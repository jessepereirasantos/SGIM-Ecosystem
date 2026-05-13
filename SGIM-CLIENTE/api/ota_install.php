<?php
/**
 * SGIM CLIENT - API OTA INSTALL v1.1.41 (DEBUG EDITION)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error', 
            'message' => 'INSTALL FATAL ERROR: ' . $error['message']
        ]);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    require_once __DIR__ . '/../includes/system/OtaOrchestrator.php';

    // 1. Detectar Versão Alvo
    $manifestPath = __DIR__ . '/../manifest.json';
    $versaoAlvo = '1.1.41';
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (isset($manifest['version'])) $versaoAlvo = $manifest['version'];
    }

    // 2. Usar Orquestrador para o Commit (Instalação Real)
    $masterUrl = 'https://escolateologicaeloha.com.br';
    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . '/../', $masterUrl);
    
    if ($orchestrator->commitUpdate($versaoAlvo)) {
        echo json_encode(['status' => 'success', 'message' => 'Sistema atualizado para v' . $versaoAlvo, 'version' => $versaoAlvo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha no commit da versão v' . $versaoAlvo . '. Verifique activation.log']);
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'INSTALL EXCEPTION: ' . $e->getMessage()]);
}
