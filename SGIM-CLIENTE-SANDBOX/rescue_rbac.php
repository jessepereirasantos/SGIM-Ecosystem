<?php
/**
 * SGIM RESCUE RBAC - Restaurador de Hierarquia Ministerial v2.0
 * Este script injeta a matriz de permissões GRANULAR e garante acesso total ao Admin.
 */
require_once 'config/database.php';

echo "<h2>🛡️ SGIM Rescue RBAC: Iniciando Expansão Ministerial...</h2>";

try {
    // 1. GARANTE QUE O CARGO MASTER EXISTE
    $pdo->exec("INSERT IGNORE INTO cargos (id, nome, escopo, status) VALUES (1, 'Admin Total', 'global', 'Ativo')");
    echo "✅ Cargo Mestre (ID 1) validado.<br>";

    // 2. MATRIZ GRANULAR DE PERMISSÕES
    $modulos = [
        'membros'       => ['visualizar', 'criar', 'editar', 'excluir', 'relatorio'],
        'financeiro'    => ['visualizar', 'lancar', 'estornar', 'relatorio'],
        'usuarios'      => ['visualizar', 'gerenciar', 'logs'],
        'congregacoes'  => ['visualizar', 'gerenciar'],
        'departamentos' => ['visualizar', 'gerenciar'],
        'eventos'       => ['visualizar', 'gerenciar'],
        'comunicacao'   => ['visualizar', 'enviar'],
        'configuracoes' => ['visualizar', 'sistema', 'backup'],
        'atualizacoes'  => ['visualizar', 'executar']
    ];

    $count = 0;
    $stmt = $pdo->prepare("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES (?, ?, ?)");
    
    foreach ($modulos as $modulo => $acoes) {
        foreach ($acoes as $acao) {
            $descricao = ucfirst($acao) . " em " . ucfirst($modulo);
            $stmt->execute([$modulo, $acao, $descricao]);
            $count++;
        }
    }
    echo "✅ Matriz Granular Injetada ($count permissões).<br>";

    // 3. VINCULA TODAS AS PERMISSÕES AO CARGO 1 (ADMIN TOTAL)
    $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
                SELECT 1, id FROM permissoes");
    echo "✅ Cargo Admin Total vinculado a 100% da matriz.<br>";

    // 4. GARANTE QUE O USUÁRIO ID 1 TENHA O CARGO 1
    $pdo->exec("UPDATE usuarios SET cargo_id = 1 WHERE id = 1");
    echo "✅ Usuário Mestre (ID 1) promovido a Admin Total.<br>";

    echo "<br><h3 style='color:green;'>🚀 SISTEMA ATIVADO: O RBAC agora é funcional e granular.</h3>";
    echo "<p>Agora todos os módulos aparecerão na sidebar e a matriz em 'Novo Cargo' estará completa.</p>";
    echo "<a href='dashboard.php'>ACESSAR PAINEL ADMINISTRATIVO</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ ERRO NA EXPANSÃO: " . $e->getMessage() . "</h3>";
}
