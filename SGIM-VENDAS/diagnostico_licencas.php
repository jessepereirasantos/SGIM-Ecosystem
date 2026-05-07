<?php
require_once 'config/database.php';

echo "<h3>Diagnóstico de Licenças (Master)</h3>";
try {
    $stmt = $pdo->query("SELECT id, chave_licenca, status, cliente_id FROM licencas LIMIT 20");
    $licencas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Chave</th><th>Status</th><th>Cliente</th></tr>";
    foreach($licencas as $l) {
        echo "<tr>";
        echo "<td>{$l['id']}</td>";
        echo "<td>{$l['chave_licenca']}</td>";
        echo "<td><b>{$l['status']}</b></td>";
        echo "<td>{$l['cliente_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
