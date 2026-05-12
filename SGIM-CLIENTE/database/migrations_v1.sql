-- SGIM MIGRATION v2.2 - FINAL RESCUE
-- Garante a estrutura e o vínculo imediato do administrador.

-- 1. Estrutura (IF NOT EXISTS para segurança)
CREATE TABLE IF NOT EXISTS permissoes (id INT AUTO_INCREMENT PRIMARY KEY, modulo VARCHAR(50) NOT NULL, acao VARCHAR(50) NOT NULL, descricao VARCHAR(255), UNIQUE KEY (modulo, acao)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cargo_permissoes (cargo_id INT NOT NULL, permissao_id INT NOT NULL, PRIMARY KEY (cargo_id, permissao_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Colunas (Direto, o Try-Catch do instalador cuida se já existirem)
ALTER TABLE usuarios ADD COLUMN congregacao_id INT DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN cargo_id INT DEFAULT NULL;
ALTER TABLE cargos ADD COLUMN escopo ENUM('global', 'local') DEFAULT 'local';

-- 3. Preset de Segurança (Garantia de IDs)
INSERT IGNORE INTO cargos (id, nome, escopo, status) VALUES (1, 'Admin Total', 'global', 'Ativo');
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES ('usuarios', 'visualizar', 'Gestão de Acessos');
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) VALUES (1, (SELECT id FROM permissoes WHERE modulo = 'usuarios' LIMIT 1));

-- 4. 🚨 O GOLPE FINAL: VÍNCULO COMPULSÓRIO
-- Força o usuário ID 1 (você) a ter o cargo 1.
UPDATE usuarios SET cargo_id = 1 WHERE id = 1;
-- Garante para qualquer outro admin também.
UPDATE usuarios SET cargo_id = 1 WHERE nivel_acesso = 'admin' AND cargo_id IS NULL;
