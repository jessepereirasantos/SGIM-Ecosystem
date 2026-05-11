<?php
/**
 * SGIM - Motor OTA v5.0 (Reconstructed)
 * Endpoint local consultado pelo frontend do cliente.
 */
error_reporting(0); 
ini_set('display_errors', 0);

// RASTREADOR DE PULSO INICIAL (Garante log antes de qualquer crash)
file_put_contents(__DIR__ . '/ota_pulse.log', "[" . date('c') . "] Polling Recebido do Frontend!\n", FILE_APPEND);

require_once 'src/autoload.php';
require_once 'config/database.php'; 
require_once 'includes/system/OtaOrchestrator.php';

if (!isset($pdo) || $pdo === null) {
    foreach (get_defined_vars() as $var) {
        if ($var instanceof PDO) { $pdo = $var; break; }
    }
}

function fetchRemoteJson($url) {
    $result = [
        'body'      => null,
        'http_code' => 0,
        'error'     => ''
    ];

    // Tentativa 1: file_get_contents
    $opts = [
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    if ($body !== false) {
        $result['body'] = $body;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $http_response_header[0], $m);
            $result['http_code'] = isset($m[1]) ? (int)$m[1] : 200;
        } else {
            $result['http_code'] = 200;
        }
        return $result;
    }

    // Tentativa 2: cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body !== false) {
            $result['body'] = $body;
            $result['http_code'] = (int)$info['http_code'];
            return $result;
        }
        $result['error'] = 'cURL Error: ' . $err;
    } else {
        $result['error'] = 'file_get_contents falhou e cURL não disponível.';
    }

    $lastErr = error_get_last();
    $result['error'] .= ' | PHP Stream Error: ' . ($lastErr['message'] ?? 'N/A');
    return $result;
}

$logFile = __DIR__ . '/ota_detection_log.json';
$telemetry = [
    'timestamp' => date('c'),
    'url_consultada' => '',
    'http_status' => 0,
    'erro_stream' => '',
    'json_bruto' => '',
    'versao_local' => '1.1.0',
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

    // Consulta real ao Master (com fallback cURL)
    $fetch = fetchRemoteJson($telemetry['url_consultada']);
    $telemetry['http_status'] = $fetch['http_code'];
    $telemetry['erro_stream'] = $fetch['error'];
    $telemetry['json_bruto'] = $fetch['body'] ? substr($fetch['body'], 0, 2000) : '';

    if (!$fetch['body']) {
        throw new Exception("Não foi possível alcançar o MASTER em " . $telemetry['url_consultada'] . " | Erro: " . $fetch['error']);
    }

    $json = $fetch['body'];
    $manifest = json_decode($json, true);
    
    if (!$manifest || !isset($manifest['version'])) {
        throw new Exception("Manifesto JSON inválido ou corrompido.");
    }

    $telemetry['versao_remota'] = $manifest['version'];
    
    // Comparação
    $hasUpdate = version_compare($manifest['version'], $telemetry['versao_local'], '>');
    $telemetry['resultado_comparacao'] = $hasUpdate;

    // Log de notificação quando há update
    if ($hasUpdate) {
        $notifLog = __DIR__ . '/ota_notification_log.json';
        $nlogs = file_exists($notifLog) ? json_decode(file_get_contents($notifLog), true) : [];
        $nlogs[] = [
            'timestamp'         => date('c'),
            'versao_detectada'  => $manifest['version'],
            'versao_local'      => $telemetry['versao_local'],
            'acao'              => 'update_detected',
            'url_consultada'    => $telemetry['url_consultada']
        ];
        file_put_contents($notifLog, json_encode(array_slice($nlogs, -50), JSON_PRETTY_PRINT));
    }

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
