<?php
/**
 * Script para registrar nova versão no Master
 */
require_once 'config/database.php';

$versao = '1.2.2';
$canal = 'stable';
$changelog = json_encode([
    "Melhoria no salvamento de configurações",
    "Segurança atômica v4.0",
    "Estabilidade de banco de dados"
]);

try {
    $zipPath = __DIR__ . '/sgim_master.zip';
    if (!file_exists($zipPath)) {
        throw new Exception("Arquivo sgim_master.zip não encontrado no servidor.");
    }
    
    // Cálculo real do Checksum para o OTA validar
    $checksum = hash_file('sha256', $zipPath);
    
    $versao = '1.2.2';
    $canal = 'stable';
    $changelog = json_encode([
        "Correção crítica: Salvamento de Dados da Igreja",
        "Motor OTA v4.0 (Blindagem Atômica)",
        "Sistema de Snapshots de Segurança"
    ]);

    $sql = "INSERT INTO sistema_versoes (versao, canal, path_zip, checksum_sha256, changelog, data_lancamento) 
            VALUES (?, ?, ?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            path_zip = VALUES(path_zip), 
            checksum_sha256 = VALUES(checksum_sha256), 
            changelog = VALUES(changelog)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$versao, $canal, 'sgim_master.zip', $checksum, $changelog]);
    
    echo "<h1>🚀 SUCESSO: Versão $versao Publicada!</h1>";
    echo "<p><b>Checksum:</b> $checksum</p>";
    echo "<p>Agora, ao atualizar a página do cliente, o Sininho DEVE tocar.</p>";
} catch (Exception $e) {
    echo "<h1>❌ Erro no Registro</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
