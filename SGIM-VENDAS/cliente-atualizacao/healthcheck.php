<?php
header('Content-Type: application/json; charset=utf-8');

$build = [
    'sgim_build' => 'healthcheck-2026-03-23-22h',
    'php' => PHP_VERSION,
    'time' => date('c'),
    'cwd' => getcwd(),
    'script' => __FILE__,
];

$paths = [
    'db_config_candidates' => [
        __DIR__ . '/config/db_config.php',
        __DIR__ . '/db_config.php',
        __DIR__ . '/../db_config.php',
    ],
    'installed_candidates' => [
        __DIR__ . '/.installed',
        __DIR__ . '/config/.installed',
        __DIR__ . '/../.installed',
    ],
];

$found = [
    'db_config_found' => null,
    'installed_found' => null,
];

foreach ($paths['db_config_candidates'] as $p) {
    if (file_exists($p)) {
        $found['db_config_found'] = $p;
        break;
    }
}
foreach ($paths['installed_candidates'] as $p) {
    if (file_exists($p)) {
        $found['installed_found'] = $p;
        break;
    }
}

$pdo_ok = false;
$pdo_error = null;

// Try include config and test connection
$pdo = null;
if ($found['db_config_found']) {
    try {
        include $found['db_config_found'];
    } catch (Throwable $t) {
        $pdo_error = 'include_error: ' . $t->getMessage();
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->query('SELECT 1');
        $pdo_ok = true;
    } catch (Throwable $t) {
        $pdo_ok = false;
        $pdo_error = 'pdo_error: ' . $t->getMessage();
    }
}

echo json_encode([
    'build' => $build,
    'paths' => $paths,
    'found' => $found,
    'pdo_ok' => $pdo_ok,
    'pdo_error' => $pdo_error,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
