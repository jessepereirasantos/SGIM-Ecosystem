<?php
/**
 * SGIM CLIENT - OTA POLLING v1.1.41
 */
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json; charset=utf-8');

function fetchRemoteJson($url) {
    $result = ['body' => null, 'http_code' => 0, 'error' => null];
    
    // Tentativa 1: file_get_contents
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: SGIM-Client-v1.1.41\r\n",
            "timeout" => 10
        ]
    ];
    $context = stream_context_create($opts);
    $body = @file_get_contents($url, false, $context);
    
    if ($body !== false) {
        $result['body'] = $body;
        $result['http_code'] = 200;
        return $result;
    }

    // Tentativa 2: cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SGIM-Client-v1.1.41');
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($body !== false) {
            $result['body'] = $body;
            $result['http_code'] = $httpCode;
            return $result;
        }
        $result['error'] = 'cURL Error: ' . $err;
    } else {
        $result['error'] = 'file_get_contents falhou e cURL não disponível.';
    }
    return $result;
}

$telemetry = [
    'timestamp' => date('c'),
    'versao_local' => '1.1.41',
    'versao_remota' => '',
    'has_update' => false
];

try {
    if (!$pdo) throw new Exception("Conexão falhou.");

    $stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
    $licenseKey = $stmtLic->fetchColumn();

    $stmtVersao = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
    $currentVersion = $stmtVersao->fetchColumn() ?: '1.1.41';
    $telemetry['versao_local'] = $currentVersion;

    // URL do Master (Ajuste se necessário)
    $masterUrl = 'https://escolateologicaeloha.com.br';
    $checkUrl = $masterUrl . "/api/update/check.php?license=" . urlencode($licenseKey) . "&v=" . urlencode($currentVersion);

    $fetch = fetchRemoteJson($checkUrl);
    
    if ($fetch['http_code'] === 200 && $fetch['body']) {
        $data = json_decode($fetch['body'], true);
        if (isset($data['status']) && $data['status'] === 'success') {
            $telemetry['has_update'] = $data['has_update'];
            $telemetry['latest_version'] = $data['latest_version'];
            $telemetry['notes'] = $data['notes'];
            
            echo json_encode([
                'status' => 'success',
                'has_update' => $data['has_update'],
                'latest_version' => $data['latest_version'],
                'notes' => $data['notes']
            ]);
            exit;
        }
    }
    
    echo json_encode(['status' => 'success', 'has_update' => false]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
