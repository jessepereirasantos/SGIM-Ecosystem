<?php
/**
 * API: Processamento de Atualização SaaS - SGIM v4.3
 * Executa Backup -> Download -> Instalação via AJAX (Definitivo)
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/Updater/UpdaterCore.php';
require_once __DIR__ . '/../../includes/BackupService.php';

use App\Updater\UpdaterCore;

// Compatibilidade: cliente usa user_id, admin usa usuario_id
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) && empty($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Não autorizado. Faça login novamente.']);
    exit;
}

$acao = $_POST['acao'] ?? '';

if ($acao !== 'executar_update') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

// Configurações de Resiliência
set_time_limit(600);
ini_set('memory_limit', '512M');
ignore_user_abort(true);

try {
    // 1. Carregar configurações do banco (fonte de verdade)
    $stmtCfg = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('master_url', 'license_key', 'versao_sistema')");
    $cfgRows  = $stmtCfg->fetchAll(PDO::FETCH_KEY_PAIR);

    $masterUrl      = $cfgRows['master_url']     ?? 'https://escolateologicaeloha.com.br/';
    $licenseKey     = $cfgRows['license_key']    ?? '';
    $currentVersion = $cfgRows['versao_sistema'] ?? '1.1.0';

    if (empty($licenseKey)) {
        throw new Exception("Chave de licença não configurada. Acesse Configurações para inserí-la.");
    }

    // 2. Verificar se há atualização disponível (usa sessão ou consulta o master)
    $updater = new UpdaterCore($pdo, $licenseKey, $currentVersion);
    $updater->setApiUrl($masterUrl);

    $ota = $_SESSION['ota_available'] ?? null;
    if (!$ota || !isset($ota['has_update'])) {
        $ota = $updater->checkForUpdate();
    }

    if (!isset($ota['has_update']) || !$ota['has_update']) {
        $msg = $ota['message'] ?? 'Nenhuma atualização pendente.';
        throw new Exception($msg);
    }

    // 3. Criar Backup de Segurança
    $backupDir = __DIR__ . '/../../backups';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

    $backupService = new BackupService($pdo, $backupDir);
    $backup_file = $backupService->createDatabaseBackup();

    if (!$backup_file) {
        // Backup falhou mas não interrompe (pode ser que não haja permissão de escrita)
        error_log("OTA Warning: Não foi possível criar backup antes da atualização.");
    }

    // 4. Executar Atualização
    $res = $updater->update(
        $ota['latest_version'],
        $ota['update_url'],
        $ota['checksum'] ?? '',
        $ota['migrations'] ?? []
    );

    if ($res) {
        unset($_SESSION['ota_available'], $_SESSION['last_ota_check']);
        echo json_encode([
            'success' => true,
            'message' => "✅ Sistema atualizado com sucesso para v" . $ota['latest_version'] . "!",
            'version' => $ota['latest_version']
        ]);
    } else {
        throw new Exception("O motor de atualização retornou falha inesperada.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "🛑 " . $e->getMessage()]);
}
