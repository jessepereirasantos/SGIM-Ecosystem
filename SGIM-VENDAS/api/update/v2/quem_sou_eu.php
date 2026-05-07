<?php
echo "<h1>📍 Localização Real no Servidor</h1>";
echo "<b>Caminho Absoluto:</b> " . __DIR__ . "<br>";
echo "<b>URL Acessada:</b> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<b>Data/Hora no Servidor:</b> " . date('Y-m-d H:i:s') . "<br>";
echo "<hr>";
echo "<h3>Teste de Conexão com o Banco:</h3>";
try {
    require_once __DIR__ . '/../../../config/database.php';
    echo "✅ Conexão com banco OK!";
} catch (Exception $e) {
    echo "❌ Erro ao conectar: " . $e->getMessage();
}
