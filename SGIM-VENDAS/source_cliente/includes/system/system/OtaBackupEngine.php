<?php
/**
 * SGIM OTA - INCREMENTAL BACKUP ENGINE
 * Responsável por garantir que cada alteração tenha um ponto de restauração.
 */

namespace SGIM\OTA;

use Exception;

class OtaBackupEngine {
    private $basePath;
    private $backupDir;

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->backupDir = $this->basePath . 'shared/system/backups/';
    }

    /**
     * Gera Relatório de Impacto (Dry-Run)
     */
    public function generateImpactReport($manifest) {
        $report = [
            "version" => $manifest['version'],
            "to_change" => [],
            "to_delete" => [],
            "protected_ignored" => [],
            "estimated_space" => 0
        ];

        foreach ($manifest['changed_files'] as $file) {
            $fullPath = $this->basePath . $file;
            if (file_exists($fullPath)) {
                $report['to_change'][] = [
                    "path" => $file,
                    "size" => filesize($fullPath),
                    "hash_pre" => hash_file('sha256', $fullPath)
                ];
                $report['estimated_space'] += filesize($fullPath);
            }
        }

        return $report;
    }

    /**
     * Executa o Backup Incremental antes da ativação
     */
    public function performBackup($manifest) {
        $releaseBackupPath = $this->backupDir . 'release_' . $manifest['version'] . '/';
        if (!file_exists($releaseBackupPath)) mkdir($releaseBackupPath, 0755, true);
        if (!file_exists($releaseBackupPath . 'modified/')) mkdir($releaseBackupPath . 'modified/', 0755, true);

        $rollbackMap = [];

        foreach ($manifest['changed_files'] as $file) {
            $source = $this->basePath . $file;
            if (file_exists($source)) {
                $dest = $releaseBackupPath . 'modified/' . str_replace('/', '_', $file);
                if (copy($source, $dest)) {
                    $rollbackMap[$file] = [
                        "backup_file" => $dest,
                        "original_sha256" => hash_file('sha256', $source)
                    ];
                }
            }
        }

        file_put_contents($releaseBackupPath . 'rollback_map.json', json_encode($rollbackMap, JSON_PRETTY_PRINT));
        return true;
    }
}
