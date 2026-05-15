<?php
/**
 * SGIM OTA - HARD RESET TOOL v1.1.54
 * Limpa pastas temporárias e logs para destravar o sistema.
 */
header('Content-Type: text/plain; charset=utf-8');

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

$root = __DIR__ . '/../';
echo "Iniciando Limpeza OTA...\n";

// 1. Limpar Releases (exceto base)
$releasesDir = $root . 'releases/';
if (is_dir($releasesDir)) {
    foreach (scandir($releasesDir) as $f) {
        if ($f != '.' && $f != '..' && $f != 'base') {
            echo "Removendo: releases/$f\n";
            rrmdir($releasesDir . $f);
        }
    }
}

// 2. Limpar Downloads
$downloadDir = $root . 'shared/system/download/';
if (is_dir($downloadDir)) {
    foreach (scandir($downloadDir) as $f) {
        if ($f != '.' && $f != '..') {
            echo "Removendo download: $f\n";
            @unlink($downloadDir . $f);
        }
    }
}

// 3. Limpar Estado
$stateFile = $root . 'shared/system/state/current_state.json';
if (file_exists($stateFile)) {
    echo "Resetando arquivo de estado.\n";
    @unlink($stateFile);
}

echo "\nSISTEMA OTA LIMPO E DESTRAVADO.";
