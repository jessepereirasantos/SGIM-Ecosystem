<?php
/**
 * Publish Master: Sincroniza a versão atual do CLIENTE com o pacote MASTER de vendas.
 */
session_start();
if (!isset($_SESSION['user_id'])) die("Acesso negado.");

require_once 'config/database.php';

$zipName = "sgim_master.zip";
$sourceDir = __DIR__;
$destDir = __DIR__ . "/../SGIM-VENDAS/downloads/";
$destPath = $destDir . $zipName;

if (!is_dir($destDir)) {
    @mkdir($destDir, 0777, true);
}

// Criar o ZIP
$zip = new ZipArchive();
if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            // Filtros de exclusão (segurança e peso)
            $exclude = [
                'config/db.php',
                'config/db_config.php',
                '.installed',
                'backups/',
                'uploads/',
                'debug.log',
                '.git',
                'node_modules'
            ];

            $skip = false;
            foreach ($exclude as $pattern) {
                if (strpos($relativePath, $pattern) === 0 || strpos($relativePath, DIRECTORY_SEPARATOR . $pattern) !== false) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    $zip->close();
    
    // Sucesso
    header("Location: configuracoes.php?sucesso=1&msg=Pacote MASTER atualizado com sucesso!");
    exit;
} else {
    die("Falha ao criar o pacote ZIP em $destPath. Verifique permissões de escrita.");
}
