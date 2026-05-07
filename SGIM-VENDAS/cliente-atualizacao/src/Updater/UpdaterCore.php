<?php
namespace App\Updater;

use PDO;
use Exception;
use ZipArchive;

class UpdaterCore
{
    private $db;
    private $apiUrl = "https://vendas.sgim.com.br/"; // Será ajustada dinamicamente
    private $licenseKey;
    private $currentVersion;
    private $tempDir;

    public function __construct(PDO $db, $licenseKey, $currentVersion)
    {
        $this->db = $db;
        $this->licenseKey = $licenseKey;
        $this->currentVersion = $currentVersion;
        $this->tempDir    = __DIR__ . '/../../backups/temp_update/';
        $this->rollbackDir = __DIR__ . '/../../backups/rollback/';


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
        // Autodetectar URL se estiver vazia (Inteligência SaaS 3.2)
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
            return ['success' => false, 'message' => "Servidor Master retornou erro HTTP $httpCode. Verifique se o arquivo check.php existe no Master."];
        }

        $data = json_decode($response, true);
        return $data ?: ['success' => false, 'message' => "Resposta inválida do Master (JSON Error)."];
    }

    private function diagnoseConnection($errno, $msg)
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);
        $diagnostic = "Erro de conexão ($errno): $msg";

        // Teste de DNS
        if (!gethostbyname($host) || gethostbyname($host) === $host) {
            $diagnostic = "DOMÍNIO NÃO ENCONTRADO: O servidor não conseguiu localizar o endereço '$host'. Verifique se a URL em Configurações está correta.";
        } else {
            // Teste de Porta
            $connection = @fsockopen($host, 443, $error_code, $error_msg, 5);
            if (!$connection) {
                $diagnostic = "SERVIDOR INACESSÍVEL: O Master '$host' está online mas bloqueou a conexão do seu site. Isso pode ser um Firewall no seu servidor ou no Master.";
            } else {
                fclose($connection);
                $diagnostic = "FALHA SSL/TIMEOUT: A conexão física existe, mas falhou ao negociar dados. Verifique o certificado SSL do Master.";
            }
        }

        return ['success' => false, 'message' => $diagnostic, 'diagnostic_code' => $errno];
    }

    private function master_url_configured()
    {
        // Lógica para pegar do banco local sem depender de injeção manual constante
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
        // Inteligência 3.3: Detecta o domínio do ambiente atual
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];

        // Se o usuário estiver no domínio principal, o master é ele mesmo
        // Se estiver em outro, tentamos inferir ou mantemos o que foi configurado
        if (strpos($host, 'escolateologicaeloha.com.br') !== false) {
            $newUrl = $protocol . "escolateologicaeloha.com.br/";
        } else {
            // Padrão de segurança se nada for detectado
            $newUrl = "https://escolateologicaeloha.com.br/";
        }

        $this->apiUrl = $newUrl;
        $this->db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'master_url'")->execute([$newUrl]);
    }

    public function update($latestVersion, $updateUrl, $checksum, $migrations = [])
    {
        $lockFile = __DIR__ . '/../../updating.lock';

        // 0. Lock e Limites
        if (file_exists($lockFile)) {
            throw new Exception("Uma atualização já está em andamento. Aguarde alguns minutos.");
        }
        file_put_contents($lockFile, time());

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Verificar permissão de escrita no diretório temp ANTES de tudo
        @mkdir($this->tempDir, 0755, true);
        if (!is_writable($this->tempDir)) {
            @unlink($lockFile);
            throw new Exception("Sem permissão de escrita no diretório temp: {$this->tempDir}. Verifique as permissões do HostGator.");
        }
        error_log("[SGIM-OTA] [ENGINE] Temp dir OK: {$this->tempDir}");

        $zipPath = $this->tempDir . "update_{$latestVersion}.zip";
        $extractPath = $this->tempDir . "files/";

        try {
            // 1. Download do ZIP
            // Se $updateUrl já for uma URL absoluta, usá-la diretamente
            $finalUrl = (strpos($updateUrl, 'http') === 0) ? $updateUrl : $this->apiUrl . $updateUrl;
            error_log("OTA: Iniciando download de $finalUrl");
            if (!$this->downloadFile($finalUrl, $zipPath)) {
                throw new Exception("Falha ao baixar o pacote de atualização de: $finalUrl");
            }

            // 2. Validar Tamanho Mínimo (ZIP não pode estar vazio)
            $zipSize = filesize($zipPath);
            error_log("OTA: Download concluído. Tamanho: $zipSize bytes");
            if ($zipSize < 100) {
                throw new Exception("Arquivo de atualização inválido ou vazio ($zipSize bytes). Verifique se o pacote foi publicado corretamente no Master.");
            }

            // 3. Extrair para Pasta TEMP
            error_log("OTA: Extraindo para $extractPath");
            if (is_dir($extractPath))
                $this->recursiveDelete($extractPath);
            @mkdir($extractPath, 0755, true);

            $zip = new ZipArchive();
            $zipRes = $zip->open($zipPath);
            if ($zipRes === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
                error_log("OTA: Extração concluída com sucesso.");
            } else {
                throw new Exception("Erro ao extrair pacote ZIP (Código: $zipRes). Verifique permissões de escrita.");
            }

            // 5. Mover Arquivos TEMP → RAIZ CORRETA da aplicação
            $appRoot = realpath(__DIR__ . '/../../');
            error_log("[SGIM-OTA] [ENGINE] App Root resolvido: $appRoot");
            if (!$appRoot || !is_dir($appRoot)) {
                throw new Exception("Diretório raiz da aplicação não encontrado: " . __DIR__ . '/../../');
            }
            error_log("[SGIM-OTA] [ENGINE] Copiando de $extractPath para $appRoot");
            $this->recursiveCopy($extractPath, $appRoot);

            // 6. Executar Migrations Versionadas
            if (!empty($migrations)) {
                $manager = new MigrationManager($this->db);
                // Migrations agora é esperado vir como array no JSON da API
                $manager->runMigrations($latestVersion, $migrations);
            }

            // 7. Atualizar Versão Local
            $this->updateLocalVersion($latestVersion);

            // Sucesso! Limpar Lock e Temp
            @unlink($lockFile);
            $this->recursiveDelete($this->tempDir);
            return true;

        } catch (Exception $e) {
            @unlink($lockFile);
            error_log("Update Critical Failure: " . $e->getMessage());
            throw $e;
        }
    }

    private function recursiveCopy($src, $dst)
    {
        if (!is_dir($src)) {
            error_log("[SGIM-OTA] [COPY] ERRO: Origem não existe: $src");
            return;
        }

        if (!is_dir($dst)) {
            if (!@mkdir($dst, 0755, true)) {
                error_log("[SGIM-OTA] [COPY] ERRO: Não foi possível criar diretório: $dst");
                return;
            }
        }

        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;

            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            if (is_dir($srcFile)) {
                $this->recursiveCopy($srcFile, $dstFile);
            } else {
                // Não sobrescrever arquivos de configuração do cliente!
                if (strpos($dstFile, 'config/db.php') !== false) {
                    error_log("[SGIM-OTA] [COPY] Pulando config protegido: $dstFile");
                    continue;
                }
                if (@copy($srcFile, $dstFile)) {
                    error_log("[SGIM-OTA] [COPY] OK: $dstFile");
                } else {
                    error_log("[SGIM-OTA] [COPY] FALHOU: $dstFile");
                }
            }
        }
        closedir($dir);
    }

    private function recursiveDelete($dir)
    {
        if (!is_dir($dir))
            return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Download robusto com diagnóstico e fallback automático.
     * Tenta cURL primeiro. Se falhar (HTTP 0, timeout, etc), tenta file_get_contents.
     */
    private function downloadFile($url, $path)
    {
        // Garantir que o diretório temp existe
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new Exception("Não foi possível criar o diretório temporário: $dir. Verifique as permissões da pasta backups/.");
            }
        }

        $downloaded = false;
        $lastError = '';

        // -----------------------------------------------
        // TENTATIVA 1: cURL (mais eficiente para arquivos grandes)
        // -----------------------------------------------
        if (function_exists('curl_init')) {
            $fp = @fopen($path, 'w+');
            if ($fp) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_FILE => $fp,
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT => 'SGIM-OTA/4.3',
                ]);
                $execOk = curl_exec($ch);
                $errno = curl_errno($ch);
                $errMsg = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                fclose($fp);

                if (!$execOk || $errno > 0) {
                    $lastError = "cURL falhou (erro $errno): $errMsg";
                    @unlink($path);
                } elseif ($httpCode !== 200) {
                    $content = @file_get_contents($path);
                    $data = json_decode($content, true);
                    $lastError = $data['message'] ?? "Servidor Master retornou HTTP $httpCode.";
                    @unlink($path);
                } elseif (strpos($ctype ?? '', 'application/json') !== false) {
                    // Master retornou JSON com erro (ex: licença inválida)
                    $content = @file_get_contents($path);
                    $data = json_decode($content, true);
                    if (isset($data['success']) && !$data['success']) {
                        @unlink($path);
                        throw new Exception($data['message'] ?? "Master recusou o download.");
                    }
                    $downloaded = true;
                } else {
                    $downloaded = true;
                }
            } else {
                $lastError = "Não foi possível criar arquivo temporário em $path.";
            }
        } else {
            $lastError = "cURL não está instalado neste servidor.";
        }

        // -----------------------------------------------
        // TENTATIVA 2: file_get_contents (fallback para servidores sem cURL ou com firewall)
        // -----------------------------------------------
        if (!$downloaded) {
            error_log("OTA: cURL falhou ($lastError). Tentando file_get_contents...");
            $context = stream_context_create([
                'http' => [
                    'timeout' => 120,
                    'user_agent' => 'SGIM-OTA/4.3',
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $data = @file_get_contents($url, false, $context);
            if ($data !== false && strlen($data) > 100) {
                // Verificar se não é um JSON de erro
                $decoded = json_decode($data, true);
                if (isset($decoded['success']) && !$decoded['success']) {
                    throw new Exception($decoded['message'] ?? "Master recusou o download (fallback).");
                }
                if (@file_put_contents($path, $data) !== false) {
                    $downloaded = true;
                } else {
                    $lastError .= " | Falha ao gravar arquivo via fallback.";
                }
            } else {
                $lastError .= " | file_get_contents também falhou ou retornou dados inválidos.";
            }
        }

        if (!$downloaded) {
            throw new Exception("Falha no download do pacote de atualização. Detalhe: $lastError");
        }

        return true;
    }

    private function updateLocalVersion($newVersion)
    {
        // Atualizar no banco do cliente
        $stmt = $this->db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'versao_sistema'");
        $stmt->execute([$newVersion]);

        // Atualizar version.json local como redundância
        $jsonPath = __DIR__ . '/../../../version.json';
        $data = [
            'version' => $newVersion,
            'last_check' => date('Y-m-d')
        ];
        @file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function setApiUrl($url)
    {
        $this->apiUrl = rtrim($url, '/') . '/';
    }
}
