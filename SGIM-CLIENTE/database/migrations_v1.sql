-- SGIM MIGRATION v2.1 - RBAC AUTO-RESCUE
-- Esta versão garante que as colunas sejam criadas e que o Admin Principal recupere o acesso.

-- 1. Tabelas de Mapeamento (Garantia)
CREATE TABLE IF NOT EXISTS permissoes (id INT AUTO_INCREMENT PRIMARY KEY, modulo VARCHAR(50) NOT NULL, acao VARCHAR(50) NOT NULL, descricao VARCHAR(255), UNIQUE KEY (modulo, acao)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cargo_permissoes (cargo_id INT NOT NULL, permissao_id INT NOT NULL, PRIMARY KEY (cargo_id, permissao_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Criação das Colunas (Modo Direto - Se falhar porque já existe, o Try-Catch do OTA ignora)
ALTER TABLE usuarios ADD COLUMN congregacao_id INT DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN cargo_id INT DEFAULT NULL;
ALTER TABLE cargos ADD COLUMN escopo ENUM('global', 'local') DEFAULT 'local';

-- 3. Seed de Permissões e Cargos Base (Garantia)
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
('membros', 'visualizar', 'Ver lista de membros'), ('financeiro', 'visualizar', 'Ver dashboard'), 
('congregacoes', 'gerenciar', 'Gerenciar Igrejas'), ('usuarios', 'gerenciar', 'Gerenciar Acessos');

INSERT IGNORE INTO cargos (id, nome, escopo, status) VALUES (1, 'Admin Total', 'global', 'Ativo');

-- 4. Vínculo de Permissões para o Admin Total (ID 1)
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) SELECT 1, id FROM permissoes;

-- 5. 🚨 A MÁGICA: AUTO-VÍNCULO DO ADMINISTRADOR ANTIGO
-- Todo usuário que era 'admin' no sistema antigo agora recebe o cargo de 'Admin Total' automaticamente.
UPDATE usuarios SET cargo_id = 1 WHERE nivel_acesso = 'admin' AND (cargo_id IS NULL OR cargo_id = 0);
