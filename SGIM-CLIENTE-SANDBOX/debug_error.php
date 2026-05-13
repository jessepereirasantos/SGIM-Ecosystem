<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'config/database.php'; 
    require_once 'src/Updater/VersionManager.php';
    require_once 'src/Updater/UpdaterCoreV4.php';

    echo "<h1>Debug de Erro PHP</h1>";
    echo "<p>Arquivos carregados com sucesso.</p>";

    $updater = new \App\Updater\UpdaterCoreV4($pdo, 'TEST', 'https://localhost');
    echo "<p>Classe instanciada com sucesso.</p>";

    // Teste de Permissão de Pasta
    $testFile = __DIR__ . '/backups/test_write.txt';
    if (@file_put_contents($testFile, "Teste de Escrita")) {
        echo "<p style='color: green;'>Permissão de escrita na pasta /backups/: OK</p>";
        @unlink($testFile);
    } else {
        echo "<p style='color: red;'>ERRO: Sem permissão de escrita na pasta /backups/</p>";
    }

} catch (Throwable $e) {
    echo "<h3>Erro Capturado:</h3>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
