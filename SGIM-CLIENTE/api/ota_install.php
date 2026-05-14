<?php
/**
 * SGIM CLIENT - API OTA INSTALL v1.1.42 (DIAGNOSTIC EDITION)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'INSTALL CRITICAL: ' . $error['message']]);
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../src/autoload.php';
    
    $sysDir = __DIR__ . '/../includes/system/';
    $basePath = realpath(__DIR__ . '/../') . '/';

    // Inclusões Manuais de Segurança
    require_once $sysDir . 'ActivationDriverInterface.php';
    require_once $sysDir . 'OtaOrchestrator.php';
    require_once $sysDir . 'drivers/SharedHostingDriver.php';

    // 1. Identificação Determinística da Versão (Fim da heurística de rsort)
    $stateFile = $basePath . 'shared/system/state/current_state.json';
    if (!file_exists($stateFile)) {
        throw new Exception("ESTADO CORROMPIDO: Arquivo de estado transacional ausente. O pipeline atômico exige um download validado prévio.");
    }
    
    $state = json_decode(file_get_contents($stateFile), true);
    $versaoAlvo = $state['extraction']['version'] ?? null;

    if (!$versaoAlvo) {
        throw new Exception("ESTADO INVÁLIDO: Versão alvo não encontrada no registro de transação.");
    }

    // 2. Validação de existência física antes de chamar o orquestrador
    $releasesDir = $basePath . 'releases/';
    $versionPath = $releasesDir . 'v' . $versaoAlvo . '/';
    if (!is_dir($versionPath)) {
        throw new Exception("Pasta v$versaoAlvo não encontrada em: $versionPath");
    }

    $masterUrl = 'https://escolateologicaeloha.com.br';
    $orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, $basePath, $masterUrl);
    
    if ($orchestrator->commitUpdate($versaoAlvo)) {
        echo json_encode(['status' => 'success', 'message' => 'Sistema atualizado para v' . $versaoAlvo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha no commit da v' . $versaoAlvo . '. Tente rodar o ota_diagnostic.php']);
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'INSTALL EXCEPTION: ' . $e->getMessage()]);
}
