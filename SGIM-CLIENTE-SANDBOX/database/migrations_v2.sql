-- SGIM MIGRATION v2.0 - RBAC SYSTEM (Identity & Access)
-- Esta migration expande a estrutura de usuários e cargos para suportar Hierarquia Ministerial.

-- 1. Tabela de Catálogo de Permissões (Módulos)
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL,    -- ex: 'financeiro', 'membros', 'secretaria'
    acao VARCHAR(50) NOT NULL,      -- ex: 'visualizar', 'editar', 'excluir'
    descricao VARCHAR(255),
    UNIQUE KEY (modulo, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de Vínculo: Cargo ⇄ Permissão (A Ponte de Acesso)
CREATE TABLE IF NOT EXISTS cargo_permissoes (
    cargo_id INT NOT NULL,
    permissao_id INT NOT NULL,
    PRIMARY KEY (cargo_id, permissao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Expansão da Tabela de Usuários (Vínculos de Identidade)
-- Nota: Usamos IF NOT EXISTS para colunas via lógica de verificação
SET @dropdown = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'congregacao_id' AND TABLE_SCHEMA = DATABASE());
SET @sql = IF(@dropdown = 0, 'ALTER TABLE usuarios ADD COLUMN congregacao_id INT DEFAULT NULL AFTER nivel_acesso', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dropdown2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cargo_id' AND TABLE_SCHEMA = DATABASE());
SET @sql2 = IF(@dropdown2 = 0, 'ALTER TABLE usuarios ADD COLUMN cargo_id INT DEFAULT NULL AFTER congregacao_id', 'SELECT 1');
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Expansão da Tabela de Cargos (Escopo de Visão)
SET @dropdown3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'cargos' AND COLUMN_NAME = 'escopo' AND TABLE_SCHEMA = DATABASE());
SET @sql3 = IF(@dropdown3 = 0, 'ALTER TABLE cargos ADD COLUMN escopo ENUM(\'global\', \'local\') DEFAULT \'local\' AFTER nivel_hierarquico', 'SELECT 1');
PREPARE stmt FROM @sql3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Seed de Permissões Básicas (Catálogo Inicial)
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('membros', 'visualizar', 'Ver lista de membros'),
('membros', 'cadastrar', 'Cadastrar novos membros'),
('membros', 'editar', 'Editar dados de membros'),
('membros', 'excluir', 'Excluir membros do sistema'),
('financeiro', 'visualizar', 'Ver dashboard e transações'),
('financeiro', 'cadastrar', 'Lançar dízimos e ofertas'),
('financeiro', 'editar', 'Alterar lançamentos'),
('financeiro', 'excluir', 'Remover registros financeiros'),
('congregacoes', 'gerenciar', 'Criar e editar congregações'),
('usuarios', 'gerenciar', 'Controlar usuários e permissões'),
('eventos', 'gerenciar', 'Criar e editar eventos e cultos'),
('comunicacao', 'gerenciar', 'Enviar WhatsApp e E-mails'),
('configuracoes', 'gerenciar', 'Alterar dados do sistema');

-- 6. Seed de Cargos Base (Matrizes Iniciais)
-- Criaremos os 6 níveis solicitados com IDs fixos para facilitar o mapeamento inicial (se possível)
-- Mas usaremos INSERT IGNORE para não quebrar IDs existentes.
INSERT IGNORE INTO cargos (id, nome, escopo, status) VALUES 
(1, 'Admin Total', 'global', 'Ativo'),
(2, 'Admin Secretaria', 'global', 'Ativo'),
(3, 'Admin Tesoureiro', 'global', 'Ativo'),
(4, 'Pastor Local', 'local', 'Ativo'),
(5, 'Secretário Local', 'local', 'Ativo'),
(6, 'Tesoureiro Local', 'local', 'Ativo');

-- 7. Mapeamento Automático de Permissões Iniciais (Presets)
-- Admin Total (ID 1) recebe tudo
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
SELECT 1, id FROM permissoes;

-- Admin Secretaria (ID 2) recebe tudo menos financeiro e configurações core
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
SELECT 2, id FROM permissoes WHERE modulo NOT IN ('financeiro', 'configuracoes');

-- Admin Tesoureiro (ID 3) recebe financeiro e relatórios
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
SELECT 3, id FROM permissoes WHERE modulo IN ('financeiro');

-- Pastor Local (ID 4) recebe tudo (dentro do seu escopo local)
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
SELECT 4, id FROM permissoes WHERE modulo NOT IN ('usuarios', 'configuracoes');

-- Secretário Local (ID 5)
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
SELECT 5, id FROM permissoes WHERE modulo IN ('membros', 'comunicacao', 'eventos');

-- Tesoureiro Local (ID 6)
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
SELECT 6, id FROM permissoes WHERE modulo IN ('financeiro');
