<?php
/**
 * SGIM - Teste de Micro-Etapa 4.4 (Orquestrador de Deploy)
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
    
    echo "<h1>Teste Etapa 4.4 - Orquestrador de Deploy</h1>";
    echo "<p>Iniciando processo completo de deploy v1.2.0...</p>";

    // Executa o Deploy (Isso fará Snapshot -> Deploy de arquivos -> Log)
    $resultado = $updater->executeDeploy('1.2.0');

    echo "<h3 style='color: green;'>SUCESSO TOTAL!</h3>";
    echo "<ul>";
    echo "<li><strong>Mensagem:</strong> " . $resultado['message'] . "</li>";
    echo "<li><strong>Snapshot Gerado:</strong> <code>" . $resultado['snapshot'] . "</code></li>";
    echo "<li><strong>Log:</strong> Verifique o arquivo <code>backups/update_log.txt</code></li>";
    echo "</ul>";
    echo "<p>O sistema foi atualizado de forma atômica e segura.</p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>FALHA CONTROLADA:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Verifique o log para confirmar se o Rollback foi acionado corretamente.</p>";
}
