<?php
/**
 * SGIM - Gerador de Pacote de Venda Ultra-Resiliente (v2.0)
 * Este script garante que o sgim_master.zip seja criado do zero com os arquivos corrigidos.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$sourceDir = __DIR__ . '/source_cliente/'; 
$zipFile = __DIR__ . '/sgim_master.zip';

// 1. Limpeza Preventiva
if (file_exists($zipFile)) {
    unlink($zipFile);
}

if (!is_dir($sourceDir)) {
    die("<h1>❌ Erro Fatal</h1><p>Pasta de origem <b>$sourceDir</b> não encontrada!</p>");
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("<h1>❌ Erro ao criar o ZIP</h1>");
}

$count = 0;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen(realpath($sourceDir)) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);

        // Ignorar lixo e configs antigas
        if (strpos($relativePath, 'config/db_config.php') !== false) continue;
        if (strpos($relativePath, 'config/db.php') !== false) continue;
        if (strpos($relativePath, '.installed') !== false) continue;
        if (strpos($relativePath, '.git') !== false) continue;

        $zip->addFile($filePath, $relativePath);
        $count++;
    }
}

$zip->close();

echo "<h1>📦 Pacote sgim_master.zip RECONSTRUÍDO!</h1>";
echo "<p>Total de arquivos processados: <b>$count</b></p>";
echo "<p>Pasta de Origem: <code>$sourceDir</code></p>";
echo "<p>Status: <b style='color:green'>PRONTO PARA NOVAS VENDAS</b></p>";
echo "<hr>";
echo "<p><b>Dica:</b> Se o setup.php do seu cliente ainda der erro na linha 4, verifique se o arquivo <code>$sourceDir/setup.php</code> no seu servidor realmente contém a correção do include condicional.</p>";
