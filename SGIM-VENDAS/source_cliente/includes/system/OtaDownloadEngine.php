<?php
/**
 * SGIM CLIENT - OTA DOWNLOAD ENGINE (INDUSTRIAL MODEL)
 * Responsável pelo download resiliente e validação de integridade.
 */

namespace SGIM\OTA;

use Exception;

class OtaDownloadEngine {
    private $workspacePath;
    private $downloadsPath;
    private $logsPath;

    public function __construct($basePath) {
        $this->workspacePath = rtrim($basePath, '/') . '/shared/system/workspace/';
        $this->downloadsPath = rtrim($basePath, '/') . '/shared/system/downloads/';
        $this->logsPath = rtrim($basePath, '/') . '/shared/system/logs/';
    }

    /**
     * Download Resiliente via cURL Stream
     */
    public function downloadPackage($url, $expectedHash, $version) {
        $tmpFile = $this->workspacePath . "downloading_{$version}.tmp";
        $finalFile = $this->downloadsPath . "release_{$version}.zip";

        try {
            $this->log("Iniciando download da versão $version de $url");

            // 1. Abrir arquivo para escrita em Stream (Economia de RAM)
            $fp = fopen($tmpFile, 'w+');
            if (!$fp) throw new Exception("Não foi possível criar arquivo temporário no workspace.");

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos de limite
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SGIM-OTA-Industrial/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL verification for resilience
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if (!$success || $httpCode !== 200) {
                @unlink($tmpFile);
                throw new Exception("Falha no download via cURL. HTTP Code: $httpCode");
            }

            // 2. Validação de Integridade (SHA-256)
            $actualHash = hash_file('sha256', $tmpFile);
            if ($actualHash !== $expectedHash) {
                @unlink($tmpFile);
                throw new Exception("Falha de Integridade: Hash esperado $expectedHash, recebido $actualHash");
            }

            // 3. Swap Atômico para pasta de Downloads
            if (!rename($tmpFile, $finalFile)) {
                throw new Exception("Falha ao mover pacote validado para diretório de downloads.");
            }

            $this->log("Download e validação da versão $version concluídos com sucesso.");
            return true;

        } catch (Exception $e) {
            $this->log("ERRO NO DOWNLOAD: " . $e->getMessage());
            if (file_exists($tmpFile)) @unlink($tmpFile);
            return false;
        }
    }

    private function log($message) {
        if (!file_exists($this->logsPath)) mkdir($this->logsPath, 0755, true);
        $logFile = $this->logsPath . 'download.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
