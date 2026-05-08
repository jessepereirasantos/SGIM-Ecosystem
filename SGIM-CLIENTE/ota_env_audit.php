<?php
/**
 * SGIM OTA - ENVIRONMENT AUDIT
 * Este script valida se o servidor HostGator possui os requisitos para o OTA Industrial.
 */
header('Content-Type: application/json');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_SOFTWARE'],
    'php_version' => PHP_VERSION,
    'requirements' => []
];

// 1. Teste de FLOCK (Bloqueio de Arquivo)
$tmpFile = 'ota_lock_test.txt';
$fp = fopen($tmpFile, 'w');
$results['requirements']['flock'] = ($fp && flock($fp, LOCK_EX)) ? 'OK' : 'FAIL';
if ($fp) { flock($fp, LOCK_UN); fclose($fp); unlink($tmpFile); }

// 2. Teste de RENAME (Atômico)
$src = 'ota_rename_src.txt';
$dst = 'ota_rename_dst.txt';
file_put_contents($src, 'test');
$results['requirements']['rename_atomic'] = rename($src, $dst) ? 'OK' : 'FAIL';
if (file_exists($dst)) unlink($dst);
if (file_exists($src)) unlink($src);

// 3. Teste de ZipArchive
$results['requirements']['ZipArchive'] = class_exists('ZipArchive') ? 'OK' : 'FAIL';

// 4. Teste de CURL
$results['requirements']['curl'] = function_exists('curl_init') ? 'OK' : 'FAIL';

// 5. Teste de opcache_reset
$results['requirements']['opcache_reset'] = function_exists('opcache_reset') ? 'OK' : 'FAIL';

// 6. Teste de file_put_contents LOCK_EX
$results['requirements']['file_put_contents_lock'] = (file_put_contents('ota_put_test.txt', 'test', LOCK_EX) !== false) ? 'OK' : 'FAIL';
if (file_exists('ota_put_test.txt')) unlink('ota_put_test.txt');

// 7. Permissões de Escrita na Raiz
$results['requirements']['root_writable'] = is_writable('.') ? 'OK' : 'FAIL';

echo json_encode($results, JSON_PRETTY_PRINT);
