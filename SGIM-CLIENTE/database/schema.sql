-- SGIM-CLIENTE SQL Schema (Compatível com phpMyAdmin cPanel)
-- Importe este arquivo DIRETAMENTE no seu banco de dados vazio criado no cPanel.

CREATE TABLE IF NOT EXISTS configuracoes (
    chave VARCHAR(50) PRIMARY KEY,
    valor TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel_acesso ENUM('admin', 'gerente', 'usuario') DEFAULT 'usuario',
    congregacao_id INT DEFAULT NULL,
    cargo_id INT DEFAULT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    two_factor_secret VARCHAR(100) DEFAULT NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    genero ENUM('M', 'F', 'Outro'),
    data_nascimento DATE,
    estado_civil ENUM('Solteiro', 'Casado', 'Divorciado', 'Viúvo', 'Outro'),
    cpf VARCHAR(20),
    rg VARCHAR(20),
    cep VARCHAR(10),
    endereco VARCHAR(255),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    data_batismo DATE,
    data_conversao DATE,
    cargo_id INT,
    congregacao_id INT,
    foto VARCHAR(255),
    status ENUM('Ativo', 'Inativo', 'Em Disciplina', 'Transferido', 'Falecido') DEFAULT 'Ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS congregacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    sigla VARCHAR(20),
    icone VARCHAR(50) DEFAULT 'church',
    cnpj VARCHAR(20),
    telefone VARCHAR(20),
    email VARCHAR(255),
    cep VARCHAR(10),
    endereco VARCHAR(255),
    numero VARCHAR(20),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    dirigente_id INT,
    data_fundacao DATE,
    status ENUM('Ativa', 'Inativa') DEFAULT 'Ativa',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    icone VARCHAR(50) DEFAULT 'groups',
    descricao TEXT,
    lider_id INT,
    congregacao_id INT,
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    nivel_hierarquico INT DEFAULT 1,
    escopo ENUM('global', 'local') DEFAULT 'local',
    departamento_id INT,
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Novas Tabelas de Hierarquia e Acessos (RBAC)
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    descricao VARCHAR(255),
    UNIQUE KEY (modulo, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cargo_permissoes (
    cargo_id INT NOT NULL,
    permissao_id INT NOT NULL,
    PRIMARY KEY (cargo_id, permissao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('receita', 'despesa') NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    descricao VARCHAR(255),
    membro_id INT,
    congregacao_id INT,
    status ENUM('pago', 'pendente', 'cancelado') DEFAULT 'pendente',
    comprovante_url VARCHAR(255),
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    local VARCHAR(255),
    congregacao_id INT,
    departamento_id INT,
    tipo ENUM('Culto', 'Reunião', 'Festa', 'Congresso', 'Outro') DEFAULT 'Culto',
    status ENUM('Agendado', 'Em Andamento', 'Concluído', 'Cancelado') DEFAULT 'Agendado',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comunicacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('email', 'sms', 'whatsapp', 'aviso_painel') NOT NULL,
    assunto VARCHAR(255),
    mensagem TEXT NOT NULL,
    remetente_id INT,
    destinatarios_filtro JSON,  -- Guarda as regras do filtro (ex: Todos de X cargo)
    status ENUM('rascunho', 'agendado', 'enviado', 'falha') DEFAULT 'rascunho',
    data_agendamento DATETIME,
    data_envio DATETIME,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Constraints de FKs removidas para evitar erros de integridade em instalações parciais
-- As chaves estrangeiras agora são tratadas via lógica PHP (Soft-Constraint)

-- 3. Seed de Dados Iniciais RBAC
INSERT IGNORE INTO cargos (id, nome, escopo, status) VALUES (1, 'Admin Total', 'global', 'Ativo');
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('usuarios', 'visualizar', 'Gestão de Acessos'), 
('membros', 'visualizar', 'Ver Membros'), 
('financeiro', 'visualizar', 'Ver Financeiro');
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) SELECT 1, id FROM permissoes;

-- 4. Seed de Configurações do Sistema (CRÍTICO: define a versão instalada)
INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('versao_sistema', '1.1.75');
INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('sistema_nome', 'SGIM');
INSERT IGNORE INTO configuracoes (chave, valor) VALUES ('tema', 'dark');
