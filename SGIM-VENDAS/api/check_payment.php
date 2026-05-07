<?php
/**
 * Verificação Ativa de Pagamento (Polling) - SGIM-VENDAS
 * Consulta a API do Mercado Pago e libera a licença se aprovado.
 */
header('Content-Type: application/json');
require_once '../config/database.php';
$mp_config = require_once '../config/mercadopago.php';

$venda_id = $_GET['venda_id'] ?? null;

if (!$venda_id) {
    echo json_encode(['success' => false, 'message' => 'ID da venda não fornecido.']);
    exit;
}

try {
    // 1. Buscar a venda no banco
    $stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = ?");
    $stmt->execute([$venda_id]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venda) {
        echo json_encode(['success' => false, 'message' => 'Venda não encontrada.']);
        exit;
    }

    // 2. Se a venda já estiver aprovada, retornar sucesso
    if (in_array(strtolower($venda['status']), ['approved', 'aprovado', 'pago'])) {
        echo json_encode(['success' => true, 'status' => 'approved', 'redirect' => 'cliente/dashboard.php?success=1']);
        exit;
    }

    // 3. Consultar API do Mercado Pago
    if (empty($venda['payment_id'])) {
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Aguardando processamento.']);
        exit;
    }

    $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $venda['payment_id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $mp_config['access_token']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $payment = json_decode($response, true);

    if ($http_code !== 200 || !isset($payment['status'])) {
        echo json_encode(['success' => false, 'message' => 'Erro ao consultar Mercado Pago.']);
        exit;
    }

    $status_mp = $payment['status'];

    // 4. Se aprovado, processar liberação
    if ($status_mp === 'approved') {
        try {
            $pdo->beginTransaction();

            // Atualizar status da venda
            $stmt = $pdo->prepare("UPDATE vendas SET status = 'approved', data_venda = NOW() WHERE id = ?");
            $stmt->execute([$venda['id']]);

            // Gerar Licença (se não existir)
            $stmt = $pdo->prepare("SELECT id FROM licencas WHERE pedido_id = ?");
            $stmt->execute([$venda['id']]);
            if (!$stmt->fetch()) {
                $chave = strtoupper(substr(md5(uniqid($venda['id'], true)), 0, 16));
                $api_token = bin2hex(random_bytes(32));
                
                $stmt = $pdo->prepare("INSERT INTO licencas (cliente_id, pedido_id, chave_licenca, api_token, dominio, status) VALUES (?, ?, ?, ?, 'aguardando', 'ativa')");
                $stmt->execute([$venda['cliente_id'], $venda['id'], $chave, $api_token]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'status' => 'approved', 'redirect' => 'cliente/dashboard.php?success=1']);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro ao liberar licença: ' . $e->getMessage()]);
            exit;
        }
    }

    // 5. Retornar status atual
    echo json_encode(['success' => true, 'status' => $status_mp]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
