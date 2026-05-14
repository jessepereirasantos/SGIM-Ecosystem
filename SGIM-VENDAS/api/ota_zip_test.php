<?php
/**
 * SGIM MASTER - ISOLATED ZIP STRESS TEST (LAYER 1)
 * Este arquivo serve APENAS para validar a estabilidade física do motor de compactação.
 * NÃO altera banco, NÃO publica release, NÃO afeta o sistema.
 */

header('Content-Type: application/json; charset=utf-8');
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $startTime = microtime(true);
    $startMem = memory_get_usage();
    
    // Configurações de Origem e Destino Temporário
    $sourceDir = dirname(__DIR__) . '/source_cliente/';
    if (!is_dir($sourceDir)) throw new Exception("Diretório source_cliente não encontrado.");
    
    $tempZip = sys_get_temp_dir() . '/ota_stress_test_' . uniqid() . '.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Falha ao criar ZIP temporário.");
    }

    // Camada 1: Bloqueio de Origem e Profundidade (CORRIGIDO: Sem FOLLOW_SYMLINKS)
    $excludeDirs = ['shared', 'releases', 'workspace', 'downloads', 'backups', 'node_modules', 'vendor', '.git'];
    
    $directory = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS); // REMOVIDO FOLLOW_SYMLINKS
    
    $filter = new RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($excludeDirs) {
        if ($current->isDir()) {
            return !in_array($current->getFilename(), $excludeDirs);
        }
        return true;
    });

    $files = new RecursiveIteratorIterator($filter);
    $files->setMaxDepth(10); // TRAVA DE PROFUNDIDADE OBRIGATÓRIA

    $count = 0;
    $memLimit = (int)ini_get('memory_limit') * 1024 * 1024;
    if ($memLimit <= 0) $memLimit = 256 * 1024 * 1024;

    foreach ($files as $file) {
        // Monitoramento de RAM
        if (memory_get_usage() > ($memLimit * 0.8)) {
            throw new Exception("FAIL_SAFE: Limite de memória atingido no teste.");
        }

        if (!$file->isDir()) {
            $p = $file->getRealPath();
            $r = str_replace('\\', '/', substr($p, strlen(realpath($sourceDir)) + 1));
            
            $zip->addFile($p, $r);
            $count++;
        }
    }
    
    $zip->close();
    $size = filesize($tempZip);
    @unlink($tempZip); // Limpa o teste imediatamente

    echo json_encode([
        "success" => true,
        "stage" => "STRESS_TEST_LAYER_1",
        "files_count" => $count,
        "ignored_dirs_count" => count($excludeDirs),
        "memory_peak_mb" => round(memory_get_peak_usage() / 1024 / 1024, 2),
        "time_seconds" => round(microtime(true) - $startTime, 4),
        "zip_size_mb" => round($size / 1024 / 1024, 2),
        "temp_zip_path" => $tempZip,
        "source_dir" => $sourceDir,
        "symlink_following" => false,
        "max_depth" => 10
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
