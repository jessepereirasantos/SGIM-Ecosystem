<?php
/**
 * SGIM MASTER - VERSION SYNC v1.1.41
 */
require_once __DIR__ . '/config/database.php';

echo "<h2>Sincronizando Master SGIM para v1.1.41...</h2>";

try {
    if (!$pdo) throw new Exception("Falha na conexão com o banco.");

    // Atualiza a versão do sistema no banco do Master
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', '1.1.41') ON DUPLICATE KEY UPDATE valor = '1.1.41'");
    $stmt->execute();

    echo "<p style='color: green; font-weight: bold;'>✅ SUCESSO: O Master agora está oficialmente na v1.1.41.</p>";
    echo "<p>Pode excluir este arquivo e atualizar seu Dashboard Master agora.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERRO: " . $e->getMessage() . "</p>";
}
