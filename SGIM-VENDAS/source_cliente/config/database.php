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
if (!function_exists('ensureColumnExists')) {
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
}

// Auto-Provisionamento Silencioso (SGIM v1.1.99)
if ($pdo instanceof PDO) {
    try {
        // 1. Tabela sistema_novidades (Resgate completo se não existir)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_novidades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            badge VARCHAR(50) DEFAULT NULL,
            icone VARCHAR(50) DEFAULT 'rocket_launch',
            titulo VARCHAR(255) NOT NULL,
            descricao TEXT NOT NULL,
            visto TINYINT(1) DEFAULT 0,
            data_lancamento DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        ensureColumnExists($pdo, 'sistema_novidades', 'icone', "VARCHAR(50) DEFAULT 'rocket_launch' AFTER badge");
        ensureColumnExists($pdo, 'sistema_novidades', 'visto', "TINYINT(1) DEFAULT 0 AFTER descricao");
    } catch (Throwable $e) {
        error_log("Erro ao provisionar sistema_novidades: " . $e->getMessage());
    }

    try {
        // 2. Tabela membros (Carteirinhas v1.1.99)
        ensureColumnExists($pdo, 'membros', 'hash_carteirinha', "VARCHAR(64) DEFAULT NULL AFTER status");
        ensureColumnExists($pdo, 'membros', 'carteirinha_valida_ate', "DATE DEFAULT NULL AFTER hash_carteirinha");

        // Criar índice único idx_hash_carteirinha
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'sqlite') {
            $checkIdx = $pdo->query("SHOW INDEX FROM membros WHERE Key_name = 'idx_hash_carteirinha'");
            if ($checkIdx && $checkIdx->rowCount() == 0) {
                $pdo->exec("CREATE UNIQUE INDEX idx_hash_carteirinha ON membros (hash_carteirinha)");
            }
        }
    } catch (Throwable $e) {
        error_log("Erro ao provisionar colunas de membros: " . $e->getMessage());
    }

    try {
        // 3. Tabela financeiro_transacoes (Financeiro Completo)
        $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro_transacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('entrada', 'saida') NOT NULL,
            categoria VARCHAR(100) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            data_transacao DATE NOT NULL,
            descricao TEXT,
            membro_id INT DEFAULT NULL,
            congregacao_id INT DEFAULT NULL,
            nome_identificado VARCHAR(255) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        ensureColumnExists($pdo, 'financeiro_transacoes', 'congregacao_id', "INT DEFAULT NULL AFTER membro_id");
        ensureColumnExists($pdo, 'financeiro_transacoes', 'deleted_at', "DATETIME DEFAULT NULL AFTER nome_identificado");
    } catch (Throwable $e) {
        error_log("Erro ao provisionar financeiro_transacoes: " . $e->getMessage());
    }

    try {
        // 4. Catálogo de Permissões Adicionais (RBAC Completo)
        $stmtIns = $pdo->prepare("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES (?, ?, ?)");
        $permissions = [
            ['carteirinhas', 'visualizar', 'Ver listagem de carteirinhas'],
            ['carteirinhas', 'gerenciar', 'Criar, editar e renovar carteirinhas'],
            ['departamentos', 'visualizar', 'Ver departamentos e cargos'],
            ['departamentos', 'gerenciar', 'Gerenciar departamentos e cargos'],
            ['eventos', 'visualizar', 'Ver eventos e cultos'],
            ['comunicacao', 'visualizar', 'Ver histórico de comunicações'],
            ['congregacoes', 'visualizar', 'Ver congregações'],
            ['configuracoes', 'visualizar', 'Ver configurações do sistema']
        ];
        foreach ($permissions as $p) {
            $stmtIns->execute($p);
        }

        // Mapear todas para o Admin Total (cargo_id = 1)
        $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id)
                    SELECT 1, id FROM permissoes;");
    } catch (Throwable $e) {
        error_log("Erro ao provisionar permissoes: " . $e->getMessage());
    }
}

// Flags globais para o sistema
$is_configured = ($pdo instanceof PDO);
$is_installed_local = $is_configured; // Assume instalado se estiver configurado
?>
