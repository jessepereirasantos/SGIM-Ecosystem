<?php
/**
 * SGIM System Bootstrap
 */
session_start();

// Configuração Central do Master (Onde o SGIM busca atualizações e notificações)
// Todos os seus clientes apontarão para este domínio automaticamente.
define('SGIM_MASTER_URL', 'https://escolateologicaeloha.com.br');

/**
 * Função Universal de Migração Defensiva (SGIM v5.1 - Standalone)
 * Definida aqui para garantir disponibilidade durante atualizações OTA.
 */
if (!function_exists('ensureColumnExists')) {
    function ensureColumnExists($pdo, $table, $column, $definition) {
        try {
            if (!($pdo instanceof PDO)) return false;
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
                return true;
            }
        } catch (Exception $e) {
            error_log("Erro de Migração ($table.$column): " . $e->getMessage());
        }
        return false;
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/autoload.php';

// Auto-Patching Database
try {
    // Configurações do Tema
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes_tema (
        id INT AUTO_INCREMENT PRIMARY KEY,
        logo_url VARCHAR(255) DEFAULT NULL,
        cor_brand VARCHAR(20) DEFAULT '#FFC107',
        cor_brand_dark VARCHAR(20) DEFAULT '#D4AF37',
        cor_brand_light VARCHAR(20) DEFAULT '#FFD54F',
        darkbg VARCHAR(20) DEFAULT '#050505',
        darkcard VARCHAR(20) DEFAULT '#121212',
        darkborder VARCHAR(20) DEFAULT '#1E1E1E',
        lightbg VARCHAR(20) DEFAULT '#F3F4F6',
        lightcard VARCHAR(20) DEFAULT '#FFFFFF',
        lightborder VARCHAR(20) DEFAULT '#E5E7EB',
        modo_padrao ENUM('dark', 'light') DEFAULT 'dark'
    )");

    // Tabela de Novidades (Garantir colunas)
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_novidades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(100) NOT NULL,
        descricao TEXT NOT NULL,
        icone VARCHAR(50) DEFAULT 'rocket_launch',
        badge VARCHAR(50) DEFAULT 'OTA',
        data_lancamento DATE,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        visto TINYINT DEFAULT 0
    )");

    // Tabela de Novidades (Garantir colunas via Migração Defensiva)
    ensureColumnExists($pdo, 'sistema_novidades', 'badge', "VARCHAR(50) DEFAULT 'OTA' AFTER icone");
    ensureColumnExists($pdo, 'sistema_novidades', 'data_criacao', "DATETIME DEFAULT CURRENT_TIMESTAMP AFTER data_lancamento");

    // Tabela de Auditoria (Fase 3)
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_auditoria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT,
        tabela VARCHAR(100),
        id_referencia INT,
        acao VARCHAR(50),
        dados_antigos TEXT,
        dados_novos TEXT,
        ip VARCHAR(45),
        data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Soft Delete (Fase 3) - Adicionar colunas via Migração Defensiva
    $tabelas_patch = ['membros', 'financeiro_transacoes', 'departamentos', 'cargos'];
    foreach ($tabelas_patch as $tabela) {
        ensureColumnExists($pdo, $tabela, 'deleted_at', "TIMESTAMP NULL");
    }

    // Configurações Globais e OTA (SaaS v3.0)
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(50) NOT NULL UNIQUE,
        valor TEXT,
        grupo VARCHAR(50) DEFAULT 'geral'
    )");

    // Patch para coluna 'grupo' se a tabela já existia (Migração Defensiva)
    ensureColumnExists($pdo, 'configuracoes', 'grupo', "VARCHAR(50) DEFAULT 'geral'");

    // Inserir configurações padrão se não existirem
    $default_configs = [
        'versao_sistema' => '1.1.0',
        'master_url'     => 'https://vendas.sgim.com.br/',
        'license_key'    => ''
    ];

    foreach ($default_configs as $chave => $valor) {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO configuracoes (chave, valor, grupo) VALUES (?, ?, 'sistema')")->execute([$chave, $valor]);
        }
    }

} catch (Exception $e) {
    error_log("Bootstrap Patching Error: " . $e->getMessage());
}
