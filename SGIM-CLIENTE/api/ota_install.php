<?php
/**
 * SGIM CLIENT - API OTA INSTALL v1.1.41 (SMART DETECTION)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'INSTALL FATAL: ' . $error['message']]);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    
    $sysDir = __DIR__ . '/../includes/system/';
    require_once $sysDir . 'ActivationDriverInterface.php';
    require_once $sysDir . 'OtaOrchestrator.php';
    require_once $sysDir . 'drivers/SharedHostingDriver.php';

    // 1. Detecção Inteligente da Versão Baixada
    $versaoAlvo = null;
    
    // Tenta pelo estado atual
    $statePath = __DIR__ . '/../shared/system/state/current_state.json';
    if (file_exists($statePath)) {
        $state = json_decode(file_get_contents($statePath), true);
        $versaoAlvo = $state['discovery']['last_manifest']['version'] ?? null;
    }

    // Se não encontrou no estado, varre a pasta releases
    if (!$versaoAlvo) {
        $releases = array_diff(scandir(__DIR__ . '/../releases/'), ['.', '..', 'base']);
        rsort($releases); // Pega a maior/mais recente
        if (!empty($releases)) {
            $versaoAlvo = str_replace('v', '', $releases[0]);
        }
    }

    if (!$versaoAlvo) {
        throw new Exception("Não foi possível determinar a versão para instalação. Pasta releases vazia.");
    }

    $masterUrl = 'https://escolateologicaeloha.com.br';
    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . '/../', $masterUrl);
    
    if ($orchestrator->commitUpdate($versaoAlvo)) {
        echo json_encode(['status' => 'success', 'message' => 'Sistema atualizado para v' . $versaoAlvo, 'version' => $versaoAlvo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha no commit da v' . $versaoAlvo . '. Verifique activation.log']);
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'INSTALL EXCEPTION: ' . $e->getMessage()]);
}
