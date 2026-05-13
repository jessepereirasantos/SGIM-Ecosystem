<?php
/**
 * SGIM OTA - DIAGNÓSTICO DE INTEGRIDADE v1.1.41
 * Este script valida se a atualização ocorreu de fato no sistema.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$report = [
    "timestamp" => date('c'),
    "checkpoints" => []
];

// 1. Verificação de Versão no Banco
try {
    $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
    $dbVersion = $stmt->fetchColumn();
    $report['checkpoints']['database_version'] = $dbVersion ?: "NÃO ENCONTRADA";
} catch (Exception $e) {
    $report['checkpoints']['database_version'] = "ERRO: " . $e->getMessage();
}

// 2. Verificação de Arquivos na Raiz
$testFile = __DIR__ . '/../includes/system/ActivationDriverInterface.php';
$report['checkpoints']['interface_file_exists'] = file_exists($testFile);
if (file_exists($testFile)) {
    $content = file_get_contents($testFile);
    $report['checkpoints']['interface_has_protection'] = strpos($content, 'interface_exists') !== false;
    $report['checkpoints']['interface_namespace'] = strpos($content, 'namespace SGIM\OTA;') !== false ? "CORRETO (SGIM\OTA)" : "ERRADO";
}

// 3. Verificação de Releases
$releasesDir = __DIR__ . '/../releases/';
if (is_dir($releasesDir)) {
    $report['checkpoints']['available_releases'] = array_values(array_diff(scandir($releasesDir), ['.', '..']));
} else {
    $report['checkpoints']['available_releases'] = "PASTA RELEASES NÃO EXISTE";
}

// 4. Verificação de Logs Recentes
$logFile = __DIR__ . '/../shared/system/logs/activation.log';
if (file_exists($logFile)) {
    $report['checkpoints']['last_log_lines'] = explode("\n", shell_exec("tail -n 10 " . escapeshellarg($logFile)));
} else {
    $report['checkpoints']['last_log_lines'] = "LOG NÃO ENCONTRADO";
}

echo json_encode($report, JSON_PRETTY_PRINT);
