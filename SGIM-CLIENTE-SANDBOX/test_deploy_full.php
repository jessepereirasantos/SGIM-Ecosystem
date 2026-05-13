<?php
/**
 * SGIM - Teste de Ciclo Completo (Etapa 4.5)
 */
require_once 'config/database.php'; 
require_once 'src/Updater/VersionManager.php';
require_once 'src/Updater/UpdaterCoreV4.php';

if (!isset($pdo) || $pdo === null) {
    foreach (get_defined_vars() as $var) {
        if ($var instanceof PDO) { $pdo = $var; break; }
    }
}

$licenseKey = 'SGIM-5C8E-B382-49D9-8511';
$masterUrl  = 'https://escolateologicaeloha.com.br/';

try {
    $updater = new \App\Updater\UpdaterCoreV4($pdo, $licenseKey, $masterUrl);
    
    echo "<h1>🚀 Teste Ciclo Completo - Etapa 4.5</h1>";
    echo "<p>Iniciando Orquestração: Arquivos + Banco de Dados...</p>";

    // Executa o Deploy Completo
    $resultado = $updater->executeDeploy('1.2.0');

    echo "<h2 style='color: green;'>✅ Ciclo Finalizado com Sucesso!</h2>";
    echo "<ul>";
    echo "<li><strong>Estado dos Arquivos:</strong> v1.2.0 aplicado.</li>";
    
    // Verificação de Banco ao vivo para o relatório
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'last_ota_sync'");
    $stmt->execute();
    $lastSync = $stmt->fetchColumn();
    
    echo "<li><strong>Estado do Banco:</strong> Chave 'last_ota_sync' = <code>$lastSync</code></li>";
    echo "<li><strong>Log de Auditoria:</strong> Gravado em <code>backups/update_log.txt</code></li>";
    echo "</ul>";

    echo "<h3>Auditoria do Log:</h3>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border: 1px solid #ccc;'>" . file_get_contents('backups/update_log.txt') . "</pre>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Falha no Ciclo:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
