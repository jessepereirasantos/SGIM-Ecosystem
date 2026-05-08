<?php
/**
 * SGIM OTA - FOUNDATION INITIALIZER
 * Cria a estrutura física e valida permissões internamente.
 */

$basePath = __DIR__ . '/';
$folders = ['releases', 'shared', 'updates'];
$report = [];

foreach ($folders as $folder) {
    $path = $basePath . $folder;
    
    // 1. Criar Pasta (Idempotente)
    if (!file_exists($path)) {
        if (mkdir($path, 0755, true)) {
            $report[$folder]['status'] = 'CREATED';
        } else {
            $report[$folder]['status'] = 'ERROR_MKDIR';
        }
    } else {
        $report[$folder]['status'] = 'EXISTS';
    }

    // 2. Segurança: index.html
    file_put_contents($path . '/index.html', '<!-- SGIM Industrial -->');
    
    // 3. Segurança: .htaccess (Deny All)
    $htaccess = "Order Deny,Allow\nDeny from all";
    file_put_contents($path . '/.htaccess', $htaccess);
}

// 4. Inicializar current_release.txt
if (!file_exists($basePath . 'current_release.txt')) {
    file_put_contents($basePath . 'current_release.txt', 'base', LOCK_EX);
}

// 5. Auto-Teste de Capacidades (Grava em log local para evitar 406 do firewall)
$logFile = $basePath . 'foundation_audit.log';
$audit = "AUDIT DATE: " . date('Y-m-d H:i:s') . "\n";

// Teste FLOCK
$fp = fopen($basePath . 'updates/lock.test', 'w');
$audit .= "FLOCK: " . ($fp && flock($fp, LOCK_EX) ? 'OK' : 'FAIL') . "\n";
if ($fp) { flock($fp, LOCK_UN); fclose($fp); }

// Teste RENAME
$audit .= "RENAME: " . (rename($basePath . 'updates/lock.test', $basePath . 'updates/rename.test') ? 'OK' : 'FAIL') . "\n";

// Teste ZIP
$audit .= "ZIPARCHIVE: " . (class_exists('ZipArchive') ? 'OK' : 'FAIL') . "\n";

// Teste OPCACHE
$audit .= "OPCACHE_RESET: " . (function_exists('opcache_reset') ? 'OK' : 'FAIL') . "\n";

file_put_contents($logFile, $audit);

echo "FOUNDATION INITIALIZED. Check foundation_audit.log for details.";
