<?php
/**
 * SGIM OTA - SHARED HOSTING DRIVER v1.1.41 (REAL ACTIVATION ENABLED)
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
        "simulation_only" => false, // ✅ REAL ACTIVATION ENABLED
        "write_enabled" => true,
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

    public function validateEnvironment(): bool {
        return is_writable($this->basePath) && extension_loaded('zlib');
    }

    public function prepareActivation($versionPath, $manifest): bool {
        try {
            $this->log("Iniciando Fase de STAGING para v" . $manifest['version']);
            $this->validator->validate($manifest);
            $report = $this->backupEngine->generateImpactReport($manifest);
            $this->saveActivationReport($manifest['version'], $report);

            if (!$this->config['simulation_only']) {
                $this->backupEngine->performBackup($manifest);
            }
            return true;
        } catch (Exception $e) {
            $this->log("FALHA NO STAGING: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NÍVEL 3: COMMIT (Ativação Real via Smart Promote)
     */
    public function activate($versionPath, $manifest): bool {
        if ($this->config['simulation_only'] || !$this->config['write_enabled']) {
            $this->log("[SAFETY] Ativação REAL bloqueada. Verifique config.");
            return true; 
        }

        try {
            $version = $manifest['version'];
            $this->log("Iniciando PROMOÇÃO REAL de v$version para a raiz operacional.");
            
            $total = $this->recursivePromote($versionPath, $this->basePath, $version);
            
            $this->log("PROMOÇÃO CONCLUÍDA: $total arquivos movidos para a raiz.");
            return true;
        } catch (Exception $e) {
            $this->log("FALHA CRÍTICA NO COMMIT: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Função de Promoção Recursiva (Smart Flatten)
     */
    private function recursivePromote($src, $dst, $version) {
        if (!is_dir($src)) return 0;
        $dir = opendir($src);
        @mkdir($dst);
        $count = 0;
        
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                // Pular arquivos protegidos (configurações locais)
                if (ProtectedPathsPolicy::isProtected($file)) {
                    $this->log("[SKIP] Protegido: $file");
                    continue;
                }

                if ( is_dir($src . '/' . $file) ) {
                    $count += $this->recursivePromote($src . '/' . $file, $dst . '/' . $file, $version);
                } else {
                    if (copy($src . '/' . $file, $dst . '/' . $file)) {
                        $count++;
                    }
                }
            }
        }
        closedir($dir);
        return $count;
    }

    public function rollback($version): bool {
        $this->log("Rollback manual solicitado para $version.");
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
