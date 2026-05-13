<?php
/**
 * SGIM - PERITO DE AUDITORIA FÍSICA v1.1.36
 * Verifica se a atualização foi PROMOVIDA para a raiz operacional.
 */

header('Content-Type: application/json; charset=utf-8');

$raiz = realpath(__DIR__) . DIRECTORY_SEPARATOR;
$versao_alvo = "1.1.36";
$log_file = $raiz . 'shared' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'installer.log';

$auditoria = [
    'timestamp' => date('c'),
    'analise_estrutural' => [
        'pasta_versao_detectada' => is_dir($raiz . $versao_alvo),
        'raiz_operacional' => $raiz
    ],
    'validacao_física' => [
        'header_atualizado' => false,
        'cargo_novo_atualizado' => false,
        'ota_motor_atualizado' => false
    ],
    'blindagem_dados' => [
        'db_config_preservado' => file_exists($raiz . 'config/db_config.php'),
        'uploads_preservado' => is_dir($raiz . 'uploads')
    ],
    'log_forense' => [
        'arquivo_log_existe' => file_exists($log_file),
        'ultimas_linhas' => []
    ]
];

// 1. Verifica Overwrite Real no Header
if (file_exists($raiz . 'includes/header.php')) {
    $header_content = file_get_contents($raiz . 'includes/header.php');
    if (strpos($header_content, "TESTE-SGIM-v1.1.36") !== false) {
        $auditoria['validacao_física']['header_atualizado'] = true;
    }
}

// 2. Verifica Overwrite no Motor OTA
if (file_exists($raiz . 'api/ota_install.php')) {
    $ota_content = file_get_contents($raiz . 'api/ota_install.php');
    if (strpos($ota_content, "SMART FLATTEN") !== false) {
        $auditoria['validacao_física']['ota_motor_atualizado'] = true;
    }
}

// 3. Captura o rastro no Log
if (file_exists($log_file)) {
    $lines = file($log_file);
    $auditoria['log_forense']['ultimas_linhas'] = array_slice($lines, -10);
}

// VEREDITO FINAL
$sucesso = (
    !$auditoria['analise_estrutural']['pasta_versao_detectada'] && 
    $auditoria['validacao_física']['header_atualizado'] &&
    $auditoria['validacao_física']['ota_motor_atualizado']
);

$auditoria['veredito'] = $sucesso ? "✅ SUCESSO: Arquivos promovidos para a raiz operacional." : "❌ FALHA: Arquivos permanecem encapsulados ou não atualizados.";

echo json_encode($auditoria, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
