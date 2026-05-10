<?php
require_once 'config/database.php';

echo "<h2>🔧 Diagnóstico e Correção de Banco (OTA)</h2>";

try {
    // 1. Verificar colunas na tabela sistema_novidades
    $columns = $pdo->query("DESCRIBE sistema_novidades")->fetchAll(PDO::FETCH_COLUMN);
    
    $required = [
        'icone' => "ALTER TABLE sistema_novidades ADD COLUMN icone VARCHAR(50) DEFAULT 'rocket_launch' AFTER badge",
        'visto' => "ALTER TABLE sistema_novidades ADD COLUMN visto TINYINT(1) DEFAULT 0 AFTER descricao"
    ];

    foreach ($required as $col => $sql) {
        if (!in_array($col, $columns)) {
            $pdo->exec($sql);
            echo "✅ Coluna <b>$col</b> adicionada.<br>";
        } else {
            echo "ℹ️ Coluna <b>$col</b> já existe.<br>";
        }
    }

    echo "<br><b>✅ Banco de dados sincronizado com a nova Timeline!</b>";
    echo "<br><p>Agora publique uma nova versão no Master para testar.</p>";

} catch (Exception $e) {
    echo "❌ Erro ao processar: " . $e->getMessage();
}
