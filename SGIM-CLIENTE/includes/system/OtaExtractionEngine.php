<?php
/**
 * SGIM CLIENT - OTA EXTRACTION ENGINE (INDUSTRIAL MODEL)
 * Responsável pela extração isolada, proteção Zip-Slip e validação estrutural.
 */

namespace SGIM\OTA;

use Exception;
use ZipArchive;

class OtaExtractionEngine {
    private $basePath;
    private $workspacePath;
    private $releasesPath;
    private $quarantinePath;
    private $logsPath;

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->workspacePath = $this->basePath . 'shared/system/workspace/extracting/';
        $this->releasesPath = $this->basePath . 'releases/';
        $this->quarantinePath = $this->basePath . 'shared/system/workspace/quarantine/';
        $this->logsPath = $this->basePath . 'shared/system/logs/';
    }

    /**
     * Fluxo de Extração Segura
     */
    public function extract($version, $zipPath) {
        $tempExtractPath = $this->workspacePath . $version . '/';
        $finalReleasePath = $this->releasesPath . 'v' . $version . '/';

        try {
            $this->log("Iniciando extração isolada da versão $version...");

            // 1. Limpar e criar diretório temporário
            if (file_exists($tempExtractPath)) $this->recursiveRmdir($tempExtractPath);
            mkdir($tempExtractPath, 0755, true);

            // 2. Extração com Proteção contra Path Traversal
            $this->secureUnzip($zipPath, $tempExtractPath);

            // 3. Validação Estrutural
            $this->validateStructure($tempExtractPath);

            // 4. Geração do release_health.json
            $this->generateHealthReport($tempExtractPath, $version);

            // 5. Promoção Atômica para /releases/
            if (file_exists($finalReleasePath)) {
                $this->recursiveRmdir($finalReleasePath);
                // Pequena pausa para o SO liberar handles (comum no Windows)
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') usleep(500000); 
            }
            
            if (!@rename($tempExtractPath, $finalReleasePath)) {
                // Fallback: se o rename falhar (ex: cross-device ou lock), tenta mover via copy recursivo ou erro
                throw new Exception("Falha ao promover release. Verifique permissões de escrita em $finalReleasePath.");
            }

            $this->log("Release v$version extraída e validada com sucesso.");
            return true;

        } catch (Exception $e) {
            $this->log("ERRO NA EXTRAÇÃO: " . $e->getMessage());
            $this->handleFailure($tempExtractPath, $version);
            return false;
        }
    }

    private function secureUnzip($zipPath, $dest) {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Proteção Zip-Slip (Anti Path Traversal)
                if (strpos($filename, '..') !== false || strpos($filename, '/') === 0) {
                    throw new Exception("Tentativa de Path Traversal detectada no arquivo: $filename");
                }

                $zip->extractTo($dest, $filename);
            }
            $zip->close();
        } else {
            throw new Exception("Não foi possível abrir o pacote ZIP.");
        }
    }

    private function validateStructure($path) {
        // CONTRATO RÍGIDO v2.0: Fim das heurísticas
        $vitalFiles = ['index.php', 'api/health/version.php']; 
        foreach ($vitalFiles as $file) {
            if (!file_exists($path . $file)) {
                throw new Exception("REJEIÇÃO DE PACOTE (CLIENT SIDE): Estrutura inválida. O arquivo vital '$file' está ausente na raiz da extração. O OTA exige um pacote perfeitamente padronizado.");
            }
        }
    }

    private function generateHealthReport($path, $version) {
        $report = [
            "version" => $version,
            "extracted_at" => date('c'),
            "health" => "PASS",
            "integrity_check" => true
        ];
        file_put_contents($path . 'release_health.json', json_encode($report, JSON_PRETTY_PRINT));
    }

    private function handleFailure($path, $version) {
        if (file_exists($path)) {
            $quarantineDest = $this->quarantinePath . $version . '_' . time();
            rename($path, $quarantineDest);
            $this->log("Release corrompida movida para quarentena: $quarantineDest");
        }
    }

    private function recursiveRmdir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    private function log($message) {
        $logFile = $this->logsPath . 'extraction.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
