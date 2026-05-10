<?php
/**
 * SGIM - Motor OTA v5.0 (Reconstructed)
 * Endpoint local consultado pelo frontend do cliente.
 */
error_reporting(0); 
ini_set('display_errors', 0);

require_once 'config/database.php'; 
require_once 'includes/system/OtaOrchestrator.php';

if (!isset($pdo) || $pdo === null) {
    foreach (get_defined_vars() as $var) {
        if ($var instanceof PDO) { $pdo = $var; break; }
    }
}

$logFile = __DIR__ . '/ota_detection_log.json';
$telemetry = [
    'timestamp' => date('c'),
    'url_consultada' => '',
    'http_status' => 0,
    'versao_local' => '1.1.0', // Fixo temporário se não houver config
    'versao_remota' => '',
    'resultado_comparacao' => false,
    'motivo_falha' => ''
];

try {
    if (!$pdo) throw new Exception("Falha na ponte de conexão PDO.");

    $stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
    $licenseKey = $stmtLic->fetchColumn();

    $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
    $masterUrl = $stmtMaster->fetchColumn();

    if (!$masterUrl || $masterUrl === 'PADRÃO') {
        $masterUrl = 'https://escolateologicaeloha.com.br/';
    }
    
    $telemetry['url_consultada'] = rtrim($masterUrl, '/') . '/api/update/latest.json';

    // Obter versão local (simulada se banco não tiver)
    $stmtVer = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
    if($stmtVer) {
        $localVersion = $stmtVer->fetchColumn();
        if($localVersion) $telemetry['versao_local'] = $localVersion;
    }

    // Consulta real ao Master
    $json = @file_get_contents($telemetry['url_consultada']);
    if (!$json) {
        $telemetry['http_status'] = 500;
        throw new Exception("Não foi possível alcançar o MASTER em " . $telemetry['url_consultada']);
    }

    $telemetry['http_status'] = 200;
    $manifest = json_decode($json, true);
    
    if (!$manifest || !isset($manifest['version'])) {
        throw new Exception("Manifesto JSON inválido ou corrompido.");
    }

    $telemetry['versao_remota'] = $manifest['version'];
    
    // Comparação
    $hasUpdate = version_compare($manifest['version'], $telemetry['versao_local'], '>');
    $telemetry['resultado_comparacao'] = $hasUpdate;

    // Salva Telemetria
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    $logs[] = $telemetry;
    file_put_contents($logFile, json_encode(array_slice($logs, -50), JSON_PRETTY_PRINT));

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'has_update' => $hasUpdate,
        'current_version' => $telemetry['versao_local'],
        'latest_version' => $manifest['version'],
        'notes' => $manifest['notes'] ?? '',
        'manifest' => $manifest
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $telemetry['motivo_falha'] = $e->getMessage();
    
    $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    $logs[] = $telemetry;
    file_put_contents($logFile, json_encode(array_slice($logs, -50), JSON_PRETTY_PRINT));

    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
}
