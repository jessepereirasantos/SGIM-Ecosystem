<?php
require_once 'config/db.php';

$stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
$licenseKey = $stmtLic->fetchColumn() ?: 'NÃO ENCONTRADA';

echo "<h3>Diagnóstico do Cliente</h3>";
echo "Sua Chave de Licença Local: <b>$licenseKey</b><br><br>";

echo "Tentando conectar ao Master...<br>";
$url = "https://escolateologicaeloha.com.br/api/update/v2/check.php?license_key=" . urlencode($licenseKey) . "&version=1.0.0&t=" . time();

$res = file_get_contents($url);
echo "Resposta do Master: <pre>" . htmlspecialchars($res) . "</pre>";
