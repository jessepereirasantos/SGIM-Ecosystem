<?php
/**
 * SGIM RESCUE RBAC - Restaurador de Hierarquia Ministerial
 * Este script garante que as tabelas de permissões existam e estejam povoadas.
 */
require_once 'config/database.php';

echo "<h2>🛡️ SGIM Rescue RBAC: Iniciando Auditoria Ministerial...</h2>";

try {
    // 1. GARANTE QUE O CARGO MASTER EXISTE
    $pdo->exec("INSERT IGNORE INTO cargos (id, nome, escopo, status) VALUES (1, 'Admin Total', 'global', 'Ativo')");
    echo "✅ Cargo Mestre Validado.<br>";

    // 2. LISTA MESTRA DE PERMISSÕES (Módulos e Ações)
    $permissoes = [
        ['modulo' => 'membros', 'acao' => 'visualizar', 'descricao' => 'Ver lista de membros'],
        ['modulo' => 'membros', 'acao' => 'gerenciar', 'descricao' => 'Criar, editar e excluir membros'],
        ['modulo' => 'financeiro', 'acao' => 'visualizar', 'descricao' => 'Ver dashboard financeira'],
        ['modulo' => 'financeiro', 'acao' => 'gerenciar', 'descricao' => 'Lançar dízimos e despesas'],
        ['modulo' => 'usuarios', 'acao' => 'visualizar', 'descricao' => 'Ver gestão de usuários'],
        ['modulo' => 'usuarios', 'acao' => 'gerenciar', 'descricao' => 'Criar cargos e permissões'],
        ['modulo' => 'congregacoes', 'acao' => 'visualizar', 'descricao' => 'Ver congregações'],
        ['modulo' => 'congregacoes', 'acao' => 'gerenciar', 'descricao' => 'Cadastrar e editar igrejas'],
        ['modulo' => 'departamentos', 'acao' => 'visualizar', 'descricao' => 'Ver departamentos'],
        ['modulo' => 'eventos', 'acao' => 'visualizar', 'descricao' => 'Ver agenda de eventos'],
        ['modulo' => 'comunicacao', 'acao' => 'visualizar', 'descricao' => 'Acesso ao WhatsApp/E-mail'],
        ['modulo' => 'configuracoes', 'acao' => 'visualizar', 'descricao' => 'Configurações do sistema'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES (?, ?, ?)");
    foreach ($permissoes as $p) {
        $stmt->execute([$p['modulo'], $p['acao'], $p['descricao']]);
    }
    echo "✅ Matriz de Permissões Injetada.<br>";

    // 3. VINCULA TODAS AS PERMISSÕES AO CARGO 1 (ADMIN TOTAL)
    $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
                SELECT 1, id FROM permissoes");
    echo "✅ Cargo Mestre Vinculado à Matriz Total.<br>";

    // 4. GARANTE QUE O USUÁRIO ATUAL (OU O ID 1) TENHA O CARGO 1
    // Se estiver logado, usa a sessão, senão tenta o ID 1
    session_start();
    $userId = $_SESSION['user_id'] ?? 1;
    $stmtUser = $pdo->prepare("UPDATE usuarios SET cargo_id = 1 WHERE id = ?");
    $stmtUser->execute([$userId]);
    echo "✅ Usuário Admin vinculado ao Cargo Mestre.<br>";

    echo "<br><h3 style='color:green;'>🚀 MISSÃO CONCLUÍDA: A lógica de níveis foi ATIVADA.</h3>";
    echo "<p>Agora, ao acessar o menu 'Gestão de Usuários' ou o Sidebar, você verá todas as opções de hierarquia.</p>";
    echo "<a href='dashboard.php'>VOLTAR PARA O PAINEL</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ ERRO NA AUDITORIA: " . $e->getMessage() . "</h3>";
}
