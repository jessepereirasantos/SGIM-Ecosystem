<?php
namespace App\Updater;

use PDO;
use Exception;

class MigrationManager {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ensureLogsTable();
    }

    private function ensureLogsTable() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS sistema_migrations_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_migration VARCHAR(100) NOT NULL UNIQUE,
            versao VARCHAR(20),
            data_execucao DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    /**
     * Executa uma lista de migrations estruturadas
     * $migrations = [['id' => '2026_01_add_col', 'sql' => '...'], ...]
     */
    public function runMigrations($version, $migrations) {
        if (empty($migrations)) return true;

        foreach ($migrations as $m) {
            $id = $m['id'] ?? null;
            $sql = $m['sql'] ?? null;

            if (!$id || !$sql) continue;

            // Verificar se esta migração específica já foi executada
            $stmt = $this->db->prepare("SELECT id FROM sistema_migrations_log WHERE id_migration = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                continue; // Já executada, pula para a próxima
            }

            try {
                $this->db->beginTransaction();
                
                // Limpeza e execução do comando
                $this->db->exec($sql);

                // Registrar sucesso atômico
                $stmt = $this->db->prepare("INSERT INTO sistema_migrations_log (id_migration, versao) VALUES (?, ?)");
                $stmt->execute([$id, $version]);

                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("Migration Fail ($id): " . $e->getMessage());
                throw new Exception("Falha na Migração SQL ($id): " . $e->getMessage());
            }
        }
        return true;
    }
}
