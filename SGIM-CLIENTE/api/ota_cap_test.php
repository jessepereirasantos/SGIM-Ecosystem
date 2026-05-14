<?php
/**
 * SGIM OTA v2.0 - CAPABILITY TESTER
 * Valida o ambiente HostGator para o novo pipeline determinístico.
 */
header('Content-Type: application/json; charset=utf-8');

$report = [
    "environment" => [
        "os" => PHP_OS,
        "php_version" => PHP_VERSION,
        "sapi" => PHP_SAPI,
        "user" => get_current_user(),
        "uid" => getmyuid()
    ],
    "capabilities" => []
];

$testDir = __DIR__ . '/../shared/system/cap_test_' . time() . '/';
mkdir($testDir, 0755, true);

// 1. Teste de Symlink
try {
    $target = $testDir . 'target.txt';
    $link = $testDir . 'link.txt';
    file_put_contents($target, "Hello");
    $report["capabilities"]["symlink_support"] = symlink($target, $link);
    @unlink($link);
    @unlink($target);
} catch (Throwable $e) {
    $report["capabilities"]["symlink_support"] = false;
    $report["errors"]["symlink"] = $e->getMessage();
}

// 2. Teste de Rename Atômico (Diretório)
try {
    $dirA = $testDir . 'folder_A';
    $dirB = $testDir . 'folder_B';
    mkdir($dirA);
    file_put_contents($dirA . '/test.txt', "data");
    
    $start = microtime(true);
    $res = rename($dirA, $dirB);
    $end = microtime(true);
    
    $report["capabilities"]["atomic_rename_dir"] = $res;
    $report["capabilities"]["rename_time_ms"] = ($end - $start) * 1000;
} catch (Throwable $e) {
    $report["capabilities"]["atomic_rename_dir"] = false;
    $report["errors"]["rename"] = $e->getMessage();
}

// 3. Teste de OPCache
$report["capabilities"]["opcache_enabled"] = function_exists('opcache_get_status') && (opcache_get_status() !== false);
$report["capabilities"]["opcache_reset_support"] = function_exists('opcache_reset');

// 4. Teste de Permissões de Escrita na Raiz
$rootDir = realpath(__DIR__ . '/../../') . '/';
$report["capabilities"]["root_writable"] = is_writable($rootDir);

// Limpeza
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) rrmdir($dir . "/" . $object);
                else unlink($dir . "/" . $object);
            }
        }
        rmdir($dir);
    }
}
rrmdir($testDir);

echo json_encode($report, JSON_PRETTY_PRINT);
