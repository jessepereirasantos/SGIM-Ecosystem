-- SGIM Migração v1.4.5 - Correção de Permissões e Escopo de Usuários
-- Data: 2026-06-08

-- 1. Garante que as permissões de gestão de usuários existam
INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES
    ('usuarios', 'visualizar', 'Visualizar Usuários'),
    ('usuarios', 'gerenciar', 'Gerenciar Usuários');

-- 2. Garante que o Admin Total (cargo_id = 1) tenha TODAS as permissões de usuários
INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id)
    SELECT 1, id FROM permissoes WHERE modulo = 'usuarios';

-- 3. Garante que os cargos GLOBAIS existentes tenham suas permissões de visualização
-- (Atualiza o cargo Admin Total para nivel_hierarquico = 99 como referência de topo)
UPDATE cargos SET nivel_hierarquico = 99 WHERE id = 1 AND nome = 'Admin Total';

-- 4. Atualiza a versão do sistema
UPDATE configuracoes SET valor = '1.4.5' WHERE chave = 'versao_sistema';
