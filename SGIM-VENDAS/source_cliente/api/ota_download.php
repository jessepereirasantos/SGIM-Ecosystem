<?php
/**
 * SGIM CLIENT - API OTA DOWNLOAD v1.1.42 (LIGHTWEIGHT)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ignore_user_abort(true); // Continua mesmo se o navegador fechar
set_time_limit(600);    // 10 minutos

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'FATAL DOWNLOAD ERROR: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit;
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    
    // Deixamos o OtaOrchestrator cuidar de suas próprias inclusões
    require_once __DIR__ . '/../includes/system/OtaOrchestrator.php';

    $masterUrl = 'https://escolateologicaeloha.com.br';
    if (isset($pdo)) {
        $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url' LIMIT 1");
        if ($url = $stmtMaster->fetchColumn()) $masterUrl = rtrim($url, '/');
    }

    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . '/../', $masterUrl);
    
    // Executa apenas o Ciclo de Preparação (Download + Extração)
    $res = $orchestrator->updateLifecycle();
    
    echo json_encode([
        'status' => ($res === 'READY_FOR_COMMIT' ? 'success' : 'error'),
        'message' => $res
    ]);

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
