<?php
namespace SGIM\OTA\Migrations;

use Exception;
use PDO;

class Migration_v1_1_94_AcessosECarteirinhas {
    /**
     * Executa as alterações de banco de dados para a v1.1.94/v1.1.95.
     */
    public function up(PDO $pdo) {
        // 1. Verificar e adicionar colunas na tabela membros
        $columnsMembros = $pdo->query("DESCRIBE membros")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('hash_carteirinha', $columnsMembros)) {
            $pdo->exec("ALTER TABLE membros ADD COLUMN hash_carteirinha VARCHAR(64) DEFAULT NULL AFTER status");
        }
        
        if (!in_array('carteirinha_valida_ate', $columnsMembros)) {
            $pdo->exec("ALTER TABLE membros ADD COLUMN carteirinha_valida_ate DATE DEFAULT NULL AFTER hash_carteirinha");
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
        }

        // 2. Criar a tabela financeiro_transacoes e adicionar colunas se necessário
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

        $columnsFin = $pdo->query("DESCRIBE financeiro_transacoes")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('congregacao_id', $columnsFin)) {
            $pdo->exec("ALTER TABLE financeiro_transacoes ADD COLUMN congregacao_id INT DEFAULT NULL AFTER membro_id");
        }
        if (!in_array('deleted_at', $columnsFin)) {
            $pdo->exec("ALTER TABLE financeiro_transacoes ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER nome_identificado");
        }

        // 3. Cadastrar Catálogo de Permissões Adicionais (RBAC Completo)
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

        // Mapear todas para o Admin Total (cargo_id = 1)
        $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id)
                    SELECT 1, id FROM permissoes;");
    }
}
?>
