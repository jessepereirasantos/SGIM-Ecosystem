<?php
namespace App\Updater;

use PDO;
use Exception;
use ZipArchive;

class UpdaterCore
{
    private $db;
    private $apiUrl = "https://escolateologicaeloha.com.br/"; // Master Oficial
    private $licenseKey;
    private $currentVersion;
    private $tempDir;
    private $rollbackDir;
    private $appRoot;

    public function __construct(PDO $db, $licenseKey, $currentVersion)
    {
        $this->db = $db;
        $this->licenseKey = $licenseKey;
        $this->currentVersion = $currentVersion;

        // RESOLUÇÃO DE RAIZ COM TRAVA DE SEGURANÇA (OBRIGATÓRIO)
        // __DIR__ é src/Updater/, então ../../ é a raiz da aplicação
        $this->appRoot = realpath(__DIR__ . '/../../');
        
        // PROVA DE SEGURANÇA: A raiz DEVE conter o config/db.php
        if (!$this->appRoot || !file_exists($this->appRoot . '/config/db.php')) {
            error_log("[SGIM-OTA] [ERRO FATAL] Raiz da aplicação não validada em: " . ($this->appRoot ?: 'Caminho Inválido'));
            throw new Exception("Erro de Integridade: O motor OTA não conseguiu validar a pasta raiz da aplicação.");
        }

        $this->tempDir    = $this->appRoot . '/backups/temp_update/';
        $this->rollbackDir = $this->appRoot . '/backups/rollback/';

        if (!is_dir($this->tempDir))
            @mkdir($this->tempDir, 0755, true);
        if (!is_dir($this->rollbackDir))
            @mkdir($this->rollbackDir, 0755, true);
    }

    /**
     * Verifica e Diagnostica a conexão com a API Master
     */
    public function checkForUpdate()
    {
        if (empty($this->master_url_configured())) {
            $this->autodetectMasterUrl();
        }

        $domain = $_SERVER['HTTP_HOST'];
        $url = $this->apiUrl . "api/update/v2/check.php?version=" . urlencode($this->current_version_configured()) . "&license_key=" . urlencode($this->licenseKey) . "&domain=" . urlencode($domain) . "&t=" . time();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error_msg = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno > 0) {
            return $this->diagnoseConnection($errno, $error_msg);
        }

        if ($httpCode !== 200 || !$response) {
            return ['success' => false, 'message' => "Servidor Master retornou erro HTTP $httpCode."];
        }

