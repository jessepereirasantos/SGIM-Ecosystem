<?php
namespace SGIM\OTA\Migrations;

use Exception;
use PDO;

class Migration_v1_5_1_RaizCopier {
    /**
     * Executa a migração física dos arquivos do release para a raiz do site do cliente.
     * Isso resolve o problema de servidores cPanel onde o document root aponta para a raiz
     * e os arquivos físicos originais não eram atualizados via Symlink.
     */
    public function up(PDO $pdo) {
        $basePath = realpath(__DIR__ . '/../../../');
        $logFile = $basePath . '/shared/system/logs/migrations.log';
        
        $this->log($logFile, "Iniciando migração física de raiz (v1.5.1)...");

        if (!$basePath) {
            throw new Exception("Falha ao detectar a raiz do site no Copier.");
        }

        $releasesDir = $basePath . '/releases';
        if (!is_dir($releasesDir)) {
            $this->log($logFile, "Aviso: Diretório de releases não encontrado.");
            return;
        }

        // Acha todas as pastas de versão (releases/v*)
        $versions = [];
        foreach (scandir($releasesDir) as $file) {
            if ($file[0] === 'v' && is_dir($releasesDir . '/' . $file)) {
                $versions[] = substr($file, 1);
            }
        }
        
        if (empty($versions)) {
            $this->log($logFile, "Aviso: Nenhuma versão de release encontrada.");
            return;
        }

        // Ordena versões e pega a mais alta (que é o release sendo instalado)
        usort($versions, 'version_compare');
        $latestVersion = end($versions);
        $sourceDir = $releasesDir . '/v' . $latestVersion;

        $this->log($logFile, "Copiando arquivos do release v{$latestVersion} ({$sourceDir}) para a raiz ({$basePath})...");

        if (!is_dir($sourceDir)) {
            throw new Exception("Diretório de origem do release não existe: $sourceDir");
        }

        // Lista de exclusões (arquivos e pastas sensíveis)
        $ignore = [
            'db_config.php', 
            '.installed', 
            'config/db_config.php', 
            'config/database.php',
            'api/update/packages/', 
            '.git',
            'backups',
            'shared',
            'releases'
        ];

        $copiedCount = 0;
        $failedCount = 0;
        
        $this->copyRecursive($sourceDir, $basePath, $ignore, $copiedCount, $failedCount, $logFile);
        
        $this->log($logFile, "Cópia concluída: {$copiedCount} arquivos copiados, {$failedCount} falhas.");
        
        // Garante as permissões de usuários no banco de dados do cliente
        try {
            $pdo->exec("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
                       ('usuarios', 'visualizar', 'Visualizar Usuários'),
                       ('usuarios', 'gerenciar', 'Gerenciar Usuários')");
            
            $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
                       SELECT 1, id FROM permissoes WHERE modulo = 'usuarios'");
            
            $this->log($logFile, "Permissões de usuários semeadas no banco com sucesso.");
        } catch (\Throwable $dbEx) {
            $this->log($logFile, "Aviso ao semear permissões: " . $dbEx->getMessage());
        }
    }

    private function copyRecursive($src, $dst, $ignore, &$copiedCount, &$failedCount, $logFile) {
        if (!is_dir($src)) return;
        if (!is_dir($dst)) {
            if (!@mkdir($dst, 0755, true)) {
                $this->log($logFile, "Falha ao criar pasta: $dst");
                $failedCount++;
                return;
            }
        }

        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;

                // Ignora caminhos da lista estática
                if (in_array($file, $ignore)) {
                    continue;
                }

                // Verifica subpastas com caminho relativo
                $relative = ltrim(str_replace($src, '', $srcPath), '/');
                foreach ($ignore as $ig) {
                    if (strpos($relative, $ig) === 0) {
                        continue 2;
                    }
                }

                if (is_dir($srcPath)) {
                    $this->copyRecursive($srcPath, $dstPath, $ignore, $copiedCount, $failedCount, $logFile);
                } else {
                    if (@copy($srcPath, $dstPath)) {
                        $copiedCount++;
                    } else {
                        $this->log($logFile, "Falha ao copiar: {$file} para {$dstPath}");
                        $failedCount++;
                    }
                }
            }
        }
        closedir($dir);
    }

    private function log($logFile, $message) {
        $entry = "[" . date('Y-m-d H:i:s') . "] [RaizCopier] " . $message . "\n";
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
