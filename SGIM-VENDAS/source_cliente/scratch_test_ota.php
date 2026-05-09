<?php
/**
 * SCRIPT DE PROVA REAL - VALIDAÇÃO DE ROOT_PATH E ESCRITA
 */
require_once 'src/Updater/UpdaterCore.php';

// Mock do PDO para não precisar de banco real no teste de arquivos
class MockPDO extends PDO {
    public function __construct() {}
    public function prepare($statement, $driver_options = array()) { return new class { public function execute($p=[]) {} public function fetch() { return ['valor' => '1.1.0']; } }; }
}

try {
    $pdo = new MockPDO();
    $updater = new App\Updater\UpdaterCore($pdo, 'LICENSE-TEST', '1.1.0');
    
    echo "--- INICIANDO TESTE DE MOTOR ---\n";
    
    // O motor vai ler a raiz via realpath(__DIR__ . '/../../') dentro de src/Updater/
    // Como estamos na raiz executando este script, o UpdaterCore vai subir 2 níveis e chegar na raiz do SGIM-CLIENTE
    
    $extractPath = __DIR__ . '/backups/temp_update/files/';
    $appRoot = realpath(__DIR__ . '/');
    
    echo "[LOG SIMULADO] Tentando aplicar de $extractPath para $appRoot\n\n";
    
    // Acessar o método privado via Reflection para teste isolado de escrita
    $reflection = new ReflectionClass($updater);
    $method = $reflection->getMethod('safeSwap');
    $method->setAccessible(true);
    
    // EXECUÇÃO DO SWAP
    $method->invoke($updater, $extractPath, $appRoot);
    
    // CRIAR ARQUIVO DE PROVA (Simulando o passo 5 do update())
    $proofFile = $appRoot . '/teste_ota.txt';
    $proofContent = "OTA EXECUÇÃO REAL PROVA | DATA: " . date('d/m/Y H:i:s') . " | RAIZ: " . $appRoot;
    file_put_contents($proofFile, $proofContent);
    
    echo "\n--- FIM DA EXECUÇÃO ---\n";
    echo "Verifique os arquivos criados na sua pasta local.\n";

} catch (Exception $e) {
    echo "ERRO NO MOTOR: " . $e->getMessage() . "\n";
}
