<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');

$res = [
    'db_version' => 'unknown',
    'hardcoded_version' => '1.1.54',
    'active_release' => basename(dirname(__DIR__)),
    'pdo_status' => ($pdo instanceof PDO) ? 'connected' : 'failed'
];

if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $res['db_version'] = $stmt ? $stmt->fetchColumn() : 'table_not_found';
    } catch (Exception $e) {
        $res['db_version'] = 'error: ' . $e->getMessage();
    }
}

echo json_encode($res, JSON_PRETTY_PRINT);
