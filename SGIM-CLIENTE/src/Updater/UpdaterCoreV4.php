<?php
namespace App\Updater;

use PDO;
use Exception;
use ZipArchive;

/**
 * UpdaterCore v4.0 - Motor de Atualização SGIM SaaS
 * Foco: Automação Total, Segurança e Resiliência.
 */
class UpdaterCoreV4
{
    private $db;
    private $versionManager;
    private $apiUrl;
    private $licenseKey;
    private $tempDir;
    private $rollbackDir;
    private $snapshotDir;
    private $logFile;
    private $lockFile;

    // Diretórios Protegidos (Nunca sobrescrever ou deletar)
    private $protectedDirs = ['uploads', 'config', 'backups'];

    public function __construct(PDO $db, $licenseKey, $apiUrl)
    {
        $this->db = $db;
        $this->versionManager = new VersionManager();
        $this->licenseKey = $licenseKey;
        $this->apiUrl = rtrim($apiUrl, '/') . '/';

        $this->tempDir = __DIR__ . '/../../backups/temp_update/';
        $this->rollbackDir = __DIR__ . '/../../backups/rollback/';
        $this->snapshotDir = __DIR__ . '/../../backups/snapshots/';
        $this->logFile     = __DIR__ . '/../../backups/update_log.txt';
        $this->lockFile    = __DIR__ . '/../../updating.lock';

        if (!is_dir($this->tempDir))
            @mkdir($this->tempDir, 0755, true);
        if (!is_dir($this->rollbackDir))
            @mkdir($this->rollbackDir, 0755, true);
        if (!is_dir($this->snapshotDir))
            @mkdir($this->snapshotDir, 0755, true);
    }

