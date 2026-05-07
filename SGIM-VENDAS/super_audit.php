<?php
require_once 'config/database.php';
header('Content-Type: text/plain');

echo "SGIM DEEP AUDIT - " . date('Y-m-d H:i:s') . "\n";
echo "----------------------------------------\n";

// 1. Verificar Tabela Pedidos
try {
    $stmt = $pdo->query("SELECT * FROM pedidos ORDER BY id DESC LIMIT 5");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "ULTIMOS 5 PEDIDOS:\n";
    foreach($pedidos as $p) {
        echo "ID: {$p['id']} | Cliente: {$p['cliente_id']} | Valor: {$p['valor']} | Status: {$p['status']} | MP_ID: {$p['payment_id']}\n";
    }
} catch(Exception $e) { echo "ERRO PEDIDOS: " . $e->getMessage() . "\n"; }

echo "\n----------------------------------------\n";

// 2. Verificar Tabela Vendas
try {
    $stmt = $pdo->query("SELECT * FROM vendas ORDER BY id DESC LIMIT 5");
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "ULTIMAS 5 VENDAS:\n";
    foreach($vendas as $v) {
        echo "ID: {$v['id']} | Pedido_ID: " . ($v['pedido_id'] ?? 'N/A') . " | Status: {$v['status']} | Total: {$v['total']}\n";
    }
} catch(Exception $e) { echo "ERRO VENDAS: " . $e->getMessage() . "\n"; }

echo "\n----------------------------------------\n";

// 3. Verificar SITE_URL configurado
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : "NAO DEFINIDO") . "\n";
