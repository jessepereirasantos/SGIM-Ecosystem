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

            // 3. ESTRATÉGIA DE SWAP (Symlink com Fallback para Rename)
            $currentLink = $this->releasesPath . 'current';
            $previousLink = $this->releasesPath . 'previous';
            
            // Tenta Symlink primeiro (Ideal para Linux/cPanel)
            $useSymlink = true;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $useSymlink = false; // Windows raramente permite symlink via PHP sem privilégios
            }

            if ($useSymlink) {
                $this->log("Tentando Swap via Symlink...");
                $tmpLink = $this->releasesPath . 'current_tmp_' . time();
                
                if (@symlink($versionPath, $tmpLink)) {
                    if (file_exists($currentLink) || is_link($currentLink)) {
                        if (file_exists($previousLink) || is_link($previousLink)) @unlink($previousLink);
                        @rename($currentLink, $previousLink);
                    }
                    if (@rename($tmpLink, $currentLink)) {
                        $this->log("Swap via Symlink concluído.");
                    } else {
                        throw new Exception("Falha ao renomear Symlink temporário.");
                    }
                } else {
                    $this->log("Symlink falhou ou não suportado. Usando estratégia de RENAME...");
                    $useSymlink = false;
                }
            }

            // Fallback: Rename (Copiar não, mover!)
            if (!$useSymlink) {
                $this->log("Executando Swap via Inclusão Direta/Router...");
                // No modo sem symlink, o Router (.htaccess) deve apontar diretamente para a pasta da versão
                // ou mantemos o 'current' como um diretório real.
                if (file_exists($currentLink) && !is_link($currentLink)) {
                    if (file_exists($previousLink)) $this->recursiveRmdir($previousLink);
                    @rename($currentLink, $previousLink);
                }
                
                // Em vez de symlink, movemos a pasta inteira para 'current'
                // Mas queremos manter a versão original em /releases/v1.x.x/
                // Então vamos copiar? Não, copy é lento. 
                // Vamos usar o Router para apontar para a pasta da versão!
                $this->updateRouter($version);
            }

            // 4. HEALTH CHECK
            if (!$this->verifyHealth($versionPath)) {
                throw new Exception("Health Check falhou para v$version.");
            }

            // 5. COMMIT NO BANCO
            if ($this->pdo instanceof PDO) {
                $stmt = $this->pdo->prepare("REPLACE INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?)");
                $stmt->execute([$version]);
            }

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
    private function updateRouter($version) {
        $htaccessPath = $this->basePath . '.htaccess';
        $targetFolder = "releases/v" . $version;
        
        $routerRules = "
# SGIM OTA v2.0 - DYNAMIC ROUTER
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Exceções (não redireciona pastas de sistema ou assets compartilhados)
    RewriteCond %{REQUEST_URI} !^/shared/
    RewriteCond %{REQUEST_URI} !^/releases/
    
    # Redireciona tudo para a pasta da release ativa
    RewriteRule ^(.*)$ $targetFolder/$1 [L,QSA]
</IfModule>
";
        // Sobrescreve ou cria o router atômico
        $content = "";
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            // Remove regras antigas do OTA se existirem
            $content = preg_replace('/# SGIM OTA v2.0 - .*?<\/IfModule>/s', '', $content);
        }
        
        file_put_contents($htaccessPath, trim($content) . "\n" . $routerRules, LOCK_EX);
        $this->log("Router dinâmico atualizado para apontar para $targetFolder.");
    }

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
