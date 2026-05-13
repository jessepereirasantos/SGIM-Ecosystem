<?php
/**
 * SGIM OTA - SHARED HOSTING DRIVER v1.1.41 (REAL ACTIVATION + DB SYNC)
 */

namespace SGIM\OTA\Drivers;

use SGIM\OTA\ActivationDriverInterface;
use SGIM\OTA\OtaManifestValidator;
use SGIM\OTA\OtaBackupEngine;
use SGIM\OTA\ProtectedPathsPolicy;
use Exception;
use PDO;

class SharedHostingDriver implements ActivationDriverInterface {
    private $basePath;
    private $pdo; // ✅ Adicionado para sincronização de banco
    private $config = [
        "simulation_only" => false,
        "write_enabled" => true,
        "rollback_enabled" => true
    ];
    
    private $validator;
    private $backupEngine;
    private $logsPath;

    public function __construct($basePath, $pdo = null) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->pdo = $pdo;
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
     * NÍVEL 3: COMMIT (Promoção de Arquivos + Banco + Cache)
     */
    public function activate($versionPath, $manifest): bool {
        if ($this->config['simulation_only'] || !$this->config['write_enabled']) {
            $this->log("[SAFETY] Ativação REAL bloqueada.");
            return true; 
        }

        try {
            $version = $manifest['version'];
            $this->log("Iniciando PROMOÇÃO REAL de v$version para a raiz operacional.");
            
            // 1. Promover Arquivos
            $total = $this->recursivePromote($versionPath, $this->basePath, $version);
            $this->log("PROMOÇÃO FÍSICA CONCLUÍDA: $total arquivos movidos.");

            if ($total === 0) {
                throw new Exception("Nenhum arquivo foi promovido. Verifique a pasta de origem: $versionPath");
            }

            // 2. Atualizar Banco de Dados
            if ($this->pdo instanceof PDO) {
                $stmt = $this->pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'versao_sistema'");
                $stmt->execute([$version]);
                $this->log("BANCO DE DADOS ATUALIZADO: v$version");
            } else {
                $this->log("[AVISO] PDO não disponível para atualização de banco.");
            }

            // 3. Limpar Cache do Servidor
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $this->log("OPCACHE RESETADO.");
            }

            return true;
        } catch (Exception $e) {
            $this->log("FALHA CRÍTICA NO COMMIT: " . $e->getMessage());
            return false;
        }
    }

    private function recursivePromote($src, $dst, $version) {
        if (!is_dir($src)) return 0;
        $dir = opendir($src);
        @mkdir($dst);
        $count = 0;
        
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (ProtectedPathsPolicy::isProtected($file)) continue;

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

    public function rollback($version): bool { return true; }
    public function getHealthcheck(): array { return ["status" => "READY"]; }

    private function saveActivationReport($version, $impact) {
        $report = ["release" => $version, "status" => "READY_FOR_COMMIT"];
        file_put_contents($this->basePath . 'shared/system/state/activation_report.json', json_encode($report, JSON_PRETTY_PRINT));
    }

    private function log($message) {
        $logFile = $this->logsPath . 'activation.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] [SharedHosting] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
