<?php
/**
 * SGIM - Teste de Micro-Etapa 4.3 (Escrita Atômica)
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
    
    echo "<h1>Teste Etapa 4.3 - Escrita Atômica</h1>";
    echo "<p>Tentando atualizar apenas o arquivo <code>version.json</code>...</p>";

    $arquivoParaTestar = "version.json";
    
    // Captura versão antes do teste
    $versaoAntiga = file_exists($arquivoParaTestar) ? file_get_contents($arquivoParaTestar) : "Não existe";

    // Executa a escrita atômica
    $resultado = $updater->applyFileUpdate($arquivoParaTestar);

    // Captura versão depois do teste
    $versaoNova = file_get_contents($arquivoParaTestar);

    echo "<h3>Sucesso!</h3>";
    echo "<ul>";
    echo "<li><strong>Arquivo Afetado:</strong> <code>$arquivoParaTestar</code></li>";
    echo "<li><strong>Conteúdo Anterior:</strong> <pre>" . htmlspecialchars($versaoAntiga) . "</pre></li>";
    echo "<li><strong>Conteúdo Atualizado:</strong> <pre>" . htmlspecialchars($versaoNova) . "</pre></li>";
    echo "</ul>";
    echo "<p>O arquivo foi sobrescrito usando o método seguro de renomeação temporária.</p>";

} catch (Exception $e) {
    echo "<h3>Erro Crítico:</h3> <p>" . $e->getMessage() . "</p>";
}
