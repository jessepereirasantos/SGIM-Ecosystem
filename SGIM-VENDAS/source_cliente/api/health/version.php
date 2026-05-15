<?php
/**
 * SGIM OTA v2.0 - HEALTH CHECK ENDPOINT
 * Arquivo vital obrigatório. Validado após o Atomic Swap.
 */
header('Content-Type: application/json; charset=utf-8');

// O manifesto estará na raiz da versão extraída
$manifestPath = __DIR__ . '/../../release_manifest.json';
$version = 'unknown';
$releaseId = 'unknown';

if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
    $version = $manifest['version'] ?? 'unknown';
    $releaseId = $manifest['release_id'] ?? 'unknown';
} else {
    // Se não achar o manifesto na mesma pasta, tenta achar o fallback (caso de desenvolvimento)
    $fallbackPath = __DIR__ . '/../../releases/current/release_manifest.json';
    if (file_exists($fallbackPath)) {
        $manifest = json_decode(file_get_contents($fallbackPath), true);
        $version = $manifest['version'] ?? 'unknown';
        $releaseId = $manifest['release_id'] ?? 'unknown';
    }
}

// Se o PHP conseguir rodar até aqui sem fatal errors (require faltante, etc), o status básico é operacional.
echo json_encode([
    "version" => $version,
    "release_id" => $releaseId,
    "status" => "operational",
    "timestamp" => date('c'),
    "php_version" => PHP_VERSION
]);
