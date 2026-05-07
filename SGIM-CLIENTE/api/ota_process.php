<?php
/**
 * SGIM CLIENT - CONTROLADOR OTA V4.6 (DIAGNOSTIC EDITION)
 */
header('Content-Type: application/json');
session_start();

require_once '../config/db.php';
require_once '../src/Updater/UpdaterCore.php';
require_once '../includes/ota_helper.php';
use App\Updater\UpdaterCore;

// 1. IDENTIDADE LOCAL
$v_local = get_local_version(true);

$stmtLic = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
$stmtLic->execute();
$licenseKey = $stmtLic->fetchColumn() ?: '';

// 2. CONSULTA AO MASTER
$domain = $_SERVER['HTTP_HOST'] ?? '';
$master_url = "https://escolateologicaeloha.com.br/api/update/v2/check.php"
            . "?version=" . urlencode($v_local)
            . "&license_key=" . urlencode($licenseKey)
            . "&domain=" . urlencode($domain)
            . "&t=" . time();

$ch = curl_init($master_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$master_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("[TRACE-CLIENT] Chamando Master: $master_url");
error_log("[TRACE-CLIENT] Resposta do Master (HTTP $http_code): $master_json");

$master_data = json_decode($master_json, true);

// DIAGNÓSTICO EXPLICITAMENTE REQUISITADO
$remoteVersion = $master_data['latest'] ?? null;

if (empty($remoteVersion)) {
    error_log("[SGIM-OTA] [ERROR] REMOTE_VERSION_NULL | Payload: " . $master_json);
}

// LOG DE COMPARAÇÃO (REQUISITO DE ACEITE)
error_log("[SGIM-OTA] [INFO] VERSION_COMPARE | local=$v_local | remote=" . ($remoteVersion ?? 'NULL'));

if (isset($_GET['check_only'])) {
    echo json_encode([
        'has_update' => $master_data['has_update'] ?? false,
        'latest' => $remoteVersion ?? $v_local,
        'current' => $v_local
    ]);
    exit;
}

// 3. PROCESSO DE UPDATE
if (isset($master_data['has_update']) && $master_data['has_update']) {
    $download_url = $master_data['url']; // O check.php já devolve a URL montada
    
    $updater = new UpdaterCore($pdo, $licenseKey, $v_local);
    $updater->setApiUrl("https://escolateologicaeloha.com.br/");

    try {
        $success = $updater->update($remoteVersion, $download_url, $master_data['hash']);
        echo json_encode(['success' => $success, 'version' => $remoteVersion]);
    } catch (Exception $e) {
        error_log("[SGIM-OTA] [FATAL] Erro no Update: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => true, 'updated' => false]);
}
