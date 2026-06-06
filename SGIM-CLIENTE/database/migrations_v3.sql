-- SGIM MIGRATION v3.0 - CARTEIRINHAS DINÂMICAS E RBAC EXPANDIDO
-- Compatível com MySQL 5.5 (HostGator)

-- 1. Expansão da tabela de membros para Controle de Validade e QR Code Dinâmico
SET @col_hash = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'membros' AND COLUMN_NAME = 'hash_carteirinha' AND TABLE_SCHEMA = DATABASE());
SET @sql_hash = IF(@col_hash = 0, 'ALTER TABLE membros ADD COLUMN hash_carteirinha VARCHAR(64) DEFAULT NULL AFTER status', 'SELECT 1');
PREPARE stmt1 FROM @sql_hash; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

SET @col_valida = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'membros' AND COLUMN_NAME = 'carteirinha_valida_ate' AND TABLE_SCHEMA = DATABASE());
SET @sql_valida = IF(@col_valida = 0, 'ALTER TABLE membros ADD COLUMN carteirinha_valida_ate DATE DEFAULT NULL AFTER hash_carteirinha', 'SELECT 1');
PREPARE stmt2 FROM @sql_valida; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Adicionar índice único para busca rápida e segurança do hash de validação
SET @idx_hash = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = 'membros' AND INDEX_NAME = 'idx_hash_carteirinha' AND TABLE_SCHEMA = DATABASE());
SET @sql_idx = IF(@idx_hash = 0, 'CREATE UNIQUE INDEX idx_hash_carteirinha ON membros (hash_carteirinha)', 'SELECT 1');
PREPARE stmt3 FROM @sql_idx; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 2. Seed de Catálogo de Permissões Adicionais (RBAC Completo)
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('carteirinhas', 'visualizar', 'Ver listagem de carteirinhas'),
('carteirinhas', 'gerenciar', 'Criar, editar e renovar carteirinhas'),
('departamentos', 'visualizar', 'Ver departamentos e cargos'),
('departamentos', 'gerenciar', 'Gerenciar departamentos e cargos'),
('eventos', 'visualizar', 'Ver eventos e cultos'),
('comunicacao', 'visualizar', 'Ver histórico de comunicações'),
('congregacoes', 'visualizar', 'Ver congregações'),
('configuracoes', 'visualizar', 'Ver configurações do sistema');

-- 3. Criação e Ajustes da tabela de transações do financeiro
CREATE TABLE IF NOT EXISTS financeiro_transacoes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_cong = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'financeiro_transacoes' AND COLUMN_NAME = 'congregacao_id' AND TABLE_SCHEMA = DATABASE());
SET @sql_cong = IF(@col_cong = 0, 'ALTER TABLE financeiro_transacoes ADD COLUMN congregacao_id INT DEFAULT NULL AFTER membro_id', 'SELECT 1');
PREPARE stmt4 FROM @sql_cong; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

SET @col_del = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'financeiro_transacoes' AND COLUMN_NAME = 'deleted_at' AND TABLE_SCHEMA = DATABASE());
SET @sql_del = IF(@col_del = 0, 'ALTER TABLE financeiro_transacoes ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER nome_identificado', 'SELECT 1');
PREPARE stmt5 FROM @sql_del; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

-- 4. Mapear automaticamente as novas permissões para o Admin Total (cargo_id = 1)
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id)
SELECT 1, id FROM permissoes;

-- 5. Atualiza a versão do sistema no banco de dados
UPDATE configuracoes SET valor = '1.1.94' WHERE chave = 'versao_sistema';
