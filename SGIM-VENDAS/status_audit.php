<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/database.php';

echo "SISTEMA DE AUDITORIA DE STATUS\n";
echo "================================\n";

// 1. Verificar Pedidos PENDENTES que deveriam estar APROVADOS
$stmt = $pdo->query("SELECT id, payment_id, status FROM pedidos WHERE status = 'PENDENTE' OR status = 'pendente'");
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Pedidos com status Pendente: " . count($pedidos) . "\n";
foreach($pedidos as $p) {
    echo "ID: {$p['id']} | MP_ID: {$p['payment_id']} | Status: {$p['status']}\n";
}

// 2. Verificar se a tabela VENDAS existe e tem dados
try {
    $stmt = $pdo->query("SELECT id, status FROM vendas LIMIT 5");
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTabela VENDAS está acessível. Últimos 5 registros:\n";
    foreach($vendas as $v) {
        echo "ID: {$v['id']} | Status: {$v['status']}\n";
    }
} catch(Exception $e) { echo "\nERRO na tabela VENDAS: " . $e->getMessage() . "\n"; }

// 3. Verificar SITE_URL
echo "\nSITE_URL: " . (defined('SITE_URL') ? SITE_URL : "N/A") . "\n";
