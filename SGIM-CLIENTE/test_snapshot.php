<?php
/**
 * SGIM - Teste de Micro-Etapa 4.1 (Snapshot)
 */
require_once 'config/db.php'; 
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
    
    echo "<h1>Teste Etapa 4.1 - Snapshot</h1>";
    echo "<p>Iniciando geração de backup de segurança...</p>";

    // Tenta gerar o snapshot da versão atual
    $versaoSimulada = "1.1.0";
    $resultado = $updater->createSnapshot($versaoSimulada);

    echo "<h3>Sucesso!</h3>";
    echo "<ul>";
    echo "<li><strong>Arquivo Gerado:</strong> <code>" . $resultado['file'] . "</code></li>";
    echo "<li><strong>Total de Arquivos no Backup:</strong> " . $resultado['count'] . "</li>";
    echo "<li><strong>Pasta de Destino:</strong> <code>backups/snapshots/</code></li>";
    echo "</ul>";
    echo "<p>Verifique via FTP/cPanel se o arquivo ZIP contém apenas os arquivos do sistema (sem config ou uploads).</p>";

} catch (Exception $e) {
    echo "<h3>Erro:</h3> <p>" . $e->getMessage() . "</p>";
}
