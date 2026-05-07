<?php
/**
 * AJAX WHATSAPP SEND - SGIM-CLIENTE
 * Processa o envio de mensagens individuais ou em massa via API Discloud.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$api_url = rtrim($data['api_url'] ?? '', '/');
$api_token = $data['api_token'] ?? '';
$message = $data['message'] ?? '';
$type = $data['type'] ?? 'all';
$filter_id = $data['filter_id'] ?? null;

if (empty($api_url) || empty($api_token) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Configurações de API ou mensagem ausentes.']);
    exit;
}

try {
    // 1. Buscar Destinatários com base no Filtro
    $sql = "SELECT m.nome, m.telefone FROM membros m 
            JOIN usuarios u ON u.id = ? 
            WHERE m.status = 'Ativo' AND m.telefone IS NOT NULL AND m.telefone != ''";
    $params = [$_SESSION['user_id']];

    if ($type === 'individual' && $filter_id) {
        $sql .= " AND m.id = ?";
        $params[] = $filter_id;
    } elseif ($type === 'cargo' && $filter_id) {
        $sql .= " AND m.cargo_id = ?";
        $params[] = $filter_id;
    } elseif ($type === 'congregacao' && $filter_id) {
        $sql .= " AND m.congregacao_id = ?";
        $params[] = $filter_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($destinatarios)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum membro ativo com telefone encontrado para este filtro.']);
        exit;
    }

    // 2. Processar Envio (Se solicitado envio direto via PHP para evitar CORS)
    if (isset($data['action']) && $data['action'] === 'send_direct') {
        $contact = $data['contact'] ?? null;
        if (!$contact) {
            echo json_encode(['success' => false, 'message' => 'Contato não fornecido.']);
            exit;
        }

        $phone = preg_replace('/\D/', '', $contact['telefone']);
        if (strlen($phone) === 11 && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        } elseif (strlen($phone) === 10 && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        // SGIM Eloha Bot Integration Fix
        // 1. Tentar capturar a instance_id ativa automaticamente (Se for a API Sgim Bot)
        $base_url = preg_replace('#/api/?$#', '', $api_url); // Remove /api do final se houver
        $ch_inst = curl_init($base_url . '/api/instances');
        curl_setopt($ch_inst, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_inst, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_token,
            'Accept: application/json'
        ]);
        curl_setopt($ch_inst, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch_inst, CURLOPT_TIMEOUT, 5);
        $res_inst = curl_exec($ch_inst);
        curl_close($ch_inst);
        
        $instances_data = json_decode($res_inst, true);
        $instance_id = null;
        if (isset($instances_data['instances']) && is_array($instances_data['instances'])) {
            foreach ($instances_data['instances'] as $inst) {
                if ($inst['status'] === 'connected') {
                    $instance_id = $inst['id'];
                    break;
                }
            }
            if (!$instance_id && !empty($instances_data['instances'])) {
                $instance_id = $instances_data['instances'][0]['id'];
            }
        }

        $payload = json_encode([
            'number' => $phone, // Padrão Baileys antigo
            'to' => $phone . '@c.us', // Padrão Sgim Bot
            'message' => $message,
            'instance_id' => $instance_id // Requisitado obrigatoriamente pelo Sgim Bot
        ]);

        // Tentar múltiplos endpoints, incluindo o oficial do Bot Eloha ('/api/messages/send')
        $endpoints = ['/api/messages/send', '/messages/send', '/send-message', '/send', '/message/send'];
        $success = false;
        $lastError = '';

        foreach ($endpoints as $endpoint) {
            // Se o endpoint for diferente do oficial mas batermos na API nova, evitamos path quebrado
            $finalUrl = $api_url . $endpoint;
            if ($endpoint === '/api/messages/send') {
                $finalUrl = $base_url . $endpoint; // Garante que não duplique /api/api
            }

            $ch = curl_init($finalUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_token,
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $success = true;
                break;
            } else {
                $resData = json_decode($response, true);
                $errMsg = $resData['message'] ?? $resData['error'] ?? $error ?? 'Erro desconhecido';
                $lastError = "HTTP $httpCode: $errMsg";
                
                // Se der erro de Autenticação (401) ou Bad Request (400) ou Timeout, é o endpoint certo mas com dados errados! Interrompe o loop.
                if ($httpCode !== 404 && $httpCode !== 0) {
                    break;
                }
            }
        }

        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $lastError]);
        }
        exit;
    }

    // 3. Retornar a lista para o JS processar o envio (Fila Controlada)
    echo json_encode([
        'success' => true,
        'count' => count($destinatarios),
        'contacts' => $destinatarios
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar destinatários: ' . $e->getMessage()]);
}
