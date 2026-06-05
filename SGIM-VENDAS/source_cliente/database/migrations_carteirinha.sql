-- SGIM MIGRATION - CARTEIRINHA EDITOR CANVA
-- Criação das tabelas necessárias para armazenar templates de carteirinhas e vínculos com cargos

CREATE TABLE IF NOT EXISTS carteirinha_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    fundo_url VARCHAR(255) DEFAULT NULL,
    logo_url VARCHAR(255) DEFAULT NULL,
    assinatura_url VARCHAR(255) DEFAULT NULL,
    elementos_json LONGTEXT NOT NULL,
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carteirinha_cargos (
    template_id INT NOT NULL,
    cargo_id INT NOT NULL,
    PRIMARY KEY (template_id, cargo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
