-- SGIM VENDAS - Database Schema
-- Removido CREATE DATABASE pois em hospedagem compartilhada deve-se criar pelo painel

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    telefone VARCHAR(20),
    documento VARCHAR(20), -- CPF ou CNPJ
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS licencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    pedido_id INT DEFAULT NULL,
    chave_licenca VARCHAR(64) NOT NULL UNIQUE,
    dominio VARCHAR(255) NOT NULL,
    api_token VARCHAR(128) NOT NULL UNIQUE,
    status ENUM('ativa', 'revogada', 'suspensa', 'pendente') DEFAULT 'pendente',
    data_expiracao DATE DEFAULT NULL,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME DEFAULT NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    valor_desconto DECIMAL(10,2) DEFAULT 0.00,
    payment_id VARCHAR(100) UNIQUE,
    status VARCHAR(50) DEFAULT 'PENDENTE',
    data_venda DATETIME DEFAULT CURRENT_TIMESTAMP,
    email_comprador VARCHAR(255),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT,
    cliente_id INT NOT NULL,
    cliente_nome VARCHAR(255),
    valor DECIMAL(10,2) NOT NULL,
    payment_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'PENDENTE',
    data_venda DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    mercadopago_id VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'PENDENTE',
    qr_code TEXT,
    qr_code_base64 LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    license_key VARCHAR(64) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
