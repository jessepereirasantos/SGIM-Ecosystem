<?php
/**
 * SGIM CLIENT - CONTROLADOR OTA V4.5 (FORCE CHECK)
 */
header('Content-Type: application/json');
session_start();

require_once '../config/db.php';
require_once '../src/Updater/UpdaterCore.php';
use App\Updater\UpdaterCore;

// 1. PEGAR VERSÃO LOCAL (Prioridade version.json)
$v_local = '1.1.0';
$v_file = '../version.json';
if (file_exists($v_file)) {
    $v_data = json_decode(file_get_contents($v_file), true);
    $v_local = $v_data['version'] ?? '1.1.0';
} else {
    $v_local = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'")->fetchColumn() ?: '1.1.0';
}

$stmtLic = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
$stmtLic->execute();
$licenseKey = $stmtLic->fetchColumn() ?: '';

// 2. CONSULTAR MASTER
$domain = $_SERVER['HTTP_HOST'] ?? '';
$master_url = "https://escolateologicaeloha.com.br/api/update/v2/check.php"
            . "?version=" . urlencode($v_local)
            . "&license_key=" . urlencode($licenseKey)
            . "&domain=" . urlencode($domain)
            . "&t=" . time(); // Anti-cache

$ch = curl_init($master_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$master_json = curl_exec($ch);
curl_close($ch);

$master_data = json_decode($master_json, true);

// Se for apenas checagem, retorna agora
if (isset($_GET['check_only'])) {
    echo json_encode([
        'has_update' => $master_data['has_update'] ?? false,
        'latest' => $master_data['latest'] ?? $v_local,
        'current' => $v_local
    ]);
    exit;
}

// 3. EXECUÇÃO DO UPDATE REAL
if (isset($master_data['has_update']) && $master_data['has_update']) {
    $download_url = "https://escolateologicaeloha.com.br/api/update/v2/download.php"
                  . "?version=" . urlencode($master_data['latest'])
                  . "&license_key=" . urlencode($licenseKey)
                  . "&domain=" . urlencode($domain);

    $updater = new UpdaterCore($pdo, $licenseKey, $v_local);
    $updater->setApiUrl("https://escolateologicaeloha.com.br/");

    try {
        $success = $updater->update($master_data['latest'], $download_url, $master_data['hash']);
        echo json_encode(['success' => $success, 'version' => $master_data['latest']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => true, 'updated' => false, 'message' => 'Nenhuma atualização pendente.']);
}
