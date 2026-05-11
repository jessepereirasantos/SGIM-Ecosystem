<?php
/**
 * SGIM Auto-Zip Utility
 * Este script automatiza a compactação da pasta SGIM-CLIENTE para o Master.
 * Deve ser executado sempre que houver mudanças no código do cliente.
 */

$sourceDir = realpath(__DIR__ . '/SGIM-CLIENTE');
$destDownloadsDir = realpath(__DIR__ . '/SGIM-VENDAS/downloads');
if (!$destDownloadsDir) {
    // Caso a pasta não exista, usar caminho absoluto e criar
    $destDownloadsDir = __DIR__ . '/SGIM-VENDAS/downloads';
    if (!is_dir($destDownloadsDir)) mkdir($destDownloadsDir, 0755, true);
}
$destZip = $destDownloadsDir . DIRECTORY_SEPARATOR . 'sgim_master.zip';

echo "Iniciando compactação automática...\n";
echo "Origem: $sourceDir\n";
echo "Destino: $destZip\n";

if (!is_dir($sourceDir)) {
    die("Erro: Pasta SGIM-CLIENTE não encontrada em $sourceDir\n");
}

if (file_exists($destZip)) {
    unlink($destZip);
}

$zip = new ZipArchive();
if ($zip->open($destZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Erro: Não foi possível criar o arquivo ZIP em $destZip\n");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);

        // Pular pastas de backup ou temporárias se existirem
        if (strpos($relativePath, 'backups' . DIRECTORY_SEPARATOR) === 0) continue;
        if (strpos($relativePath, '.git' . DIRECTORY_SEPARATOR) === 0) continue;
        
        // Pular configurações e estado de instalação
        if (strpos($relativePath, 'config' . DIRECTORY_SEPARATOR . 'db_config.php') === 0) continue;
        if (strpos($relativePath, '.installed') === 0) continue;
        if (strpos($relativePath, 'debug_') === 0) continue;

        $zip->addFile($filePath, $relativePath);
        $count++;
    }
}

$zip->close();
echo "Sucesso! $count arquivos compactados em sgim_master.zip\n";
