<?php
/**
 * SGIM MASTER - KILL CACHE & DIAGNOSTIC
 */
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🚀 Iniciando Limpeza Profunda de Cache...</h2>";

// 1. Limpar OPCache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✅ OPCache Resetado com Sucesso!<br>";
    } else {
        echo "❌ Falha ao resetar OPCache.<br>";
    }
} else {
    echo "ℹ️ OPCache não está habilitado ou disponível.<br>";
}

// 2. Limpar Cache de Status de Arquivo
clearstatcache(true);
echo "✅ Cache de Status de Arquivo (statcache) limpo!<br>";

// 3. Tocar arquivos principais para forçar re-interpretação
$files = ['index.php', 'dashboard.php', 'config/database.php', 'templates/header.php'];
foreach ($files as $f) {
    if (file_exists($f)) {
        if (touch($f)) {
            echo "✅ Arquivo '$f' re-validado (touch).<br>";
        }
    }
}

// 4. Teste de Conexão com Banco
echo "<h3>🔍 Teste de Conexão:</h3>";
try {
    require_once 'config/database.php';
    if (isset($pdo)) {
        echo "✅ Banco de Dados conectado OK!<br>";
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "📊 Tabelas encontradas: " . implode(', ', $tables) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro no teste de banco: " . $e->getMessage() . "<br>";
}

echo "<hr><p><b>Ação recomendada:</b> Feche seu navegador e abra novamente. Tente acessar <a href='login.php'>login.php</a> ou <a href='dashboard.php'>dashboard.php</a>.</p>";
