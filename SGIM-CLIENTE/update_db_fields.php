<?php
require_once 'config/db.php';

try {
    // Adicionando colunas novas na tabela membros
    $pdo->exec("ALTER TABLE membros ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE membros ADD COLUMN data_batismo DATE DEFAULT NULL");
    $pdo->exec("ALTER TABLE membros ADD COLUMN data_conversao DATE DEFAULT NULL");
    
    // Novas correções solicitadas: congregacoes (sigla) e departamentos (icone)
    try { $pdo->exec("ALTER TABLE congregacoes ADD COLUMN sigla VARCHAR(10) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE departamentos ADD COLUMN icone VARCHAR(50) DEFAULT 'group'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE cargos ADD COLUMN nivel_acesso VARCHAR(50) DEFAULT 'Leitura'"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE cargos ADD COLUMN departamento_id INT DEFAULT NULL"); } catch(Exception $e) {}
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS cargos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        nivel_acesso VARCHAR(50) DEFAULT 'Leitura',
        departamento_id INT,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS departamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT,
        icone VARCHAR(50) DEFAULT 'group',
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS congregacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sigla VARCHAR(10),
        nome VARCHAR(255) NOT NULL,
        pastor VARCHAR(255),
        telefone VARCHAR(20),
        endereco TEXT,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Novas tabelas faltantes para evitar tela branca em Financeiro, Eventos e Comunicação
    $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro_transacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM('entrada', 'saida') NOT NULL,
        categoria VARCHAR(100),
        descricao TEXT,
        valor DECIMAL(10,2) NOT NULL,
        data_transacao DATE NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT,
        data_evento DATETIME NOT NULL,
        local VARCHAR(255),
        status ENUM('pendente', 'confirmado', 'cancelado') DEFAULT 'confirmado',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comunicacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assunto VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        canal ENUM('email', 'whatsapp') DEFAULT 'email',
        status ENUM('rascunho', 'enviado') DEFAULT 'enviado',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Criando tabela de configurações se não existir (para garantir)
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(50) UNIQUE NOT NULL,
        valor TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    echo "Banco de dados atualizado com sucesso!";
} catch (Exception $e) {
    echo "Erro ao atualizar banco: " . $e->getMessage();
}
?>
