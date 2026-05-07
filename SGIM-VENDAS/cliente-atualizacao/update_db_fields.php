<?php
/**
 * SGIM - Utilitário de Migração de Banco de Dados v5.2
 * Garante que todas as tabelas e colunas necessárias existam.
 */
require_once __DIR__ . '/src/bootstrap.php';

try {
    echo "<h2>Iniciando Migração de Banco de Dados...</h2>";

    // 1. MEMBROS
    ensureColumnExists($pdo, 'membros', 'foto', "VARCHAR(255) DEFAULT NULL");
    ensureColumnExists($pdo, 'membros', 'data_batismo', "DATE DEFAULT NULL");
    ensureColumnExists($pdo, 'membros', 'data_conversao', "DATE DEFAULT NULL");
    
    // 2. CONGREGAÇÕES
    $pdo->exec("CREATE TABLE IF NOT EXISTS congregacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sigla VARCHAR(10),
        nome VARCHAR(255) NOT NULL,
        pastor VARCHAR(255),
        telefone VARCHAR(20),
        endereco TEXT,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    ensureColumnExists($pdo, 'congregacoes', 'sigla', "VARCHAR(10) DEFAULT NULL");

    // 3. DEPARTAMENTOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT,
        icone VARCHAR(50) DEFAULT 'group',
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    ensureColumnExists($pdo, 'departamentos', 'icone', "VARCHAR(50) DEFAULT 'group'");

    // 4. CARGOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS cargos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        nivel_acesso VARCHAR(50) DEFAULT 'Leitura',
        departamento_id INT,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    ensureColumnExists($pdo, 'cargos', 'nivel_acesso', "VARCHAR(50) DEFAULT 'Leitura'");
    ensureColumnExists($pdo, 'cargos', 'departamento_id', "INT DEFAULT NULL");

    // 5. FINANCEIRO
    $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro_transacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('entrada', 'saida') NOT NULL,
        categoria VARCHAR(100),
        descricao TEXT,
        valor DECIMAL(10,2) NOT NULL,
        data_transacao DATE NOT NULL,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. EVENTOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT,
        data_inicio DATETIME NOT NULL,
        local VARCHAR(255),
        status ENUM('Agendado', 'Em Andamento', 'Concluído', 'Cancelado') DEFAULT 'Agendado',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. COMUNICAÇÃO
    $pdo->exec("CREATE TABLE IF NOT EXISTS comunicacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assunto VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        canal ENUM('email', 'whatsapp') DEFAULT 'email',
        status ENUM('rascunho', 'enviado') DEFAULT 'enviado',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 8. CONFIGURAÇÕES
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(50) UNIQUE NOT NULL,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    echo "<p style='color: green; font-weight: bold;'>[SUCESSO] Banco de dados sincronizado e atualizado!</p>";
    echo "<a href='dashboard.php'>Voltar para o Painel</a>";

} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>[ERRO] Falha na migração: " . $e->getMessage() . "</p>";
}
