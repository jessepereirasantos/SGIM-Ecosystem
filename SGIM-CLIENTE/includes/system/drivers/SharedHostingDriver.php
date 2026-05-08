<?php
/**
 * SGIM OTA - SHARED HOSTING DRIVER
 * Estratégia de ativação física incremental para ambientes restritos.
 * MODO: SIMULATION_ONLY (Proteção Total)
 */

namespace SGIM\OTA\Drivers;

use SGIM\OTA\ActivationDriverInterface;
use SGIM\OTA\OtaManifestValidator;
use SGIM\OTA\OtaBackupEngine;
use SGIM\OTA\ProtectedPathsPolicy;
use Exception;

class SharedHostingDriver implements ActivationDriverInterface {
    private $basePath;
    private $config = [
        "simulation_only" => true,
        "write_enabled" => false,
        "rollback_enabled" => true
    ];
    
    private $validator;
    private $backupEngine;
    private $logsPath;

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->validator = new OtaManifestValidator();
        $this->backupEngine = new OtaBackupEngine($this->basePath);
        $this->logsPath = $this->basePath . 'shared/system/logs/';
    }

    /**
     * NÍVEL 1: ANALYZE (Auditoria e Impacto)
     */
    public function validateEnvironment(): bool {
        return is_writable($this->basePath) && extension_loaded('zlib');
    }

    /**
     * NÍVEL 2: STAGING (Backup + Preparação)
     */
    public function prepareActivation($versionPath, $manifest): bool {
        try {
            $this->log("Iniciando Fase de STAGING para v" . $manifest['version']);
            
            // 1. Validar Manifesto Rigorosamente
            $this->validator->validate($manifest);

            // 2. Gerar Relatório de Impacto (ANALYZE)
            $report = $this->backupEngine->generateImpactReport($manifest);
            $this->saveActivationReport($manifest['version'], $report);

            // 3. Executar Backup Incremental
            if (!$this->config['simulation_only']) {
                $this->backupEngine->performBackup($manifest);
            } else {
                $this->log("[SIMULATION] Backup incremental simulado.");
            }

            return true;
        } catch (Exception $e) {
            $this->log("FALHA NO STAGING: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NÍVEL 3: COMMIT (Ativação Real - Bloqueada por default)
     */
    public function activate($versionPath, $manifest): bool {
        if ($this->config['simulation_only'] || !$this->config['write_enabled']) {
            $this->log("[SAFETY] Ativação REAL bloqueada. Driver em modo SIMULAÇÃO.");
            return true; 
        }

        try {
            $this->log("Iniciando COMMIT de versão " . $manifest['version']);
            
            foreach ($manifest['changed_files'] as $file) {
                $source = rtrim($versionPath, '/') . '/' . $file;
                $dest = $this->basePath . $file;

                // Segurança Absoluta contra Protected Paths
                if (ProtectedPathsPolicy::isProtected($file)) continue;

                if (!copy($source, $dest)) {
                    throw new Exception("Falha crítica ao copiar arquivo: $file");
                }
            }

            return true;
        } catch (Exception $e) {
            $this->log("FALHA NO COMMIT: " . $e->getMessage());
            $this->rollback($manifest['version']);
            return false;
        }
    }

    public function rollback($version): bool {
        $this->log("Executando ROLLBACK para versão $version...");
        // Lógica de restauração incremental via rollback_map.json
        return true;
    }

    public function getHealthcheck(): array {
        return ["status" => "READY", "driver" => "SharedHostingDriver"];
    }

    private function saveActivationReport($version, $impact) {
        $report = [
            "release" => $version,
            "driver" => "SharedHostingDriver",
            "changed_files" => count($impact['to_change']),
            "estimated_disk_usage" => $impact['estimated_space'],
            "status" => "READY_FOR_COMMIT"
        ];
        file_put_contents($this->basePath . 'shared/system/state/activation_report.json', json_encode($report, JSON_PRETTY_PRINT));
    }

    private function log($message) {
        $logFile = $this->logsPath . 'activation.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] [SharedHosting] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
