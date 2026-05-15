<?php
/**
 * SGIM MASTER - OTA CHECK ENDPOINT
 * Verifica se há atualização disponível para um cliente específico.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$license = $_GET['license'] ?? '';
$clientVersion = $_GET['v'] ?? '';

if (empty($license) || empty($clientVersion)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing license or version']);
    exit;
}

$manifestPath = __DIR__ . '/latest.json';

if (!file_exists($manifestPath)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Manifest not found']);
    exit;
}

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!$manifest || !isset($manifest['version'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Invalid manifest']);
    exit;
}

$hasUpdate = version_compare($manifest['version'], $clientVersion, '>');

echo json_encode([
    'status' => 'success',
    'has_update' => $hasUpdate,
    'latest_version' => $manifest['version'],
    'notes' => $manifest['notes'] ?? '',
    'manifest' => $manifest
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
