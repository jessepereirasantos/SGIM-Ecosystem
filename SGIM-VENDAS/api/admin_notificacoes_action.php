<?php
/**
 * API: Gerenciamento de Notificações Admin - SGIM Vendas
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'marcar_lidas') {
        $stmt = $pdo->prepare("UPDATE admin_notificacoes SET visto = 1 WHERE visto = 0");
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Todas marcadas como lidas.']);
    } 
    elseif ($acao === 'excluir_todas') {
        $stmt = $pdo->prepare("DELETE FROM admin_notificacoes");
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Notificações excluídas com sucesso.']);
    } 
    else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
