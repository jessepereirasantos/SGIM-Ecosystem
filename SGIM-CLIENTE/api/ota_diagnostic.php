<?php
/**
 * SGIM OTA - PERÍCIA TÉCNICA v1.1.41
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$report = ["checkpoints" => []];

// 1. Versão no Banco
$stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
$report['checkpoints']['db_version'] = $stmt->fetchColumn();

// 2. Scan da pasta de Releases
$releasesDir = __DIR__ . '/../releases/';
$releases = array_diff(scandir($releasesDir), ['.', '..', 'base']);
$report['checkpoints']['folders_in_releases'] = array_values($releases);

foreach ($releases as $rel) {
    $path = $releasesDir . $rel . '/';
    $files = is_dir($path) ? array_diff(scandir($path), ['.', '..']) : "NÃO É DIRETÓRIO";
    $report['checkpoints']['content_of_' . $rel] = [
        "is_dir" => is_dir($path),
        "file_count" => is_array($files) ? count($files) : 0,
        "has_manifest" => is_array($files) && in_array('release_manifest.json', $files),
        "sample_files" => is_array($files) ? array_slice($files, 0, 5) : []
    ];
}

// 3. Leitura do Activation Log (O segredo do erro)
$logFile = __DIR__ . '/../shared/system/logs/activation.log';
if (file_exists($logFile)) {
    $lines = explode("\n", trim(file_get_contents($logFile)));
    $report['checkpoints']['activation_log_tail'] = array_slice($lines, -15);
} else {
    $report['checkpoints']['activation_log_tail'] = "LOG NÃO ENCONTRADO";
}

echo json_encode($report, JSON_PRETTY_PRINT);
