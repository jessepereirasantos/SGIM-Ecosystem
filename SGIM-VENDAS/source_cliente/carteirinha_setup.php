<?php
// AUTO-PONTE: Se existir uma versão mais nova ativa pelo OTA, desvia para ela
$bridge = __DIR__ . '/releases/current/' . basename(__FILE__);
if (file_exists($bridge) && strpos(__DIR__, 'releases') === false) {
    require_once $bridge;
    exit;
}

/**
 * Script de Configuração e Diagnóstico do Banco de Dados para Carteirinhas (v1.1.87)
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

// Proteção contra PDO nulo ou falhas de conexão
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

$page_title = 'Diagnóstico e Setup de Carteirinhas';
$current_page = 'carteirinhas';
require_once 'includes/header.php';

$relatorio = [];
$erro_critico = false;

// 1. Verificar conexão com o banco
if (!$pdo) {
    $relatorio[] = [
        'etapa' => 'Conexão com o Banco de Dados',
        'status' => 'FALHA',
        'detalhes' => 'Objeto PDO de conexão com o banco não está disponível.',
        'classe' => 'text-red-400 border-red-500/20 bg-red-500/10'
    ];
    $erro_critico = true;
} else {
    $relatorio[] = [
        'etapa' => 'Conexão com o Banco de Dados',
        'status' => 'SUCESSO',
        'detalhes' => 'Conexão ativa e autenticada com sucesso.',
        'classe' => 'text-green-400 border-green-500/20 bg-green-500/10'
    ];
}

// 2. Executar Criação das Tabelas
if (!$erro_critico) {
    try {
        // Tabela de templates
        $sqlTemplates = "CREATE TABLE IF NOT EXISTS carteirinha_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            fundo_url VARCHAR(255) DEFAULT NULL,
            logo_url VARCHAR(255) DEFAULT NULL,
            assinatura_url VARCHAR(255) DEFAULT NULL,
            elementos_json LONGTEXT NOT NULL,
            status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
            data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sqlTemplates);
        
        $relatorio[] = [
            'etapa' => 'Criação da Tabela `carteirinha_templates`',
            'status' => 'SUCESSO',
            'detalhes' => 'Tabela de modelos estruturada com sucesso (LONGTEXT sem DEFAULT incompatível).',
            'classe' => 'text-green-400 border-green-500/20 bg-green-500/10'
        ];
    } catch (PDOException $e) {
        $relatorio[] = [
            'etapa' => 'Criação da Tabela `carteirinha_templates`',
            'status' => 'FALHA',
            'detalhes' => 'Erro SQL: ' . $e->getMessage(),
            'classe' => 'text-red-400 border-red-500/20 bg-red-500/10'
        ];
        $erro_critico = true;
    }

    try {
        // Tabela de cargos vinculados
        $sqlCargos = "CREATE TABLE IF NOT EXISTS carteirinha_cargos (
            template_id INT NOT NULL,
            cargo_id INT NOT NULL,
            PRIMARY KEY (template_id, cargo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sqlCargos);
        
        $relatorio[] = [
            'etapa' => 'Criação da Tabela `carteirinha_cargos`',
            'status' => 'SUCESSO',
            'detalhes' => 'Tabela de relacionamento com cargos estruturada com sucesso.',
            'classe' => 'text-green-400 border-green-500/20 bg-green-500/10'
        ];
    } catch (PDOException $e) {
        $relatorio[] = [
            'etapa' => 'Criação da Tabela `carteirinha_cargos`',
            'status' => 'FALHA',
            'detalhes' => 'Erro SQL: ' . $e->getMessage(),
            'classe' => 'text-red-400 border-red-500/20 bg-red-500/10'
        ];
        $erro_critico = true;
    }
}

// 3. Validar se as tabelas agora realmente aparecem no banco
if (!$erro_critico) {
    try {
        $tabelas = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tabelas[] = $row[0];
        }

        $templates_ok = in_array('carteirinha_templates', $tabelas);
        $cargos_ok = in_array('carteirinha_cargos', $tabelas);

        if ($templates_ok && $cargos_ok) {
            $relatorio[] = [
                'etapa' => 'Verificação Física de Tabelas',
                'status' => 'SUCESSO',
                'detalhes' => 'Tabelas encontradas no schema ativo: ' . implode(', ', array_filter($tabelas, function($t) { return strpos($t, 'carteirinha_') === 0; })),
                'classe' => 'text-green-400 border-green-500/20 bg-green-500/10'
            ];
        } else {
            $relatorio[] = [
                'etapa' => 'Verificação Física de Tabelas',
                'status' => 'ATENÇÃO',
                'detalhes' => 'As tabelas não foram listadas no banco mesmo após execução sem erros. Verifique permissões do usuário do banco.',
                'classe' => 'text-yellow-400 border-yellow-500/20 bg-yellow-500/10'
            ];
        }
    } catch (PDOException $e) {
        $relatorio[] = [
            'etapa' => 'Verificação Física de Tabelas',
            'status' => 'FALHA',
            'detalhes' => 'Erro ao listar tabelas: ' . $e->getMessage(),
            'classe' => 'text-red-400 border-red-500/20 bg-red-500/10'
        ];
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-darkcard p-6 rounded-xl border border-darkborder shadow-lg flex items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-white tracking-tighter">Configuração de Banco - Carteirinhas</h2>
            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Status de inicialização da base de dados local/produção</p>
        </div>
        <div>
            <a href="carteirinha_digital.php" class="flex items-center gap-2 px-6 py-3 bg-brand hover:bg-brand-dark text-black rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-brand/20 transition-all">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Voltar para Carteirinhas
            </a>
        </div>
    </div>

    <div class="bg-darkcard border border-darkborder rounded-xl p-6 space-y-4 shadow-lg">
        <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-2">Relatório de Migração</h3>
        
        <div class="space-y-3">
            <?php foreach ($relatorio as $item): ?>
                <div class="p-4 border rounded-xl flex items-start gap-4 <?= $item['classe'] ?>">
                    <span class="material-symbols-outlined mt-0.5">
                        <?= $item['status'] === 'SUCESSO' ? 'check_circle' : ($item['status'] === 'ATENÇÃO' ? 'warning' : 'error') ?>
                    </span>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-white"><?= htmlspecialchars($item['etapa']) ?></h4>
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded bg-black/30">
                                <?= $item['status'] ?>
                            </span>
                        </div>
                        <p class="text-xs text-gray-300 mt-1"><?= htmlspecialchars($item['detalhes']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$erro_critico): ?>
            <div class="bg-green-500/5 border border-green-500/10 rounded-xl p-4 mt-6 text-center text-green-400 font-bold text-xs">
                🎉 Tudo pronto! As tabelas do editor de carteirinhas estão inicializadas com sucesso.
            </div>
        <?php else: ?>
            <div class="bg-red-500/5 border border-red-500/10 rounded-xl p-4 mt-6 text-center text-red-400 font-bold text-xs">
                ⚠️ Existem erros pendentes. Verifique a configuração de permissão do usuário do banco de dados na HostGator.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
