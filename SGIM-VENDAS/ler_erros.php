<?php
/**
 * SGIM MASTER - DIAGNÓSTICO DE ERRO REAL
 */
header('Content-Type: text/plain; charset=utf-8');

$log_file = 'error_log';

if (file_exists($log_file)) {
    echo "--- ÚLTIMAS 50 LINHAS DO ERROR_LOG ---\n\n";
    $lines = file($log_file);
    $last_lines = array_slice($lines, -50);
    foreach ($last_lines as $line) {
        echo $line;
    }
} else {
    echo "ERRO: Arquivo 'error_log' não encontrado na raiz.\n";
    echo "Tentando listar arquivos na raiz para ver se tem outro nome...\n";
    print_r(scandir('.'));
}
