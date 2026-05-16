<?php
/**
 * SGIM CLIENT - OTA MIGRATION ENGINE (INDUSTRIAL MODEL)
 * Responsável pela evolução segura do banco de dados (Expand/Contract).
 */

namespace SGIM\OTA;

use Exception;
use PDO;

class OtaMigrationEngine {
    private $pdo;
    private $statePath;
    private $migrationsPath;
    private $logsPath;

    public function __construct(PDO $pdo, $basePath) {
        $this->pdo = $pdo;
        $this->statePath = rtrim($basePath, '/') . '/shared/system/state/';
        $this->migrationsPath = rtrim($basePath, '/') . '/shared/system/migrations/';
        $this->logsPath = rtrim($basePath, '/') . '/shared/system/logs/';
        $this->ensureBaseline();
    }

    public function getPDO() {
        return $this->pdo;
    }

    /**
     * Estabelece a fundação de dados (Self-Provisioning)
     */
    private function ensureBaseline() {
        $sql = "CREATE TABLE IF NOT EXISTS ota_migrations_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        try {
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            // Falha silenciosa em runtime - logs internos tratam o erro
            $this->log("Erro ao garantir baseline de banco: " . $e->getMessage());
        }
    }

    /**
     * Executar Migrações Pendentes
     */
    public function migrate() {
        $applied = $this->getAppliedMigrations();
        $files = glob($this->migrationsPath . "*.php");
        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied)) {
                $this->runMigration($file, $name);
            }
        }
    }

    private function runMigration($file, $name) {
        try {
            $this->log("Iniciando migração: $name");
            
            // 1. Carregar a migração
            require_once $file;
            $className = "SGIM\\OTA\\Migrations\\" . $name;
            
            if (!class_exists($className)) {
                throw new Exception("Classe de migração $className não encontrada.");
            }

            $migration = new $className();

            // 2. Executar em Transação
            $this->pdo->beginTransaction();
            $migration->up($this->pdo);

            // 3. Registrar Histórico
            $stmt = $this->pdo->prepare("INSERT INTO ota_migrations_history (migration_name) VALUES (?)");
            $stmt->execute([$name]);

            $this->pdo->commit();
            $this->log("Migração concluída com sucesso: $name");
            $this->updateFileState($name);

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->log("ERRO NA MIGRAÇÃO $name: " . $e->getMessage());
            throw $e;
        }
    }

    private function getAppliedMigrations() {
        $stmt = $this->pdo->query("SELECT migration_name FROM ota_migrations_history");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    private function updateFileState($name) {
        $stateFile = $this->statePath . 'migration_state.json';
        $state = json_decode(file_get_contents($stateFile), true);
        $state['applied_migrations'][] = $name;
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function log($message) {
        $logFile = $this->logsPath . 'migrations.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
