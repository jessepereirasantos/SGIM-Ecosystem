<?php
require_once 'config/database.php';
require_once 'src/Updater/UpdaterCore.php';
use App\Updater\UpdaterCore;

echo "<h1>🔍 Diagnóstico de Conexão OTA</h1>";

// 1. Coleta de dados locais
$stmtCfg = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
$masterUrl = $stmtCfg->fetchColumn() ?: 'https://escolateologicaeloha.com.br/';

$stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
$licenseKey = $stmtLic->fetchColumn();

$stmtVer = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
$currentVersion = $stmtVer->fetchColumn() ?: '1.1.0';

echo "<ul>";
echo "<li><b>URL Master:</b> $masterUrl</li>";
echo "<li><b>Licença Local:</b> $licenseKey</li>";
echo "<li><b>Versão Local:</b> $currentVersion</li>";
echo "<li><b>Domínio Enviado:</b> " . $_SERVER['HTTP_HOST'] . "</li>";
echo "</ul>";

// 2. Simulação de Chamada
$updater = new UpdaterCore($pdo, $licenseKey, $currentVersion);
$updater->setApiUrl($masterUrl);

echo "<h3>📡 Chamando Master...</h3>";
$response = $updater->checkForUpdate();

echo "<pre style='background: #000; color: #0f0; padding: 20px; border-radius: 10px;'>";
print_r($response);
echo "</pre>";

if (isset($response['has_update']) && $response['has_update']) {
    echo "<h2 style='color: green;'>✅ Sucesso! O Master oferece a versão " . $response['latest_version'] . "</h2>";
} else {
    echo "<h2 style='color: red;'>❌ O Master não ofereceu atualização.</h2>";
    echo "<p>Motivo: " . ($response['message'] ?? 'Desconhecido') . "</p>";
}
