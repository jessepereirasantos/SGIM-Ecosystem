<?php
/**
 * API FIX DOMAIN - SGIM-VENDAS (v2)
 * Grava o vínculo permanente entre Domínio e Chave de Licença via Dashboard.
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$pedido_id = $data['pedido_id'] ?? null;
$dominio = trim($data['dominio'] ?? '');

// Higienização do domínio
$dominio_clean = preg_replace('/^https?:\/\//i', '', $dominio);
$dominio_clean = preg_replace('/^www\./i', '', $dominio_clean);
$dominio_clean = rtrim($dominio_clean, '/');

if (empty($pedido_id) || empty($dominio_clean)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (Domínio é obrigatório).']);
    exit;
}

try {
    // 1. Verificar se a licença pertence ao usuário e se já tem domínio fixado
    $stmt = $pdo->prepare("SELECT id, dominio FROM licencas WHERE pedido_id = ? AND cliente_id IN (SELECT id FROM clientes WHERE usuario_id = ?)");
    $stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
    $lic = $stmt->fetch();

    if (!$lic) {
        echo json_encode(['success' => false, 'message' => 'Licença não localizada ou acesso negado.']);
        exit;
    }

    if (!empty($lic['dominio']) && $lic['dominio'] !== 'venda_automática') {
        echo json_encode(['success' => false, 'message' => 'Esta licença já está vinculada ao domínio: ' . $lic['dominio']]);
        exit;
    }

    // 2. Gravar Vínculo Permanente
    $stmtUpdate = $pdo->prepare("UPDATE licencas SET dominio = ?, status = 'ativa', ultimo_acesso = NOW() WHERE id = ?");
    $stmtUpdate->execute([$dominio_clean, $lic['id']]);

    echo json_encode([
        'success' => true, 
        'message' => 'Domínio vinculado com sucesso!',
        'setup_url' => 'http://' . $dominio_clean . '/setup.php'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
