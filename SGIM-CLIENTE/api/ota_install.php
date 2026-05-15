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

    // 1. Identificação Determinística da Versão
    $stateFile = $basePath . 'shared/system/state/current_state.json';
    $versaoAlvo = null;

    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true);
        $versaoAlvo = $state['extraction']['version'] ?? null;
    }

    // Fallback: Tenta descobrir a versão se o state falhar
    if (!$versaoAlvo) {
        $releases = glob($basePath . 'releases/v*', GLOB_ONLYDIR);
        if (!empty($releases)) {
            usort($releases, 'version_compare');
            $versaoAlvo = str_replace('v', '', basename(end($releases)));
        }
    }

    if (!$versaoAlvo) {
        throw new Exception("ESTADO INVÁLIDO: Não foi possível determinar a versão alvo para instalação.");
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
        $logFile = $basePath . 'shared/system/logs/activation.log';
        $lastLog = file_exists($logFile) ? trim(implode(' | ', array_slice(file($logFile), -3))) : 'Log interno indisponível';
        echo json_encode(['status' => 'error', 'message' => 'ERRO ATÔMICO: ' . $lastLog]);
    }

} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'INSTALL EXCEPTION: ' . $e->getMessage()]);
}
