<?php
require_once 'config/database.php';
echo "<h1>Audit de Licenças</h1>";
$stmt = $pdo->query("SELECT * FROM licencas LIMIT 10");
$rows = $stmt->fetchAll();
echo "<pre>"; print_r($rows); echo "</pre>";

$stmt = $pdo->query("SELECT * FROM clientes LIMIT 10");
$rows = $stmt->fetchAll();
echo "<h1>Audit de Clientes</h1>";
echo "<pre>"; print_r($rows); echo "</pre>";

$stmt = $pdo->query("SHOW COLUMNS FROM licencas");
echo "<h1>Colunas Licencas</h1>";
echo "<pre>"; print_r($stmt->fetchAll()); echo "</pre>";
?>
