-- MIGRATION: ERP MINISTERIAL v1.0 (RBAC SYSTEM)
-- Esta migration prepara o banco para permissões modulares e escopo de congregação.

-- 1. Tabela de Permissões Disponíveis (Módulos)
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL,    -- ex: 'financeiro', 'membros'
    acao VARCHAR(50) NOT NULL,      -- ex: 'visualizar', 'editar', 'excluir'
    descricao VARCHAR(255),
    UNIQUE KEY (modulo, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de Vínculo: Cargo ⇄ Permissão (A Ponte Dinâmica)
CREATE TABLE IF NOT EXISTS cargo_permissoes (
    cargo_id INT NOT NULL,
    permissao_id INT NOT NULL,
    PRIMARY KEY (cargo_id, permissao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Evolução da Tabela de Usuários
-- Adiciona colunas de vínculo se não existirem
SET @dropdown = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'congregacao_id' AND TABLE_SCHEMA = DATABASE());
SET @sql = IF(@dropdown = 0, 'ALTER TABLE usuarios ADD COLUMN congregacao_id INT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dropdown2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cargo_id' AND TABLE_SCHEMA = DATABASE());
SET @sql2 = IF(@dropdown2 = 0, 'ALTER TABLE usuarios ADD COLUMN cargo_id INT DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Evolução da Tabela de Cargos (Para suportar nível hierárquico real)
SET @dropdown3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'cargos' AND COLUMN_NAME = 'escopo' AND TABLE_SCHEMA = DATABASE());
SET @sql3 = IF(@dropdown3 = 0, 'ALTER TABLE cargos ADD COLUMN escopo ENUM(\'global\', \'local\') DEFAULT \'local\'', 'SELECT 1');
PREPARE stmt FROM @sql3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Seed de Permissões Básicas (Garanta que os módulos existam)
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('membros', 'visualizar', 'Ver lista de membros'),
('membros', 'cadastrar', 'Cadastrar novos membros'),
('membros', 'editar', 'Editar dados de membros'),
('membros', 'excluir', 'Excluir membros do sistema'),
('financeiro', 'visualizar', 'Ver dashboard e transações'),
('financeiro', 'cadastrar', 'Lançar dízimos e ofertas'),
('financeiro', 'editar', 'Alterar lançamentos'),
('financeiro', 'excluir', 'Remover registros financeiros'),
('congregacoes', 'gerenciar', 'Criar e editar congregações (Sede Only)'),
('usuarios', 'gerenciar', 'Controlar usuários e permissões');