        $data = json_decode($response, true);
        return $data ?: ['success' => false, 'message' => "Resposta inválida do Master."];
    }

    private function diagnoseConnection($errno, $msg)
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);
        $diagnostic = "Erro de conexão ($errno): $msg";

        if (!gethostbyname($host) || gethostbyname($host) === $host) {
            $diagnostic = "DOMÍNIO NÃO ENCONTRADO: O servidor não localizou '$host'.";
        } else {
            $connection = @fsockopen($host, 443, $error_code, $error_msg, 5);
            if (!$connection) {
                $diagnostic = "SERVIDOR INACESSÍVEL: Master '$host' bloqueou a conexão (Firewall).";
            } else {
                fclose($connection);
                $diagnostic = "FALHA SSL/TIMEOUT: Conexão física OK, falha na negociação SSL.";
            }
        }
        return ['success' => false, 'message' => $diagnostic];
    }

    private function master_url_configured()
    {
        $stmt = $this->db->prepare("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
        $stmt->execute();
        $res = $stmt->fetch();
        return $res['valor'] ?? '';
    }

    private function current_version_configured()
    {
        $stmt = $this->db->prepare("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $stmt->execute();
        $res = $stmt->fetch();
        return $res['valor'] ?? '1.1.0';
    }

    private function autodetectMasterUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // Sempre priorizar o domínio oficial se estivermos no ambiente do projeto
        $newUrl = "https://escolateologicaeloha.com.br/";

        $this->apiUrl = $newUrl;
        $this->db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'master_url'")->execute([$newUrl]);
        error_log("[SGIM-OTA] [AUTO-CONFIG] Master URL redefinida para: $newUrl");
    }

    public function update($latestVersion, $updateUrl, $checksum, $migrations = [])
    {
        $lockFile = $this->appRoot . '/updating.lock';

        if (file_exists($lockFile)) {
            throw new Exception("Uma atualização já está em andamento.");
        }
        file_put_contents($lockFile, time());

        // LOG DE SEGURANÇA MANDATÓRIO
        error_log("[SGIM-OTA] [INÍCIO] Iniciando motor v{$latestVersion}");
        error_log("[SGIM-OTA] [RAIZ] ROOT_PATH = " . $this->appRoot);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $zipPath = $this->tempDir . "update_{$latestVersion}.zip";
        $extractPath = $this->tempDir . "files/";

        try {
            // 1. Download do ZIP
            $finalUrl = (strpos($updateUrl, 'http') === 0) ? $updateUrl : $this->apiUrl . $updateUrl;
            error_log("[SGIM-OTA] Download: $finalUrl");
            if (!$this->downloadFile($finalUrl, $zipPath)) {
                throw new Exception("Falha ao baixar atualização.");
            }

            // 2. Extrair
            if (is_dir($extractPath)) $this->recursiveDelete($extractPath);
            @mkdir($extractPath, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new Exception("Erro ao extrair ZIP.");
            }

            // 3. VALIDAÇÃO PRÉVIA (OBRIGATÓRIO)
            if (!is_dir($extractPath) || count(scandir($extractPath)) <= 2) {
                throw new Exception("Pacote vazio.");
            }
            if (!file_exists($extractPath . 'index.php') && !file_exists($extractPath . 'dashboard.php')) {
                throw new Exception("Arquivos críticos não encontrados no pacote.");
            }

            // 4. APLICAÇÃO SEGURA (SWAP CONTROLADO)
            error_log("[SGIM-OTA] [SWAP] Iniciando aplicação em: " . $this->appRoot);
            $this->safeSwap($extractPath, $this->appRoot);

            // 5. PROVA OBRIGATÓRIA (Escrita Real na Raiz)
            $proofFile = $this->appRoot . '/teste_ota.txt';
            $proofContent = "OTA EXECUÇÃO REAL v{$latestVersion} | DATA: " . date('d/m/Y H:i:s') . " | RAIZ: " . $this->appRoot;
            if (!file_put_contents($proofFile, $proofContent)) {
                throw new Exception("FALHA DE ESCRITA REAL na raiz da aplicação.");
            }
            error_log("[SGIM-OTA] [PROVA] Arquivo de prova criado: $proofFile");

            // 7. Atualizar Versão Local
            $this->updateLocalVersion($latestVersion);

            // 8. LIMPEZA ATÔMICA DE NOTIFICAÇÕES (NOVIDADE)
            // Marcar como lidas todas as notificações de "Nova Versão"
            $this->db->prepare("UPDATE sistema_novidades SET visto = 1 WHERE titulo LIKE 'Nova Versão Disponível%'")->execute();
            error_log("[SGIM-OTA] [SININHO] Notificações de atualização limpas.");

            // Sucesso! Limpar Lock e Temp
            @unlink($lockFile);
            $this->recursiveDelete($this->tempDir);
            return true;

        } catch (Exception $e) {
            @unlink($lockFile);
            error_log("[SGIM-OTA] [ERRO] " . $e->getMessage());
            throw $e;
        }
    }

    private function safeSwap($src, $dst)
    {
        if (!is_dir($src)) return;
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }

        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                $this->safeSwap($srcPath, $dstPath);
            } else {
                if (strpos($dstPath, 'config/db.php') !== false) continue;

                // LOG OBRIGATÓRIO DE CADA ARQUIVO
                error_log("[SGIM-OTA] APPLY: $srcPath -> $dstPath");

                if (file_exists($dstPath)) {
                    @rename($dstPath, $dstPath . '.bak');
                }

                if (!@rename($srcPath, $dstPath)) {
                    if (!@copy($srcPath, $dstPath)) {
                        error_log("[SGIM-OTA] [ERRO ESCRITA] Falha em: $dstPath");
                        throw new Exception("Falha ao aplicar: $file");
                    }
                }
            }
        }
        closedir($dir);
    }

    private function recursiveDelete($dir)
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    private function downloadFile($url, $path)
    {
        $ch = curl_init($url);
        $fp = fopen($path, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $exec = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        return $exec;
    }

    private function updateLocalVersion($newVersion)
    {
        $this->db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'versao_sistema'")->execute([$newVersion]);
        @file_put_contents($this->appRoot . '/version.json', json_encode(['version' => $newVersion, 'date' => date('Y-m-d')], JSON_PRETTY_PRINT));
    }

    public function setApiUrl($url) { $this->apiUrl = rtrim($url, '/') . '/'; }
}
