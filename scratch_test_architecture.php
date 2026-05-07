<?php
/**
 * SCRATCH - SIMULADOR DE INTEGRIDADE DE ARQUITETURA SGIM
 * Este script valida se o fluxo de Deploy -> Instalação está consistente.
 */

$master_path = __DIR__ . '/SGIM-VENDAS';
$client_path = __DIR__ . '/SGIM-CLIENTE';
$test_install_path = __DIR__ . '/SGIM-TEST-INSTALL';

echo "--- INICIANDO VALIDAÇÃO DE ARQUITETURA ---\n";

// 1. Simular Publicação v1.4.5 no Master
echo "[1] Simulando Publicação v1.4.5...\n";
$_POST['versao'] = '1.4.5';
$_POST['novidades'] = "Correção Crítica de Arquitetura\nFonte Única de Verdade\nBanco de Dados Unificado";
include $master_path . '/api/publish_update.php';

// 2. Verificar se o version.json da fonte mudou
$v_json = json_decode(file_get_contents($client_path . '/version.json'), true);
echo "[2] Versão na pasta FONTE (SGIM-CLIENTE): " . $v_json['version'] . "\n";

// 3. Verificar se o SGIM-CLIENTE.zip foi atualizado
if (file_exists($master_path . '/SGIM-CLIENTE.zip')) {
    $zip_time = date('H:i:s', filemtime($master_path . '/SGIM-CLIENTE.zip'));
    echo "[3] ZIP de Instalação (SGIM-CLIENTE.zip) atualizado às: $zip_time\n";
} else {
    echo "[ERRO] ZIP de instalação não foi gerado!\n";
}

// 4. Simular Extração (Nova Instalação)
echo "[4] Simulando Extração em pasta de teste...\n";
@mkdir($test_install_path, 0755, true);
$zip = new ZipArchive();
if ($zip->open($master_path . '/SGIM-CLIENTE.zip') === TRUE) {
    $zip->extractTo($test_install_path);
    $zip->close();
    echo "    Extração concluída.\n";
}

// 5. Validar Versão Instalada
$v_inst = json_decode(file_get_contents($test_install_path . '/version.json'), true);
echo "[5] Versão no Sistema Instalado: " . $v_inst['version'] . "\n";

// 6. Validar Estrutura de Banco (Simulando Dashboard)
echo "[6] Validando se o código instalado possui a correção do banco...\n";
$dashboard_content = file_get_contents($test_install_path . '/dashboard.php');
if (strpos($dashboard_content, 'ALTER TABLE transacoes ADD COLUMN IF NOT EXISTS data_vencimento') !== false) {
    echo "    OK: Dashboard.php contém a migration de segurança.\n";
} else {
    echo "    ERRO: Dashboard.php NÃO contém a correção de banco!\n";
}

if ($v_inst['version'] === '1.4.5') {
    echo "\n--- ✅ ARQUITETURA VALIDADA E UNIFICADA! ---\n";
} else {
    echo "\n--- ❌ FALHA NA CONSISTÊNCIA DE VERSÃO! ---\n";
}
