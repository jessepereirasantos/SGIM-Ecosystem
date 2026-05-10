<?php
/**
 * SGIM - Teste de Micro-Etapa 4.2 (Simulação de Deploy)
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
    
    echo "<h1>Teste Etapa 4.2 - Simulação de Deploy</h1>";
    echo "<p>Analisando pacote extraído na pasta temporária...</p>";

    $relatorio = $updater->simulateDeploy();

    echo "<h3>Relatório de Intenções:</h3>";
    
    echo "<h4>📝 Arquivos a serem Sobrescritos (" . count($relatorio['to_overwrite']) . "):</h4>";
    echo "<ul><li>" . implode("</li><li>", $relatorio['to_overwrite']) . "</li></ul>";

    echo "<h4>🆕 Novos Arquivos (" . count($relatorio['to_create']) . "):</h4>";
    echo "<ul><li>" . implode("</li><li>", $relatorio['to_create']) . "</li></ul>";

    if (!empty($relatorio['blocked'])) {
        echo "<h4>🚫 Bloqueados pela Whitelist (Segurança):</h4>";
        echo "<ul style='color: red;'><li>" . implode("</li><li>", $relatorio['blocked']) . "</li></ul>";
    }

    echo "<p><strong>Nota:</strong> Nenhum arquivo acima foi alterado. Esta é apenas uma lista de intenção.</p>";

} catch (Exception $e) {
    echo "<h3>Erro:</h3> <p>" . $e->getMessage() . "</p>";
}
