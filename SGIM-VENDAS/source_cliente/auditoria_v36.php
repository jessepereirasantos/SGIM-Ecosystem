<?php
/**
 * SGIM - DOSSIÊ FORENSE v1.1.36
 * Perícia física de integridade ministerial e promoção de arquivos.
 */

header('Content-Type: application/json; charset=utf-8');

$raiz = realpath(__DIR__) . DIRECTORY_SEPARATOR;
$versao_alvo = "1.1.36";
$log_file = $raiz . 'shared' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'installer.log';

$dossie = [
    'pericia_timestamp' => date('c'),
    'analise_geografica' => [
        'raiz_operacional' => $raiz,
        'wrapper_folder_detectada' => is_dir($raiz . $versao_alvo),
        'wrapper_path' => $raiz . $versao_alvo,
        'diagnostico_promocao' => ''
    ],
    'pericia_rbac_core' => [
        'AccessManager_presente' => file_exists($raiz . 'src/Auth/AccessManager.php'),
        'cargo_novo_presente' => file_exists($raiz . 'cargo_novo.php'),
        'usuarios_presente' => file_exists($raiz . 'usuarios.php'),
        'header_presente' => file_exists($raiz . 'includes/header.php')
    ],
    'validacao_conteudo_real' => [
        'aba_teste_v36_presente' => false,
        'rbac_matriz_v36_presente' => false,
        'hash_header' => null
    ],
    'blindagem_dados' => [
        'db_config_status' => file_exists($raiz . 'config/db_config.php') ? 'PRESERVADO' : '🚨 EXTRAVIADO',
        'uploads_status' => is_dir($raiz . 'uploads') ? 'PRESERVADO' : '🚨 EXTRAVIADO'
    ],
    'auditoria_log' => [
        'presenca_log' => file_exists($log_file),
        'evidencia_flatten' => false,
        'evidencia_overwrite' => false
    ]
];

// 1. Diagnóstico de Promoção
if ($dossie['analise_geografica']['wrapper_folder_detectada']) {
    $dossie['analise_geografica']['diagnostico_promocao'] = "❌ FALHA: Arquivos encapsulados em subpasta versionada.";
} else {
    $dossie['analise_geografica']['diagnostico_promocao'] = "✅ SUCESSO: Flatten realizado, arquivos promovidos para a raiz.";
}

// 2. Validação de Conteúdo Real (Header)
if ($dossie['pericia_rbac_core']['header_presente']) {
    $content = file_get_contents($raiz . 'includes/header.php');
    $dossie['validacao_conteudo_real']['hash_header'] = sha1($content);
    if (strpos($content, "TESTE-SGIM-v1.1.36") !== false) {
        $dossie['validacao_conteudo_real']['aba_teste_v36_presente'] = true;
    }
}

// 3. Validação de Conteúdo Real (Cargo Novo)
if ($dossie['pericia_rbac_core']['cargo_novo_presente']) {
    $content = file_get_contents($raiz . 'cargo_novo.php');
    if (strpos($content, "text-white") !== false) {
        $dossie['validacao_conteudo_real']['rbac_matriz_v36_presente'] = true;
    }
}

// 4. Busca por Evidências no Log
if ($dossie['auditoria_log']['presenca_log']) {
    $log_content = file_get_contents($log_file);
    if (strpos($log_content, "SMART FLATTEN ATIVADO") !== false) $dossie['auditoria_log']['evidencia_flatten'] = true;
    if (strpos($log_content, "PROMOTING:") !== false) $dossie['auditoria_log']['evidencia_overwrite'] = true;
}

// SENTENÇA FINAL
$aprovado = (
    !$dossie['analise_geografica']['wrapper_folder_detectada'] && 
    $dossie['validacao_conteudo_real']['aba_teste_v36_presente'] &&
    $dossie['validacao_conteudo_real']['rbac_matriz_v36_presente']
);

$dossie['veredito_final'] = $aprovado ? "🏆 HOMOLOGADO: Sistema atualizado fisicamente na raiz operacional." : "🚨 REJEITADO: Inconsistência física detectada.";

echo json_encode($dossie, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
