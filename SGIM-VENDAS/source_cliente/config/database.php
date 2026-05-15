<?php
// SGIM - Arquivo de Conexão Restaurado (Estabilidade Prioritária)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = null;

// Tenta localizar a configuração de banco de dados (Busca recursiva robusta)
$possible_configs = [
    __DIR__ . '/db_config.php',
    dirname(__DIR__) . '/db_config.php',
    dirname(dirname(__DIR__)) . '/db_config.php',
    dirname(dirname(dirname(__DIR__))) . '/db_config.php',
    __DIR__ . '/../db_config.php',
    '/home1/hg9a3205/public_html/sgim-iade/db_config.php' // Fallback absoluto para HostGator
];

foreach ($possible_configs as $config_path) {
    if (file_exists($config_path)) {
        @include $config_path;
        if (isset($pdo) && $pdo instanceof PDO) {
            break;
        }
    }
}

// Se falhar, tenta usar as variáveis de ambiente ou configurações padrão do SGIM
if (!$pdo) {
    // Nota: O sistema de setup deve criar o db_config.php. 
    // Se o sistema principal não conectar, verifique a existência do db_config.php na raiz.
}

/**
 * Função Universal de Migração Defensiva (SGIM v5.0)
 * Garante compatibilidade com HostGator e servidores legados.
 */
function ensureColumnExists($pdo, $table, $column, $definition) {
    try {
        if (!($pdo instanceof PDO)) return false;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'sqlite') {
            $check = $pdo->query("PRAGMA table_info(`$table`)");
            $columns = $check->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['name'] === $column) return true;
            }
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
        } else {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log("Erro de Migração: " . $e->getMessage());
        return false;
    }
}

// Flags globais para o sistema
$is_configured = ($pdo instanceof PDO);
$is_installed_local = $is_configured; // Assume instalado se estiver configurado
?>
