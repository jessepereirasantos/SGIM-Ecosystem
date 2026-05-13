<?php
/**
 * SGIM CLIENT - API OTA DOWNLOAD v1.1.41 (BULLETPROOF EDITION)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'DOWNLOAD FATAL ERROR: ' . $error['message'] . ' in ' . $error['file']
        ]);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Inclusões Manuais (Bypass Autoloader)
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    
    // Carregamento forçado dos componentes OTA
    $sysDir = __DIR__ . '/../includes/system/';
    require_once $sysDir . 'ActivationDriverInterface.php';
    require_once $sysDir . 'OtaOrchestrator.php';
    require_once $sysDir . 'OtaDownloadEngine.php';
    require_once $sysDir . 'OtaExtractionEngine.php';
    require_once $sysDir . 'OtaMigrationEngine.php';
    require_once $sysDir . 'OtaCapabilityManager.php';
    require_once $sysDir . 'OtaManifestValidator.php';
    require_once $sysDir . 'OtaBackupEngine.php';
    require_once $sysDir . 'ProtectedPathsPolicy.php';
    require_once $sysDir . 'drivers/SharedHostingDriver.php';

    // 2. Execução
    $masterUrl = 'https://escolateologicaeloha.com.br';
    if (isset($pdo)) {
        $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url' LIMIT 1");
        $urlDb = $stmtMaster ? $stmtMaster->fetchColumn() : null;
        if ($urlDb && filter_var($urlDb, FILTER_VALIDATE_URL)) {
            $masterUrl = rtrim($urlDb, '/');
        }
    }

    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . '/../', $masterUrl);
    $res = $orchestrator->updateLifecycle();
    
    if ($res === 'READY_FOR_COMMIT') {
        echo json_encode(['status' => 'success', 'message' => 'Pacote preparado e extraído. Pronto para instalar.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Orquestrador: ' . $res]);
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'EXCEPTION: ' . $e->getMessage()]);
}
