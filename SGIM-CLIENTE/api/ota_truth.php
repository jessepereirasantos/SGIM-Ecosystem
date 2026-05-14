<?php
/**
 * SGIM OTA - TESTE DA VERDADE v1.1.42
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

echo "--- DIAGNÓSTICO DE REALIDADE ---\n\n";

// 1. Versão no Banco
$stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
echo "BANCO DE DADOS (versao_sistema): " . $stmt->fetchColumn() . "\n";

// 2. Versão no Arquivo header.php (Raiz)
$headerPath = __DIR__ . '/../includes/header.php';
if (file_exists($headerPath)) {
    $content = file_get_contents($headerPath);
    preg_match('/\$systemVersion\s*=\s*\'(.*?)\'/', $content, $matches);
    echo "ARQUIVO (includes/header.php): " . ($matches[1] ?? "Não encontrada") . "\n";
}

// 3. Versão no Arquivo atualizacoes.php (Raiz)
$atualizacoesPath = __DIR__ . '/../atualizacoes.php';
if (file_exists($atualizacoesPath)) {
    $content = file_get_contents($atualizacoesPath);
    preg_match('/\$currentVersion\s*=\s*\'(.*?)\'/', $content, $matches);
    echo "ARQUIVO (atualizacoes.php): " . ($matches[1] ?? "Não encontrada") . "\n";
}

// 4. Teste de Cache
echo "\n--- LIMPEZA DE CACHE ---\n";
if (function_exists('opcache_reset')) {
    echo "OPCACHE_RESET: " . (opcache_reset() ? "SINCERIDADE RESTAURADA" : "FALHA NA LIMPEZA") . "\n";
} else {
    echo "OPCACHE: Não disponível neste ambiente.\n";
}

echo "\n--- FIM DO TESTE ---";
