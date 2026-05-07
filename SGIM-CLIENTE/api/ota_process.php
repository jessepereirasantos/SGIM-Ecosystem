<?php
/**
 * SGIM CLIENT - CONTROLADOR OTA V4.3
 * BUILD: 2026-05-07-REV-CURL-A
 * Correção: file_get_contents → cURL (fix HostGator allow_url_fopen)
 */
header('Content-Type: application/json');

// RASTREABILIDADE OBRIGATÓRIA DE BUILD
// Este log prova que o servidor está executando ESTE arquivo, desta revisão.
error_log('[SGIM-BUILD] OTA_PROCESS BUILD 2026-05-07-REV-CURL-A | arquivo ativo e executando');

// 1. CARREGAR CONFIGS DO CLIENTE
if (!file_exists('../config/db.php')) {
    die(json_encode(['success' => false, 'message' => 'Arquivo config/db.php não encontrado.']));
}
require_once '../config/db.php';
require_once '../src/Updater/UpdaterCore.php';

use App\Updater\UpdaterCore;

// 2. PEGAR VERSÃO LOCAL E LICENÇA
try {
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
    $stmt->execute();
    $current_v = $stmt->fetchColumn() ?: '1.1.0';

    $stmtLic = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
    $stmtLic->execute();
    $licenseKey = $stmtLic->fetchColumn() ?: '';

    // 3. CONSULTAR MASTER via cURL (evita bloqueio de allow_url_fopen na HostGator)
    $domain     = $_SERVER['HTTP_HOST'] ?? '';
    $master_url = "https://escolateologicaeloha.com.br/api/update/v2/check.php"
                . "?version=" . urlencode($current_v)
                . "&license_key=" . urlencode($licenseKey)
                . "&domain=" . urlencode($domain)
                . "&t=" . time();

    $ch = curl_init($master_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    $master_json = curl_exec($ch);
    $curl_errno  = curl_errno($ch);
    $curl_error  = curl_error($ch);
    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // LOG OBRIGATÓRIO: Toda resposta ou falha deve ser registrada
    error_log("[SGIM-OTA] [OTA EXEC] HTTP: $http_code | cURL errno: $curl_errno | Resposta: $master_json");

    if ($curl_errno > 0) {
        error_log("[SGIM-OTA] [OTA EXEC] Falha cURL: $curl_error");
        echo json_encode(['success' => false, 'message' => "Falha de conexão com Master: $curl_error"]);
        exit;
    }

    $master_data = json_decode($master_json, true);

    if (!$master_data || !isset($master_data['has_update'])) {
        error_log("[SGIM-OTA] [OTA EXEC] Resposta inválida do Master. JSON bruto: $master_json");
        echo json_encode(['success' => false, 'message' => 'Resposta inválida do servidor Master.']);
        exit;
    }

    if (!$master_data['has_update']) {
        error_log("[SGIM-OTA] [OTA EXEC] Sem atualização. current={$master_data['current']} latest={$master_data['latest']}");
        echo json_encode(['success' => true, 'updated' => false, 'message' => 'Sistema já está na versão mais recente.', 'current' => $master_data['current'], 'latest' => $master_data['latest']]);
        exit;
    }

    // 4. MONTAR URL DE DOWNLOAD AUTENTICADA (license_key + domain obrigatórios)
    $download_url = "https://escolateologicaeloha.com.br/api/update/v2/download.php"
                  . "?version=" . urlencode($master_data['latest'])
                  . "&license_key=" . urlencode($licenseKey)
                  . "&domain=" . urlencode($domain);

    error_log("[SGIM-OTA] [OTA EXEC] Iniciando download de v" . $master_data['latest'] . " → " . $download_url);

    // 5. EXECUTAR MOTOR
    $updater = new UpdaterCore($pdo, $licenseKey, $current_v);
    $updater->setApiUrl("https://escolateologicaeloha.com.br/");

    $success = $updater->update($master_data['latest'], $download_url, $master_data['hash']);

    error_log("[SGIM-OTA] [OTA EXEC] Fim do processo. Sucesso: " . ($success ? 'Sim' : 'Não'));

    echo json_encode(['success' => $success, 'updated' => true, 'version' => $master_data['latest']]);

} catch (Exception $e) {
    error_log("[SGIM-OTA] [OTA EXEC] Exceção: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no Motor: ' . $e->getMessage()]);
}
