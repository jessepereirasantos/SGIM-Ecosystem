<?php
/**
 * SGIM CLIENT - API OTA DOWNLOAD v1.1.41 (ULTRA-SAFE)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300); // 5 minutos de tempo limite

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'DOWNLOAD CRITICAL: ' . $error['message']
        ]);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    
    $sysDir = __DIR__ . '/../includes/system/';
    
    // Função de inclusão segura (evita o erro "Already in Use")
    function safeRequire($path) {
        if (file_exists($path)) {
            require_once $path;
        }
    }

    safeRequire($sysDir . 'ActivationDriverInterface.php');
    safeRequire($sysDir . 'OtaOrchestrator.php');

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
        echo json_encode(['status' => 'success', 'message' => 'Pacote pronto para instalação.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Status do Orquestrador: ' . $res]);
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'EXCEPTION: ' . $e->getMessage()]);
}
