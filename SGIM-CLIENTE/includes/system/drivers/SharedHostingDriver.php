<?php
/**
 * SGIM OTA - SHARED HOSTING DRIVER v1.1.43 (SMART PROMOTER)
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
    public function prepareActivation($vPath, $m): bool { return true; }

    public function activate($versionPath, $manifest): bool {
        try {
            $version = $manifest['version'] ?? 'unknown';
            if ($version === 'unknown' && preg_match('/v(\d+\.\d+\.\d+)/', $versionPath, $matches)) {
                $version = $matches[1];
            }

            $this->log("Iniciando Promoção Inteligente para v$version");

            // DETETIVE: Encontrar a verdadeira pasta dos arquivos
            $actualSrc = $this->findDeepSource($versionPath);
            $this->log("Pasta de origem identificada: $actualSrc");

            // 2. Promover Arquivos da pasta correta
            $total = $this->recursivePromote($actualSrc, $this->basePath, $version);
            
            // 3. Sincronizar Banco
            if ($this->pdo instanceof PDO && $total > 0) {
                $stmt = $this->pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = ?");
                $stmt->execute([$version, $version]);
                $this->log("SUCESSO: v$version ativa ($total arquivos movidos)");
            }

            if (function_exists('opcache_reset')) opcache_reset();
            return true;
        } catch (Exception $e) {
            $this->log("FALHA NO COMMIT: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Tenta encontrar onde os arquivos realmente estão (corrige ZIPs aninhados)
     */
    private function findDeepSource($path) {
        $files = array_diff(scandir($path), ['.', '..']);
        // Se a pasta só tem UMA subpasta (tipo SGIM-CLIENTE ou source_cliente), entra nela
        if (count($files) === 1) {
            $sub = reset($files);
            if (is_dir($path . $sub)) {
                return $path . $sub . '/';
            }
        }
        // Se encontrar a pasta 'includes', 'api' ou 'config', este é o lugar certo
        if (in_array('includes', $files) || in_array('api', $files)) {
            return $path;
        }
        return $path;
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
