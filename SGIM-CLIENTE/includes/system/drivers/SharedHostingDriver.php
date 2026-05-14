<?php
/**
 * SGIM OTA - SHARED HOSTING DRIVER v1.1.42 (ZERO-NULL PROTECTION)
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
    private $pdo;
    private $config = ["simulation_only" => false, "write_enabled" => true];
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

    public function validateEnvironment(): bool { return true; }

    public function prepareActivation($versionPath, $manifest): bool {
        $this->log("Staging para v" . ($manifest['version'] ?? 'unknown'));
        return true; 
    }

    public function activate($versionPath, $manifest): bool {
        try {
            // 1. Determinar Versão (Fallback se manifesto falhar)
            $version = $manifest['version'] ?? null;
            if (!$version) {
                // Tenta extrair do caminho da pasta (ex: releases/v1.1.46/ -> 1.1.46)
                if (preg_match('/v(\d+\.\d+\.\d+)/', $versionPath, $matches)) {
                    $version = $matches[1];
                }
            }

            if (!$version) {
                throw new Exception("Falha crítica: Versão alvo não identificada no Manifesto nem no Caminho.");
            }

            $this->log("Iniciando Promoção Real para v$version");
            
            // 2. Promover Arquivos
            $total = $this->recursivePromote($versionPath, $this->basePath, $version);
            
            // 3. Sincronizar Banco (Apenas se tiver versão válida)
            if ($this->pdo instanceof PDO && $total > 0) {
                $stmt = $this->pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = ?");
                $stmt->execute([$version, $version]);
                $this->log("BANCO ATUALIZADO: v$version ($total arquivos)");
            }

            if (function_exists('opcache_reset')) opcache_reset();

            return true;
        } catch (Exception $e) {
            $this->log("FALHA NO COMMIT: " . $e->getMessage());
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
                    if (copy($src . '/' . $file, $dst . '/' . $file)) $count++;
                }
            }
        }
        closedir($dir);
        return $count;
    }

    public function rollback($v): bool { return true; }
    public function getHealthcheck(): array { return ["status" => "READY"]; }

    private function log($message) {
        $logFile = $this->logsPath . 'activation.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] [SharedHosting] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
