<?php
/**
 * SGIM CLIENT - API OTA DOWNLOAD v1.1.41 (DEBUG EDITION)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não imprimir na tela para não quebrar o JSON

// Captura de Erros Fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'PHP FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    require_once __DIR__ . '/../includes/system/OtaOrchestrator.php';

    // Busca Master URL do banco
    $masterUrl = 'https://escolateologicaeloha.com.br';
    if (isset($pdo)) {
        $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url' LIMIT 1");
        $urlDb = $stmtMaster ? $stmtMaster->fetchColumn() : null;
        if ($urlDb && filter_var($urlDb, FILTER_VALIDATE_URL)) {
            $masterUrl = rtrim($urlDb, '/');
        }
    }

    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . '/../', $masterUrl);
    
    // O Ciclo Integrado faz Download + Extração + Migração
    $res = $orchestrator->updateLifecycle();
    
    if ($res === 'READY_FOR_COMMIT') {
        echo json_encode(['status' => 'success', 'message' => 'Pacote preparado (v' . $res . ')']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Orquestrador retornou: ' . $res]);
    }

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
}
