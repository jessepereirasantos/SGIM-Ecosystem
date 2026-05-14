<?php
/**
 * SGIM MASTER - ISOLATED ZIP STRESS & INTEGRITY TEST (LAYER 1 - v2)
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
    
    // 1. Auditoria de Raiz Física (Antes do ZIP)
    $rootEntries = array_diff(scandir($sourceDir), array('..', '.'));
    $hasApi = is_dir($sourceDir . 'api');
    $hasIncludes = is_dir($sourceDir . 'includes');
    $hasIndex = file_exists($sourceDir . 'index.php');

    if (!$hasApi || !$hasIncludes || !$hasIndex) {
        echo json_encode([
            "success" => false,
            "reason" => "SOURCE_CLIENTE_INVALID",
            "details" => "Estrutura vital ausente na raiz do source_cliente/",
            "checks" => [
                "api_dir" => $hasApi,
                "includes_dir" => $hasIncludes,
                "index_php" => $hasIndex
            ],
            "root_entries_found" => array_values($rootEntries)
        ]);
        exit;
    }

    $tempZip = sys_get_temp_dir() . '/ota_final_audit_' . uniqid() . '.zip';
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
    $sampleFiles = [];

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $p = $file->getRealPath();
            $r = str_replace('\\', '/', substr($p, strlen(realpath($sourceDir)) + 1));
            
            $topDir = explode('/', $r)[0];
            if (!isset($dirStats[$topDir])) $dirStats[$topDir] = 0;
            $dirStats[$topDir]++;

            if ($count < 10) $sampleFiles[] = $r; // Amostra dos primeiros 10 arquivos

            $zip->addFile($p, $r);
            $count++;
        }
    }
    
    $zip->close();
    $size = filesize($tempZip);
    @unlink($tempZip);

    echo json_encode([
        "success" => true,
        "stage" => "INTEGRITY_FINAL_AUDIT",
        "files_count" => $count,
        "memory_peak_mb" => round(memory_get_peak_usage() / 1024 / 1024, 2),
        "time_seconds" => round(microtime(true) - $startTime, 4),
        "zip_size_mb" => round($size / 1024 / 1024, 2),
        "root_entries" => array_values($rootEntries),
        "directory_stats" => $dirStats,
        "sample_files" => $sampleFiles,
        "structural_validation" => [
            "api_dir" => $hasApi,
            "includes_dir" => $hasIncludes,
            "index_php" => $hasIndex,
            "health_check" => file_exists($sourceDir . 'api/health/version.php')
        ]
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
