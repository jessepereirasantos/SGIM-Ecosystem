<?php
/**
 * SGIM - Gerador de Pacote de Venda (sgim_master.zip)
 * Este script empacota a versão estável do CLIENTE para novos compradores.
 */

$sourceDir = __DIR__ . '/source_cliente/'; // Pasta com o código real do cliente
$zipFile = __DIR__ . '/sgim_master.zip';

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Erro ao criar o ZIP");
}

// Lista de arquivos e pastas para ignorar no pacote de venda
$ignore = [
    'config/db.php',
    'backups',
    'updating.lock',
    '.git',
    'test_deploy_full.php',
    'test_deploy_final.php',
    'test_writing.php',
    'test_snapshot.php',
    'test_simulation.php',
    'debug_error.php',
    'fix_config.php',
    'atualizar_pacote_venda.php'
];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen(realpath($sourceDir)) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);

        // Verifica Whitelist de exclusão
        $shouldIgnore = false;
        foreach ($ignore as $i) {
            if (strpos($relativePath, $i) === 0 || $relativePath === $i) {
                $shouldIgnore = true;
                break;
            }
        }

        if (!$shouldIgnore) {
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

echo "<h1>📦 Pacote sgim_master.zip Atualizado!</h1>";
echo "<p>Versão de Produção v1.2.0 gerada com sucesso.</p>";
echo "<p>Novos clientes agora receberão o pacote base estável.</p>";
