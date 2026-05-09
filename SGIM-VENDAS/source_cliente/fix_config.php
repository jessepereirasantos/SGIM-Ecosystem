<?php
/**
 * SGIM - Corretor de Configuração (Master URL)
 */
require_once 'config/db.php';

if (!isset($pdo) || $pdo === null) {
    foreach (get_defined_vars() as $var) {
        if ($var instanceof PDO) { $pdo = $var; break; }
    }
}

$newMasterUrl = 'https://escolateologicaeloha.com.br';

try {
    if (!$pdo) throw new Exception("Sem conexão.");

    $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'master_url'");
    $stmt->execute([$newMasterUrl]);

    echo "<h1>Sucesso!</h1>";
    echo "<p>A URL do Master foi atualizada para: <code>$newMasterUrl</code></p>";
    echo "<p>Agora você pode rodar o <strong>ota.php</strong> para testar a atualização.</p>";

} catch (Exception $e) {
    echo "<h1>Erro</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
