<?php
require_once 'config/database.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tabelas no banco:\n";
print_r($tables);

foreach ($tables as $table) {
    echo "\nColunas em $table:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM $table");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
