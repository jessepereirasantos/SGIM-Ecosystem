<?php
/**
 * SGIM-OTA SYSTEM AUDITOR - LOCALIZADOR DE ARQUIVOS MENTIROSOS
 */
header('Content-Type: text/plain');

$target_string = "Nenhuma atualização";
$root_path = realpath(__DIR__ . '/');

echo "INICIANDO AUDITORIA NO SERVIDOR\n";
echo "Root Path: $root_path\n";
echo "Buscando por: '$target_string'\n\n";

$it = new RecursiveDirectoryIterator($root_path);
foreach(new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() == 'php') {
        $content = file_get_contents($file);
        if (strpos($content, $target_string) !== false) {
            echo "ACHEI! Arquivo mentiroso localizado:\n";
            echo "Caminho: " . $file->getPathname() . "\n";
            echo "Tamanho: " . $file->getSize() . " bytes\n";
            echo "Data Modificacao: " . date('Y-m-d H:i:s', $file->getMTime()) . "\n";
            echo "----------------------------------------\n";
        }
    }
}

echo "\nAUDITORIA CONCLUÍDA.";
