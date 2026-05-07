<?php
/**
 * API: Gerenciamento de Notificações - SGIM
 * Ações: marcar_lidas, excluir_todas
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/bootstrap.php';

// Compatibilidade com ambos os módulos (cliente usa user_id, admin usa usuario_id)
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) && empty($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'marcar_lidas') {
        $stmt = $pdo->prepare("UPDATE sistema_novidades SET visto = 1 WHERE visto = 0");
        $stmt->execute();
        
        // Limpar flag de cache da sessão se houver
        unset($_SESSION['ota_available']);
        
        echo json_encode(['success' => true, 'message' => 'Todas marcadas como lidas.']);
    } 
    elseif ($acao === 'excluir_todas') {
        $stmt = $pdo->prepare("DELETE FROM sistema_novidades");
        $stmt->execute();
        
        // Limpar flag de cache da sessão
        unset($_SESSION['ota_available']);
        unset($_SESSION['last_ota_check']);

        echo json_encode(['success' => true, 'message' => 'Notificações excluídas com sucesso.']);
    } 
    else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
