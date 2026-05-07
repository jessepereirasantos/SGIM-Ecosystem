<?php
/**
 * SGIM - ENDPOINT DE DIAGNÓSTICO DE BUILD (TEMPLATE DISTRIBUÍDO VIA OTA)
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$rootPath = realpath(__DIR__ . '/../');

$db_version = 'n/a';
try {
    if (file_exists($rootPath . '/config/db.php')) {
        require_once $rootPath . '/config/db.php';
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $stmt->execute();
        $db_version = $stmt->fetchColumn() ?: 'n/a';
    }
} catch (Exception $e) {
    $db_version = 'erro: ' . $e->getMessage();
}

$json_version = 'n/a';
$vjson = $rootPath . '/version.json';
if (file_exists($vjson)) {
    $decoded = json_decode(file_get_contents($vjson), true);
    $json_version = $decoded['version'] ?? 'n/a';
}

echo json_encode([
    'sgim_build'       => '2026-05-07-REV-CURL-A',
    'ota_process_rev'  => 'V4.3-cURL',
    'server_time'      => date('Y-m-d H:i:s'),
    'php_version'      => PHP_VERSION,
    'allow_url_fopen'  => ini_get('allow_url_fopen') ? 'ON' : 'OFF',
    'curl_available'   => function_exists('curl_init') ? 'SIM' : 'NÃO',
    'version_no_banco' => $db_version,
    'version_json'     => $json_version,
    'app_root'         => $rootPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
