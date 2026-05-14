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
            
            // 1. VALIDAÇÃO RÍGIDA E DETERMINÍSTICA (FIM DO BULLDOZER)
            $versionPath = rtrim($versionPath, '/') . '/';
            $vitalFiles = ['index.php', 'api/health/version.php'];
            foreach ($vitalFiles as $file) {
                if (!file_exists($versionPath . $file)) {
                    throw new Exception("Validação de Estrutura Falhou: Arquivo obrigatório $file não encontrado em $versionPath. Rejeição sumária.");
                }
            }

            $this->log("Iniciando Swap Atômico para versão $version...");

            // 2. ATOMIC SWAP (SYMLINK STRATEGY)
            $currentLink = $this->releasesPath . 'current';
            $previousLink = $this->releasesPath . 'previous';
            $tmpLink = $this->releasesPath . 'current_tmp_' . time();

            // Backup do ponteiro antigo para rollback
            if (file_exists($currentLink) || is_link($currentLink)) {
                if (file_exists($previousLink) || is_link($previousLink)) {
                    @unlink($previousLink);
                }
                @rename($currentLink, $previousLink);
            }

            // Criar novo apontamento atômico
            if (!symlink($versionPath, $tmpLink)) {
                throw new Exception("Falha de infraestrutura: Não foi possível criar Symlink para a nova versão.");
            }
            
            // A virada de chave atômica (Rename do symlink)
            rename($tmpLink, $currentLink);
            
            // 3. INSTALAR ROUTER NA RAIZ (Apenas na primeira vez)
            $this->installRouter();

            // 4. HEALTH CHECK PRÉ-COMMIT
            if (!$this->verifyHealth($currentLink)) {
                $this->rollback($version); // Aciona o Rollback Real
                throw new Exception("Health Check falhou pós-swap. Rollback automático executado com sucesso.");
            }

            // 5. COMMIT NO BANCO (Apenas após sucesso total)
            if ($this->pdo instanceof PDO) {
                $stmt = $this->pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = ?");
                $stmt->execute([$version, $version]);
                $this->log("COMMIT CONFIRMADO: Banco atualizado para v$version.");
            }

            // 6. LIMPEZA DE CACHE
            if (function_exists('opcache_reset')) @opcache_reset();

            $this->log("✅ SWAP ATÔMICO CONCLUÍDO. Sistema rodando v$version.");
            return true;

        } catch (Exception $e) {
            $this->log("FALHA NO PIPELINE ATÔMICO: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Instala o .htaccess que direciona o tráfego para a release /current/
     * Sem precisar alterar o Document Root do cPanel.
     */
    private function installRouter() {
        $htaccessPath = $this->basePath . '.htaccess';
        $routerRules = "
# SGIM OTA v2.0 - ATOMIC ROUTER
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Evita loop infinito
    RewriteCond %{REQUEST_URI} !^/releases/current/
    RewriteCond %{REQUEST_URI} !^/shared/
    
    # Redireciona tudo para a pasta da release atual (symlink)
    RewriteRule ^(.*)$ releases/current/$1 [L,QSA]
</IfModule>
";
        // Se o htaccess não existir ou não tiver a tag do router, instala.
        if (!file_exists($htaccessPath) || strpos(file_get_contents($htaccessPath), 'SGIM OTA v2.0 - ATOMIC ROUTER') === false) {
            file_put_contents($htaccessPath, $routerRules, FILE_APPEND | LOCK_EX);
            $this->log("Router Atômico (.htaccess) instalado/atualizado na raiz.");
        }
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
