<?php
/**
 * SGIM - Gerador de Pacote OTA Limpo
 */
$version = "1.2.0";
$targetZip = __DIR__ . "/updates/sgim_v{$version}.zip";
$tempDir = __DIR__ . "/updates/temp_build/";

if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

// 1. Criar o JSON Puro e Válido com Blindagem
$jsonContent = json_encode([
    "version" => $version,
    "channel" => "stable",
    "build"   => date('YmdHis')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

file_put_contents($tempDir . "version.json", $jsonContent);

// 2. Criar o ZIP via PHP (Garante compatibilidade total)
$zip = new ZipArchive();
if ($zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $zip->addFile($tempDir . "version.json", "version.json");
    $zip->close();
    echo "<h1>Pacote v{$version} Gerado com Sucesso!</h1>";
    echo "<p>Local: <code>$targetZip</code></p>";
    echo "<p>Conteúdo do JSON:</p><pre>" . htmlspecialchars($jsonContent) . "</pre>";
} else {
    echo "Falha ao criar ZIP.";
}

// Limpeza
unlink($tempDir . "version.json");
rmdir($tempDir);
