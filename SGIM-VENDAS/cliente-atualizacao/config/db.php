<?php
// SGIM - Arquivo de Conexão Unificado v5.0 (Fonte de Verdade)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = null;

// Tenta localizar a configuração de banco de dados em locais padrão da HostGator
$possible_configs = [
    __DIR__ . '/db_config.php',
    dirname(__DIR__) . '/db_config.php',
    __DIR__ . '/../db_config.php'
];

foreach ($possible_configs as $config_path) {
    if (file_exists($config_path)) {
        @include $config_path;
        if (isset($pdo) && $pdo instanceof PDO) {
            break;
        }
    }
}

/**
 * Função Universal de Migração Defensiva (SGIM v5.0)
 * Garante compatibilidade com HostGator e servidores legados.
 */
function ensureColumnExists($pdo, $table, $column, $definition) {
    try {
        if (!($pdo instanceof PDO)) return false;
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
            return true;
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar/adicionar coluna $column na tabela $table: " . $e->getMessage());
    }
    return false;
}

// Flags globais para o sistema
$is_configured = ($pdo instanceof PDO);
