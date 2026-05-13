<?php
/**
 * SGIM CLIENT - API OTA DOWNLOAD v1.1.41
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../includes/system/OtaOrchestrator.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado.']);
    exit;
}

try {
    // Busca Master URL do banco
    $masterUrl = 'https://escolateologicaeloha.com.br';
    $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url' LIMIT 1");
    $urlDb = $stmtMaster ? $stmtMaster->fetchColumn() : null;
    if ($urlDb && filter_var($urlDb, FILTER_VALIDATE_URL)) {
        $masterUrl = rtrim($urlDb, '/');
    }

    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . '/../', $masterUrl);
    
    // O Ciclo Integrado faz Download + Extração + Migração
    $res = $orchestrator->updateLifecycle();
    
    if ($res === 'READY_FOR_COMMIT') {
        echo json_encode(['status' => 'success', 'message' => 'Pacote baixado e preparado com sucesso.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha na preparação do pacote. Verifique orchestrator.log']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
