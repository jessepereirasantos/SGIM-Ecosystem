<?php
/**
 * SGIM - Motor OTA v4.2 (Dynamic Identity)
 */
error_reporting(0); 
ini_set('display_errors', 0);

require_once 'config/db.php'; 
require_once 'src/Updater/VersionManager.php';
require_once 'src/Updater/UpdaterCoreV4.php';

if (!isset($pdo) || $pdo === null) {
    foreach (get_defined_vars() as $var) {
        if ($var instanceof PDO) { $pdo = $var; break; }
    }
}

try {
    if (!$pdo) throw new Exception("Falha na ponte de conexão.");

    // 1. Obter Identidade Real do Banco
    $stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
    $licenseKey = $stmtLic->fetchColumn();

    $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
    $masterUrl = $stmtMaster->fetchColumn();

    // Fallback de segurança se o banco ainda estiver como "PADRÃO"
    if (!$masterUrl || $masterUrl === 'PADRÃO') {
        $masterUrl = 'https://escolateologicaeloha.com.br/';
    }

    if (!$licenseKey) throw new Exception("Licença não localizada no banco do cliente.");

    // 2. Executar Motor
    $updater = new \App\Updater\UpdaterCoreV4($pdo, $licenseKey, $masterUrl);
    $resultado = $updater->checkAndPrepare();

    header('Content-Type: application/json');
    echo json_encode($resultado, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'stage' => 'init', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
