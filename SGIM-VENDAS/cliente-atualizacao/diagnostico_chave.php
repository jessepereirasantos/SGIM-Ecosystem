<?php
require_once 'config/db.php';

// Captura Automática de Conexão
if (!isset($pdo) || $pdo === null) {
    foreach (get_defined_vars() as $var) {
        if ($var instanceof PDO) { $pdo = $var; break; }
    }
}

try {
    if (!$pdo) throw new Exception("Sem conexão.");

    // Busca a chave REAL que o sistema está usando
    $stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
    $chaveNoBanco = $stmtLic->fetchColumn() ?: 'NÃO ENCONTRADA';

    $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
    $masterUrl = $stmtMaster->fetchColumn() ?: 'PADRÃO';

    echo "<h1>Diagnóstico de Identidade do Cliente</h1>";
    echo "<ul>";
    echo "<li><strong>Chave no Banco:</strong> <code>" . htmlspecialchars($chaveNoBanco) . "</code></li>";
    echo "<li><strong>Master URL:</strong> <code>" . htmlspecialchars($masterUrl) . "</code></li>";
    echo "<li><strong>Domínio Detectado:</strong> <code>" . $_SERVER['HTTP_HOST'] . "</code></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
