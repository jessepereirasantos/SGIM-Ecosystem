<?php
// API Genérica de Exclusão - SGIM-VENDAS
require_once '../config/database.php';

// Proteção básica (Poderia ser expandida com sessões reais)
// if (!isset($_SESSION['admin'])) { die('Acesso Negado'); }

$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? 0;

$allowed_tables = ['clientes', 'pedidos', 'vendas', 'cupons', 'licencas'];

if (!in_array($table, $allowed_tables) || !$id) {
    die(json_encode(['success' => false, 'message' => 'Tabela ou ID inválido.']));
}

try {
    $pdo->beginTransaction();

    if ($table === 'clientes') {
        // 1. Limpar Pagamentos via Pedidos
        $stmt = $pdo->prepare("DELETE FROM pagamentos WHERE pedido_id IN (SELECT id FROM pedidos WHERE cliente_id = ?)");
        $stmt->execute([$id]);

        // 2. Limpar Activation Requests (Pedidos de Ativação)
        $stmt = $pdo->prepare("DELETE FROM activation_requests WHERE email IN (SELECT email FROM clientes WHERE id = ?)");
        $stmt->execute([$id]);

        // 3. Limpar Licenças
        $stmt = $pdo->prepare("DELETE FROM licencas WHERE cliente_id = ?");
        $stmt->execute([$id]);

        // 4. Limpar Pedidos
        $stmt = $pdo->prepare("DELETE FROM pedidos WHERE cliente_id = ?");
        $stmt->execute([$id]);

        // 5. Limpar Usuário associado ao Cliente (para liberar o e-mail/acesso)
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id IN (SELECT usuario_id FROM clientes WHERE id = ?)");
        $stmt->execute([$id]);
    }

    if ($table === 'pedidos' || $table === 'vendas') {
        // Limpar Pagamentos associados ao pedido
        $stmt = $pdo->prepare("DELETE FROM pagamentos WHERE pedido_id = ?");
        $stmt->execute([$id]);
        
        // Limpar Activation Requests associados à licença do pedido
        $stmt = $pdo->prepare("DELETE FROM activation_requests WHERE license_key IN (SELECT chave_licenca FROM licencas WHERE pedido_id = ?)");
        $stmt->execute([$id]);

        // Limpar Licenças associadas ao pedido
        $stmt = $pdo->prepare("DELETE FROM licencas WHERE pedido_id = ?");
        $stmt->execute([$id]);
    }

    if ($table === 'licencas') {
        // Ao excluir uma licença específica, limpa seus pedidos de ativação
        $stmt = $pdo->prepare("DELETE FROM activation_requests WHERE license_key IN (SELECT chave_licenca FROM licencas WHERE id = ?)");
        $stmt->execute([$id]);
    }

    // Exclusão principal
    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro de Integridade: ' . $e->getMessage()]);
}
?>
