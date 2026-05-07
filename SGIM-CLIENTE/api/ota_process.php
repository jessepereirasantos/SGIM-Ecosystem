<?php
/**
 * SGIM CLIENT - CONTROLADOR OTA V4.2 (PRODUÇÃO)
 * Correções: parâmetro 'version' correto, URL de download autenticada
 */
header('Content-Type: application/json');

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

    // 3. CONSULTAR MASTER (parâmetro correto: 'version')
    $domain     = $_SERVER['HTTP_HOST'] ?? '';
    $master_url = "https://escolateologicaeloha.com.br/api/update/v2/check.php"
                . "?version=" . urlencode($current_v)
                . "&license_key=" . urlencode($licenseKey)
                . "&domain=" . urlencode($domain);

    $master_json = @file_get_contents($master_url);
    error_log("[SGIM-OTA] [OTA EXEC] Resposta API Master: $master_json");

    $master_data = json_decode($master_json, true);

    if (!$master_data || !isset($master_data['has_update']) || !$master_data['has_update']) {
        echo json_encode(['success' => true, 'updated' => false, 'message' => 'Sistema já está na versão mais recente.']);
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
