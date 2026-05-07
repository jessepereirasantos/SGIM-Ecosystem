<?php
/**
 * Test: Simular Publicação de Versão
 */
$_POST['versao'] = '1.8.8';
$_POST['novidades'] = "Teste de Novidade 1\nTeste de Novidade 2";
$_POST['melhorias'] = "Melhoria de Performance";
$_POST['correcoes'] = "Correção de Bug Crítico";
$_POST['notificar_email'] = '0'; // Não testar e-mail aqui

// Mock session
session_start();
$_SESSION['admin_logged'] = true;

echo "Iniciando teste de publicação v1.8.8...\n";

require_once 'publish_update.php';

echo "\nPublicação concluída.\n";

// Verificar arquivos
$clientVersionFile = __DIR__ . '/../../SGIM-CLIENTE/version.json';
$vendasVersionFile = __DIR__ . '/../downloads/version.json';

echo "\n--- Resultados ---\n";
if (file_exists($clientVersionFile)) {
    echo "SGIM-CLIENTE version.json: " . file_get_contents($clientVersionFile) . "\n";
} else {
    echo "ERRO: SGIM-CLIENTE version.json não encontrado!\n";
}

if (file_exists($vendasVersionFile)) {
    echo "SGIM-VENDAS downloads/version.json: " . file_get_contents($vendasVersionFile) . "\n";
} else {
    echo "ERRO: SGIM-VENDAS downloads/version.json não encontrado!\n";
}
?>
