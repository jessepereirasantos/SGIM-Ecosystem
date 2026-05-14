<?php
/**
 * SGIM MASTER - ISOLATED ZIP STRESS & INTEGRITY TEST (LAYER 1)
 */

header('Content-Type: application/json; charset=utf-8');
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $startTime = microtime(true);
    $startMem = memory_get_usage();
    
    $sourceDir = dirname(__DIR__) . '/source_cliente/';
    if (!is_dir($sourceDir)) throw new Exception("Diretório source_cliente não encontrado.");
    
    $tempZip = sys_get_temp_dir() . '/ota_integrity_test_' . uniqid() . '.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Falha ao criar ZIP temporário.");
    }

    $excludeDirs = ['shared', 'releases', 'workspace', 'downloads', 'backups', 'node_modules', 'vendor', '.git'];
    $directory = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
    
    $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($excludeDirs) {
        if ($current->isDir()) {
            return !in_array($current->getFilename(), $excludeDirs);
        }
        return true;
    });

    $files = new RecursiveIteratorIterator($filter);
    $files->setMaxDepth(10);

    $count = 0;
    $dirStats = [];
    $criticalFiles = [
        'index.php' => false,
        'api/ota_install.php' => false,
        'includes/system/OtaOrchestrator.php' => false,
        'api/health/version.php' => false
    ];

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $p = $file->getRealPath();
            $r = str_replace('\\', '/', substr($p, strlen(realpath($sourceDir)) + 1));
            
            // Auditoria por Diretório
            $topDir = explode('/', $r)[0];
            if (!isset($dirStats[$topDir])) $dirStats[$topDir] = 0;
            $dirStats[$topDir]++;

            // Checagem de Arquivos Críticos
            if (isset($criticalFiles[$r])) $criticalFiles[$r] = true;

            $zip->addFile($p, $r);
            $count++;
        }
    }
    
    $zip->close();
    $size = filesize($tempZip);
    @unlink($tempZip);

    // Validação de Anomalia
    $errors = [];
    if (!$criticalFiles['index.php']) $errors[] = "index.php ausente";
    if (!isset($dirStats['api']) || $dirStats['api'] === 0) $errors[] = "pasta api vazia ou ausente";
    if (!isset($dirStats['includes']) || $dirStats['includes'] === 0) $errors[] = "pasta includes vazia ou ausente";

    if (!empty($errors)) {
        echo json_encode([
            "success" => false,
            "reason" => "INVALID_PACKAGE_STRUCTURE",
            "errors" => $errors,
            "dir_stats" => $dirStats,
            "critical_files" => $criticalFiles
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "stage" => "INTEGRITY_TEST_LAYER_1",
        "files_count" => $count,
        "memory_peak_mb" => round(memory_get_peak_usage() / 1024 / 1024, 2),
        "time_seconds" => round(microtime(true) - $startTime, 4),
        "zip_size_mb" => round($size / 1024 / 1024, 2),
        "directory_stats" => $dirStats,
        "structural_validation" => $criticalFiles,
        "source_dir" => $sourceDir
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "fatal" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}
