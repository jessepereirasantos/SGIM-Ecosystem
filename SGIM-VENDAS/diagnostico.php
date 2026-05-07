<?php
header('Content-Type: text/plain');

echo "--- DIAGNÓSTICO DE AMBIENTE MASTER ---" . PHP_EOL;
echo "Diretório Atual (__DIR__): " . __DIR__ . PHP_EOL;

$caminho_tentado = __DIR__ . '/fonte_cliente';
echo "Caminho Tentado: " . $caminho_tentado . PHP_EOL;

echo "--- VERIFICAÇÃO DE FATOS ---" . PHP_EOL;
echo "Existe como arquivo (file_exists)? " . (file_exists($caminho_tentado) ? "SIM" : "NÃO") . PHP_EOL;
echo "É um diretório (is_dir)? " . (is_dir($caminho_tentado) ? "SIM" : "NÃO") . PHP_EOL;
echo "Caminho Real (realpath): " . (realpath($caminho_tentado) ?: "FALHA - Diretório não encontrado") . PHP_EOL;

echo "--- LISTAGEM DE DIRETÓRIOS (Para encontrar o nome correto) ---" . PHP_EOL;
$dirs = array_filter(glob(__DIR__ . '/*'), 'is_dir');
foreach ($dirs as $dir) {
    echo "Pasta Encontrada: " . basename($dir) . PHP_EOL;
}
