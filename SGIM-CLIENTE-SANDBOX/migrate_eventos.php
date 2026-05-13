<?php
require 'config/db.php';
try {
    // 1. Unificar nomes de colunas (Se existirem nomes antigos)
    // Tenta renomear 'nome' para 'titulo' se 'titulo' não existir
    $checkTitulo = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'titulo'");
    if ($checkTitulo->rowCount() == 0) {
        $pdo->exec("ALTER TABLE eventos CHANGE nome titulo VARCHAR(255) NOT NULL");
    }

    // Tenta renomear 'data_evento' para 'data_inicio' se 'data_inicio' não existir
    $checkDataInicio = $pdo->query("SHOW COLUMNS FROM eventos LIKE 'data_inicio'");
    if ($checkDataInicio->rowCount() == 0) {
        $pdo->exec("ALTER TABLE eventos CHANGE data_evento data_inicio DATETIME NOT NULL");
    }

    // 2. Adicionar colunas faltantes
    $pdo->exec("ALTER TABLE eventos ADD COLUMN IF NOT EXISTS data_fim DATETIME NULL");
    $pdo->exec("ALTER TABLE eventos ADD COLUMN IF NOT EXISTS banner_url VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE eventos ADD COLUMN IF NOT EXISTS publico BOOLEAN DEFAULT 0");
    $pdo->exec("ALTER TABLE eventos ADD COLUMN IF NOT EXISTS status ENUM('Agendado', 'Em Andamento', 'Concluído', 'Cancelado') DEFAULT 'Agendado'");

    echo "Database migrated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