    /**
     * Orquestrador Principal: Check Automático -> Download -> Validação -> Preparação
     */
    public function checkAndPrepare()
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        try {
            $this->acquireLock();

            // 1. Fase de Check Automático
            $updateInfo = $this->checkMaster();
            if (!$updateInfo['has_update']) {
                $this->releaseLock();
                return [
                    'status' => 'success',
                    'stage' => 'check',
                    'message' => 'O sistema já está na versão mais recente.',
                    'has_update' => false
                ];
            }

            $latestVersion = $updateInfo['latest_version'];
            $updateUrl = $updateInfo['update_url'];
            $expectedChecksum = $updateInfo['checksum'];

            // 2. Fase de Download
            $zipPath = $this->tempDir . "sgim_v{$latestVersion}.zip";
            if (!$this->downloadPackage($updateUrl, $zipPath)) {
                throw new Exception("Falha ao baixar o pacote de atualização da URL: $updateUrl", 2); // Stage 2 = Download
            }

            // 3. Fase de Validação de Integridade
            if (!$this->validateIntegrity($zipPath, $expectedChecksum)) {
                @unlink($zipPath);
                throw new Exception("Falha na integridade do arquivo. Checksum divergente.", 3); // Stage 3 = Validation
            }

            // 4. Fase de Extração (Preparação para Deploy)
            $extractPath = $this->tempDir . "extracted/";
            $this->extractToTemp($zipPath, $extractPath);

            $this->releaseLock();

            // Retorno Padronizado de Sucesso
            return [
                'status' => 'success',
                'stage' => 'preparation',
                'message' => "Versão {$latestVersion} baixada e validada. Pronta para deploy.",
                'latest_version' => $latestVersion,
                'changelog' => $updateInfo['changelog'] ?? [],
                'migrations' => $updateInfo['migrations'] ?? [],
                'temp_path' => $extractPath
            ];

        } catch (Exception $e) {
            $this->releaseLock();

            $stages = [1 => 'check', 2 => 'download', 3 => 'validation', 4 => 'extraction'];
            return [
                'status' => 'error',
                'stage' => $stages[$e->getCode()] ?? 'process',
                'message' => $e->getMessage()
            ];
        }
    }

    public function executeDeploy($latestVersion) {
        set_time_limit(600);
        $this->log("--- Iniciando Deploy v{$latestVersion} ---");
        
        $snapshotInfo = null;
        try {
            // 1. Snapshot Obrigatório
            $this->log("Gerando snapshot de segurança...");
            $snapshotInfo = $this->createSnapshot($latestVersion);
            $this->log("Snapshot gerado: " . $snapshotInfo['file']);

            // 2. Obter lista de arquivos do simulador
            $plan = $this->simulateDeploy();
            $filesToProcess = array_merge($plan['to_overwrite'], $plan['to_create']);

            // 3. Aplicação Atômica Arquivo por Arquivo
            foreach ($filesToProcess as $file) {
                $this->log("Aplicando: $file");
                if (!$this->applyFileUpdate($file)) {
                    throw new Exception("Erro ao aplicar arquivo: $file");
                }
            }

            $this->log("Deploy de arquivos concluído com sucesso.");

            // 4. Migração de Dados do Sistema (Etapa 4.5)
            $this->runSystemMigrations();

            $this->log("--- Deploy v{$latestVersion} Finalizado com Sucesso ---");
            return [
                'status'  => 'success',
                'message' => "Sistema atualizado para v{$latestVersion} com sucesso.",
                'snapshot' => $snapshotInfo['file']
            ];

        } catch (Exception $e) {
            $this->log("ERRO CRÍTICO NO DEPLOY: " . $e->getMessage());
            
            // 4. Rollback Automático
            if ($snapshotInfo) {
                $this->log("Iniciando ROLLBACK automático...");
                $this->restoreSnapshot($snapshotInfo['file']);
                $this->log("Rollback concluído. Sistema restaurado.");
            }

            throw new Exception("Falha no Deploy: " . $e->getMessage() . ". O sistema foi restaurado.");
        }
    }

    /**
     * ETAPA 4.5: Migrações de Dados (Sistema)
     * Realiza inserções no padrão Key-Value com validação de persistência.
     */
    private function runSystemMigrations() {
        $this->log("Iniciando migração de dados do sistema...");
        try {
            $timestamp = date('Y-m-d H:i:s');
            
            $stmt = $this->db->prepare("
                INSERT INTO configuracoes (chave, valor) 
                VALUES ('last_ota_sync', ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)
            ");
            
            $success = $stmt->execute([$timestamp]);

            if ($success) {
                $verify = $this->db->prepare("SELECT valor FROM configuracoes WHERE chave = 'last_ota_sync'");
                $verify->execute();
                $savedValue = $verify->fetchColumn();

                if ($savedValue === $timestamp) {
                    $this->log("SUCESSO: Migration 'last_ota_sync' persistida e validada: $savedValue");
                } else {
                    $this->log("AVISO: Falha na validação de persistência. Esperado: $timestamp | Gravado: $savedValue");
                }
            } else {
                $this->log("AVISO: O banco de dados não retornou sucesso na query de migration.");
            }
        } catch (Exception $e) {
            $this->log("AVISO: Erro de banco de dados na migration: " . $e->getMessage());
        }
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
    /**
     * ETAPA 4.1: Gera um backup ZIP do sistema atual (Snapshot)
     * Respeita rigorosamente a Whitelist de diretórios protegidos.
     */
    public function createSnapshot($version)
    {
        $zipPath = $this->snapshotDir . "pre_update_{$version}_" . date('Ymd_His') . ".zip";
        $rootPath = realpath(__DIR__ . '/../../');

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Falha ao criar o arquivo de snapshot ZIP.");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $count = 0;
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath); // Normaliza para Unix style

                // Validação de Whitelist (Não incluir pastas protegidas no backup)
                $skip = false;
                foreach ($this->protectedDirs as $pDir) {
                    if (strpos($relativePath, $pDir . '/') === 0 || $relativePath === $pDir) {
                        $skip = true;
                        break;
                    }
                }

                // Ignora arquivos de trava, configurações de servidor e o próprio snapshot
                if (
                    strpos($relativePath, 'backups/') === 0 ||
                    $relativePath === 'updating.lock' ||
                    $relativePath === 'ota.php' ||
                    $relativePath === 'diagnostico_chave.php' ||
                    $relativePath === 'test_snapshot.php' ||
                    $relativePath === '.htaccess' ||
                    $relativePath === '.installed'
                ) {
                    $skip = true;
                }

                if (!$skip) {
                    $zip->addFile($filePath, $relativePath);
                    $count++;
                }
            }
        }

        $zip->close();
        return [
            'status' => 'success',
            'file' => basename($zipPath),
            'count' => $count,
            'path' => $zipPath
        ];
    }

    /**
     * ETAPA 4.2: Simulação de Deploy (Apenas Leitura)
     * Lista o que seria alterado sem realizar nenhuma escrita.
     */
    public function simulateDeploy()
    {
        $extractPath = realpath($this->tempDir . 'extracted/');
        $rootPath = realpath(__DIR__ . '/../../');

        if (!is_dir($extractPath)) {
            throw new Exception("Pasta de extração não localizada. Execute a Etapa 3 primeiro.");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $report = [
            'to_overwrite' => [],
            'to_create' => [],
            'blocked' => []
        ];

        foreach ($files as $name => $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($extractPath) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            $targetPath = $rootPath . '/' . $relativePath;

            // Verificação de Segurança (Whitelist)
            $isProtected = false;
            foreach ($this->protectedDirs as $pDir) {
                if (strpos($relativePath, $pDir . '/') === 0 || $relativePath === $pDir || $relativePath === '.htaccess' || $relativePath === '.installed') {
                    $isProtected = true;
                    break;
                }
            }

            if ($isProtected) {
                $report['blocked'][] = $relativePath;
                continue;
            }

            if (file_exists($targetPath)) {
                $report['to_overwrite'][] = $relativePath;
            } else {
                $report['to_create'][] = $relativePath;
            }
        }

        return $report;
    }
    /**
     * ETAPA 4.3: Aplica a atualização de um arquivo de forma atômica
     * Usa a técnica de .tmp + rename() para garantir integridade.
     */
    public function applyFileUpdate($relativePath)
    {
        $extractPath = realpath($this->tempDir . 'extracted/');
        $rootPath = realpath(__DIR__ . '/../../');

        $sourceFile = $extractPath . '/' . $relativePath;
        $targetFile = $rootPath . '/' . $relativePath;
        $tempFile = $targetFile . '.tmp';

        if (!file_exists($sourceFile)) {
            throw new Exception("Arquivo de origem não encontrado: $relativePath");
        }

        // 1. Verificar Whitelist (Última barreira de segurança antes da escrita)
        foreach ($this->protectedDirs as $pDir) {
            if (strpos($relativePath, $pDir . '/') === 0 || $relativePath === $pDir || $relativePath === '.htaccess' || $relativePath === '.installed') {
                throw new Exception("Tentativa de sobrescrever arquivo protegido pela Whitelist: $relativePath");
            }
        }

        // 2. Validação e Tratamento Especial de Dados
        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        if ($extension === 'json') {
            $newContent = file_get_contents($sourceFile);
            $newData = json_decode($newContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Integridade Violada: Arquivo $relativePath contém JSON inválido no pacote.");
            }

            // Se for o arquivo de versão, fazemos MERGE em vez de OVERWRITE
            if ($relativePath === 'version.json') {
                $currentData = file_exists($targetFile) ? json_decode(file_get_contents($targetFile), true) : [];
                if (!is_array($currentData)) $currentData = [];

                // O merge recursivo garante que o que vem do Master (versão, build) atualize,
                // mas o que é do Cliente (metadados locais) permaneça intacto em todos os níveis.
                $finalData = array_replace_recursive($currentData, $newData);
                $finalContent = json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                
                if (!@file_put_contents($tempFile, $finalContent)) {
                    throw new Exception("Falha ao preparar merge do version.json.");
                }
            }
        }

        // 3. Tenta copiar para arquivo temporário (.tmp) se não for o version.json (que já foi preparado acima)
        if ($relativePath !== 'version.json' && !@copy($sourceFile, $tempFile)) {
            throw new Exception("Falha de permissão ao criar arquivo temporário: $relativePath.tmp");
        }

        // 3. Rename Atômico (O ponto crítico onde a troca acontece)
        if (!@rename($tempFile, $targetFile)) {
            @unlink($tempFile);
            throw new Exception("Falha ao renomear arquivo temporário para o destino final.");
        }

        return true;
    }

    private function checkMaster()
    {
        // Detecta o domínio completo (incluindo subpasta se existir)
        $domain = $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        $params = [
            'license_key' => $this->licenseKey,
            'domain' => $domain,
            'current_version' => $this->versionManager->getVersion(),
            'channel' => $this->versionManager->getChannel()
        ];

        $url = $this->apiUrl . "api/update/v2/check.php?" . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'SGIM-OTA-v4'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            throw new Exception("Falha ao consultar o Servidor Master (HTTP $httpCode).", 1);
        }

        $data = json_decode($response, true);
        if (!$data || !$data['success']) {
            throw new Exception($data['message'] ?? "Resposta inválida do Master.", 1);
        }

        return $data;
    }

    private function acquireLock()
    {
        if (file_exists($this->lockFile)) {
            $lockTime = file_get_contents($this->lockFile);
            if (time() - $lockTime < 600)
                throw new Exception("Atualização em andamento.", 0);
        }
        file_put_contents($this->lockFile, time());
    }

    private function releaseLock()
    {
        if (file_exists($this->lockFile))
            @unlink($this->lockFile);
    }

    private function downloadPackage($updateUrl, $savePath)
    {
        // Detecta o domínio completo para o download
        $domain = $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        // Se a URL já for absoluta (começar com http), usamos ela diretamente.
        // Caso contrário, montamos a URL e anexamos os parâmetros.
        if (strpos($updateUrl, 'http') === 0) {
            $fullUrl = $updateUrl;
        } else {
            $fullUrl = $this->apiUrl . ltrim($updateUrl, '/');
            if (strpos($fullUrl, '?') === false)
                $fullUrl .= '?';
            $fullUrl .= "&license_key=" . urlencode($this->licenseKey) . "&domain=" . urlencode($domain);
        }

        $ch = curl_init($fullUrl);
        $fp = fopen($savePath, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        return ($result && $httpCode === 200);
    }

    private function validateIntegrity($filePath, $expectedHash)
    {
        if (empty($expectedHash) || $expectedHash === 'SIMULATED_HASH')
            return true;
        return (strcasecmp(hash_file('sha256', $filePath), $expectedHash) === 0);
    }

    private function extractToTemp($zipPath, $destPath)
    {
        if (is_dir($destPath))
            $this->recursiveDelete($destPath);
        @mkdir($destPath, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($destPath);
            $zip->close();
            @unlink($zipPath);
        } else {
            throw new Exception("Falha ao extrair pacote temporário.", 4);
        }
    }

    private function recursiveDelete($dir)
    {
        if (!is_dir($dir))
            return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : @unlink("$dir/$file");
        }
        return @rmdir($dir);
    }
}
?>