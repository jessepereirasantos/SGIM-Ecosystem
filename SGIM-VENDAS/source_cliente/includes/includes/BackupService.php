<?php
/**
 * SGIM - Sistema de Backup e Restauração (v1.0)
 * Este script gerencia a criação de backups do banco de dados e arquivos.
 */
class BackupService {
    private $pdo;
    private $backupDir;

    public function __construct($pdo, $backupDir) {
        $this->pdo = $pdo;
        $this->backupDir = $backupDir;
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function createDatabaseBackup() {
        try {
            $tables = [];
            $result = $this->pdo->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sql = "-- SGIM Database Backup\n";
            $sql .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $res = $this->pdo->query("SHOW CREATE TABLE $table");
                $row = $res->fetch(PDO::FETCH_NUM);
                $sql .= "DROP TABLE IF EXISTS $table;\n";
                $sql .= $row[1] . ";\n\n";

                $res = $this->pdo->query("SELECT * FROM $table");
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_keys($row);
                    $values = array_map(function($v) {
                        if ($v === null) return 'NULL';
                        return $this->pdo->quote($v);
                    }, array_values($row));
                    $sql .= "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }

            $filename = 'backup_db_' . date('Y-m-d_H-i-s') . '.sql';
            file_put_contents($this->backupDir . '/' . $filename, $sql);
            return $filename;
        } catch (Exception $e) {
            error_log("Backup Error: " . $e->getMessage());
            return false;
        }
    }

    public function createFullBackup() {
        try {
            $sqlFile = $this->createDatabaseBackup();
            if (!$sqlFile) return false;

            $zipName = 'backup_FULL_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = $this->backupDir . '/' . $zipName;
            $sourceDir = realpath(__DIR__ . '/../');

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                // Adicionar o SQL primeiro
                $zip->addFile($this->backupDir . '/' . $sqlFile, 'database_backup.sql');

                // Adicionar arquivos do sistema
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourceDir),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($sourceDir) + 1);

                        // Não incluir a própria pasta de backups no ZIP para evitar loop infinito
                        if (strpos($relativePath, 'backups/') === 0 || strpos($relativePath, 'backups\\') === 0) {
                            continue;
                        }

                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                // Remover o SQL temporário fora do zip se desejar (ou manter como backup individual)
                // unlink($this->backupDir . '/' . $sqlFile); 
                return $zipName;
            }
            return false;
        } catch (Exception $e) {
            error_log("Full Backup Error: " . $e->getMessage());
            return false;
        }
    }

    public function restoreFullBackup($backupFile) {
        $zipPath = $this->backupDir . '/' . basename($backupFile);
        if (!file_exists($zipPath)) return ['success' => false, 'message' => 'Arquivo não encontrado.'];

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $extractTo = __DIR__ . '/../';
                
                // 1. Extrair Arquivos (Exceto banco de dados local config se necessário)
                $zip->extractTo($extractTo);
                $zip->close();

                // 2. Localizar e Executar SQL
                $sqlPath = $extractTo . 'database_backup.sql';
                if (file_exists($sqlPath)) {
                    $sql = file_get_contents($sqlPath);
                    $this->pdo->exec($sql);
                    unlink($sqlPath); // Limpar após restaurar
                }

                return ['success' => true, 'message' => 'Restauração concluída com sucesso!'];
            }
            return ['success' => false, 'message' => 'Erro ao abrir o pacote ZIP.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro crítico na restauração: ' . $e->getMessage()];
        }
    }

    public function listBackups() {
        $files = glob($this->backupDir . '/*.{sql,zip}', GLOB_BRACE);
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return array_map(function($f) {
            return [
                'name' => basename($f),
                'size' => filesize($f),
                'date' => date('Y-m-d H:i:s', filemtime($f)),
                'type' => pathinfo($f, PATHINFO_EXTENSION)
            ];
        }, $files);
    }
}
