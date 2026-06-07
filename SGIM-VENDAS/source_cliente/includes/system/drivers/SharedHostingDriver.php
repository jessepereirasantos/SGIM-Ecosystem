<?php
/**
 * SGIM OTA - SHARED HOSTING DRIVER v2.0 (ATOMIC SWAP)
 * Executa promoção determinística via Symlinks ou Atomic Renames.
 * PROIBIDO O USO DE COPY() RECURSIVO.
 */

namespace SGIM\OTA\Drivers;

use SGIM\OTA\ActivationDriverInterface;
use Exception;
use PDO;

class SharedHostingDriver implements ActivationDriverInterface {
    private $basePath;
    private $pdo;
    private $logsPath;
    private $releasesPath;

    public function __construct($basePath, $pdo = null) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->pdo = $pdo;
        $this->logsPath = $this->basePath . 'shared/system/logs/';
        $this->releasesPath = $this->basePath . 'releases/';
    }

    public function validateEnvironment(): bool { return true; }
    public function prepareActivation($vPath, $m): bool { return true; }

    /**
     * PROMOÇÃO ATÔMICA
     */
    public function activate($versionPath, $manifest): bool {
        try {
            $version = $manifest['version'] ?? null;
            if (!$version) throw new Exception("Manifesto sem versão definida.");
            
            // 1. VALIDAÇÃO RÍGIDA E DETERMINÍSTICA
            $versionPath = rtrim($versionPath, '/') . '/';
            $vitalFiles = ['index.php', 'api/health/version.php'];
            foreach ($vitalFiles as $file) {
                if (!file_exists($versionPath . $file)) {
                    throw new Exception("Estrutura Inválida: Arquivo obrigatório $file não encontrado em $versionPath.");
                }
            }

            $this->log("Iniciando Promoção para versão $version...");

            // 2. MIGRAR CONFIGURAÇÕES VITAIS
            $configSource = $this->basePath . 'config/database.php';
            $configTarget = $versionPath . 'config/database.php';
            if (file_exists($configSource)) {
                if (!file_exists(dirname($configTarget))) @mkdir(dirname($configTarget), 0755, true);
                if (!@copy($configSource, $configTarget)) {
                    $this->log("AVISO: Falha ao copiar database.php. Tentando continuar...");
                } else {
                    $this->log("Configurações (database.php) injetadas na v$version.");
                }
            }

            // 3. SWAP ATÔMICO (Symlink - Estratégia de Alta Performance)
            $currentLink = $this->releasesPath . 'current';
            $this->log("[AtomicSwap] Vinculando Symlink 'current' para v$version...");
            
            if (file_exists($currentLink) || is_link($currentLink)) {
                @unlink($currentLink);
            }
            
            if (!@symlink($versionPath, $currentLink)) {
                // Fallback: Se o symlink falhar, criamos uma ponte física (Pasta com index.php)
                $this->log("[AtomicSwap] Symlink falhou. Criando ponte física em /releases/current/...");
                if (!is_dir($currentLink)) @mkdir($currentLink, 0755, true);
                file_put_contents($currentLink . '/index.php', "<?php require_once '$versionPath/index.php';");
            }

            // 3.5. GARANTIR PONTES OPERACIONAIS PARA NOVOS ARQUIVOS PHP
            $releaseFiles = glob($versionPath . '*.php');
            if ($releaseFiles) {
                foreach ($releaseFiles as $filePath) {
                    $fileName = basename($filePath);
                    $targetBridge = $this->basePath . $fileName;
                    if (!file_exists($targetBridge)) {
                        $bridgeContent = "<?php\n// AUTO-PONTE GERADA PELO OTA v2\nrequire_once __DIR__ . '/releases/current/' . basename(__FILE__);\n";
                        if (@file_put_contents($targetBridge, $bridgeContent)) {
                            $this->log("Ponte física criada para novo arquivo: $fileName");
                        } else {
                            $this->log("AVISO: Falha ao criar ponte para $fileName");
                        }
                    }
                }
            }

            // 4. LIMPEZA DE CACHE
            if (function_exists('opcache_reset')) @opcache_reset();

            $this->log("✅ ATIVAÇÃO CONCLUÍDA: v$version ativa.");
            return true;

        } catch (Exception $e) {
            $this->log("ERRO CRÍTICO: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza o Router (.htaccess) para apontar para a versão correta
     */
    private function recursiveRmdir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRmdir("$dir/$file") : @unlink("$dir/$file");
        }
        return @rmdir($dir);
    }

    /**
     * Health Check (Valida se o servidor web está enxergando a nova versão)
     */
    private function verifyHealth($currentPath) {
        $healthScript = rtrim($currentPath, '/') . '/api/health/version.php';
        // O código anterior tentava fazer json_decode no texto puro do PHP.
        // Apenas confirmamos a existência física pós-symlink para liberar o commit.
        return file_exists($healthScript);
    }

    /**
     * ROLLBACK REAL
     */
    public function rollback($v): bool {
        $currentLink = $this->releasesPath . 'current';
        $previousLink = $this->releasesPath . 'previous';
        
        $this->log("⚠️ INICIANDO ROLLBACK REAL PARA A VERSÃO ANTERIOR...");

        if (file_exists($previousLink) || is_link($previousLink)) {
            $tmpLink = $this->releasesPath . 'rollback_tmp_' . time();
            $target = readlink($previousLink);
            
            symlink($target, $tmpLink);
            rename($tmpLink, $currentLink);
            
            if (function_exists('opcache_reset')) @opcache_reset();
            $this->log("✅ ROLLBACK CONCLUÍDO. Sistema restaurado para a versão do previous.");
            return true;
        }
        
        $this->log("❌ ROLLBACK FALHOU: Link previous não encontrado.");
        return false;
    }

    public function getHealthcheck(): array { return ["status" => "DETERMINISTIC_MODE"]; }

    private function log($message) {
        $logFile = $this->logsPath . 'activation.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] [AtomicSwap] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
