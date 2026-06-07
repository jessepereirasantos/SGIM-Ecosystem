<?php
require_once 'config/database.php';

$auto_run = isset($_GET['auto_run']) && $_GET['auto_run'] == 1;
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if (!$auto_run) {
    echo "<h2>🔧 Diagnóstico e Correção de Banco (OTA v1.1.95)</h2>";
    echo "<ul>";
}

function logFix($msg) {
    global $auto_run;
    if (!$auto_run) {
        echo "<li>" . $msg . "</li>";
    }
}

try {
    // 1. Tabela sistema_novidades
    try {
        $columns = $pdo->query("DESCRIBE sistema_novidades")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('icone', $columns)) {
            $pdo->exec("ALTER TABLE sistema_novidades ADD COLUMN icone VARCHAR(50) DEFAULT 'rocket_launch' AFTER badge");
            logFix("✅ Coluna <b>icone</b> adicionada na tabela sistema_novidades.");
        }
        if (!in_array('visto', $columns)) {
            $pdo->exec("ALTER TABLE sistema_novidades ADD COLUMN visto TINYINT(1) DEFAULT 0 AFTER descricao");
            logFix("✅ Coluna <b>visto</b> adicionada na tabela sistema_novidades.");
        }
    } catch (Throwable $e) {
        logFix("⚠️ Falha ao verificar/ajustar sistema_novidades: " . $e->getMessage());
    }

    // 2. Tabela membros (Carteirinhas v1.1.94)
    try {
        $columns = $pdo->query("DESCRIBE membros")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('hash_carteirinha', $columns)) {
            $pdo->exec("ALTER TABLE membros ADD COLUMN hash_carteirinha VARCHAR(64) DEFAULT NULL AFTER status");
            logFix("✅ Coluna <b>hash_carteirinha</b> adicionada na tabela membros.");
        }
        if (!in_array('carteirinha_valida_ate', $columns)) {
            $pdo->exec("ALTER TABLE membros ADD COLUMN carteirinha_valida_ate DATE DEFAULT NULL AFTER hash_carteirinha");
            logFix("✅ Coluna <b>carteirinha_valida_ate</b> adicionada na tabela membros.");
        }

        // Criar índice único idx_hash_carteirinha
        $hasIndex = false;
        $indexes = $pdo->query("SHOW INDEX FROM membros")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $idx) {
            if ($idx['Key_name'] === 'idx_hash_carteirinha') {
                $hasIndex = true;
                break;
            }
        }
        if (!$hasIndex) {
            $pdo->exec("CREATE UNIQUE INDEX idx_hash_carteirinha ON membros (hash_carteirinha)");
            logFix("✅ Índice único <b>idx_hash_carteirinha</b> criado na tabela membros.");
        }
    } catch (Throwable $e) {
        logFix("⚠️ Falha ao verificar/ajustar membros: " . $e->getMessage());
    }

    // 3. Tabela financeiro_transacoes (Financeiro Corrigido)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro_transacoes (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        logFix("✅ Tabela <b>financeiro_transacoes</b> verificada/criada.");

        $columns = $pdo->query("DESCRIBE financeiro_transacoes")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('congregacao_id', $columns)) {
            $pdo->exec("ALTER TABLE financeiro_transacoes ADD COLUMN congregacao_id INT DEFAULT NULL AFTER membro_id");
            logFix("✅ Coluna <b>congregacao_id</b> adicionada em financeiro_transacoes.");
        }
        if (!in_array('deleted_at', $columns)) {
            $pdo->exec("ALTER TABLE financeiro_transacoes ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER nome_identificado");
            logFix("✅ Coluna <b>deleted_at</b> adicionada em financeiro_transacoes.");
        }
    } catch (Throwable $e) {
        logFix("⚠️ Falha ao verificar/ajustar financeiro_transacoes: " . $e->getMessage());
    }

    // 4. Catálogo de Permissões Adicionais (RBAC Completo)
    try {
        $stmtIns = $pdo->prepare("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES (?, ?, ?)");
        $permissions = [
            ['carteirinhas', 'visualizar', 'Ver listagem de carteirinhas'],
            ['carteirinhas', 'gerenciar', 'Criar, editar e renovar carteirinhas'],
            ['departamentos', 'visualizar', 'Ver departamentos e cargos'],
            ['departamentos', 'gerenciar', 'Gerenciar departamentos e cargos'],
            ['eventos', 'visualizar', 'Ver eventos e cultos'],
            ['comunicacao', 'visualizar', 'Ver histórico de comunicações'],
            ['congregacoes', 'visualizar', 'Ver congregações'],
            ['configuracoes', 'visualizar', 'Ver configurações do sistema']
        ];
        foreach ($permissions as $p) {
            $stmtIns->execute($p);
        }
        logFix("✅ Catálogo de permissões adicionadas/atualizadas.");

        // Mapear todas para o Admin Total (cargo_id = 1)
        $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id)
                    SELECT 1, id FROM permissoes;");
        logFix("✅ Permissões mapeadas para o cargo Admin Total.");
    } catch (Throwable $e) {
        logFix("⚠️ Falha ao atualizar permissões (RBAC): " . $e->getMessage());
    }

    // 5. Atualizar versão no banco de dados
    try {
        $stmtVer = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', '1.1.95') ON DUPLICATE KEY UPDATE valor = '1.1.95'");
        $stmtVer->execute();
        logFix("✅ Versão do sistema no banco atualizada para <b>1.1.95</b>.");
    } catch (Throwable $e) {
        logFix("⚠️ Falha ao atualizar versão no banco: " . $e->getMessage());
    }

    if ($auto_run) {
        // Sanitizar a rota de redirecionamento para evitar open redirect vulnerabilidade
        $safe_redirect = basename($redirect);
        if (strpos($redirect, '..') !== false || strpos($redirect, '/') !== false) {
            $safe_redirect = 'dashboard.php';
        }
        header("Location: " . $safe_redirect . "?db_synced=1");
        exit;
    } else {
        echo "</ul>";
        echo "<br><b>✅ Banco de dados sincronizado e corrigido com sucesso!</b>";
        echo "<br><p><a href='dashboard.php' style='color:#FFC107; font-weight:bold;'>Voltar para a Dashboard</a></p>";
    }

} catch (Throwable $e) {
    if ($auto_run) {
        header("Location: dashboard.php?db_sync_error=" . urlencode($e->getMessage()));
        exit;
    } else {
        echo "</ul>";
        echo "❌ Erro geral ao processar: " . $e->getMessage();
    }
}
?>
