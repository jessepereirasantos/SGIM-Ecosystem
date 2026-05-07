<?php
// API Genérica para Salvar (Insert/Update) - SGIM-VENDAS
require_once '../config/database.php';

$table = $_POST['table'] ?? '';
$data = $_POST['data'] ?? [];
$id = $_POST['id'] ?? null;

$allowed_tables = ['clientes', 'vendas', 'cupons'];

if (!in_array($table, $allowed_tables) || empty($data)) {
    die(json_encode(['success' => false, 'message' => 'Dados inválidos.']));
}

try {
    if ($id) {
        // UPDATE
        $sets = [];
        $params = [];
        foreach ($data as $key => $val) {
            $sets[] = "`{$key}` = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // INSERT
        $cols = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
