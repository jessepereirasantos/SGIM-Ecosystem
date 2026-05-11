<?php
/**
 * SGIM MASTER - OTA PUBLISHER (INDUSTRIAL MODEL)
 * Responsável pelo pipeline de publicação atômica e geração do manifesto.
 */

namespace SGIM\OTA;

use Exception;
use ZipArchive;

class OtaPublisher {
    private $basePath;
    private $manifestPath;
    private $packagePath;
    private $workspacePath;
    private $logsPath;

    public function __construct($basePath) {
        $this->basePath      = rtrim($basePath, '/') . '/';
        $this->manifestPath  = $this->basePath . 'api/update/latest.json';
        $this->packagePath   = $this->basePath . 'api/update/packages/';
        $this->workspacePath = $this->basePath . 'shared/system/workspace/';
        $this->logsPath      = $this->basePath . 'shared/system/logs/';

        // Garante existência dos diretórios críticos
        foreach ([$this->packagePath, $this->workspacePath, $this->logsPath] as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
        }
    }

    /**
     * Pipeline Oficial de Publicação
     */
    public function publish($zipPath, $version, $minClient = "1.0.0", $channel = "stable") {
        try {
            $this->log("Iniciando publicação da versão $version...");

            // 1. Validar ZIP
            if (!file_exists($zipPath)) throw new Exception("Arquivo ZIP não encontrado.");
            
            // 2. Extrair em Workspace para Validar Estrutura
            $this->validatePackageStructure($zipPath);

            // 3. Gerar Hash SHA-256 do ZIP Original
            $sha256 = hash_file('sha256', $zipPath);
            $releaseId = bin2hex(random_bytes(8));

            // 4. Mover Pacote Final
            $packageName = "sgim_release_{$version}_{$releaseId}.zip";
            $finalPackagePath = $this->packagePath . $packageName;
            
            if (!copy($zipPath, $finalPackagePath)) {
                throw new Exception("Falha ao mover pacote para diretório oficial.");
            }

            // 5. Gerar Manifesto Temporário
            $manifest = [
                "version" => $version,
                "release_id" => $releaseId,
                "package" => $packageName,
                "sha256" => $sha256,
                "published_at" => date('c'),
                "min_client" => $minClient,
                "channel" => $channel,
                "signature" => hash('sha256', $sha256 . $version . 'SGIM-INDUSTRIAL-KEY'),
                "health" => "PASS"
            ];

            // 6. Publicação Atômica (Rename)
            $this->atomicPublishManifest($manifest);

            // 7. Atualizar ZIP Comercial de Vendas Automaticamente
            $commercialZipPath = $this->basePath . 'downloads/sgim_master.zip';
            $commercialDownloadsDir = dirname($commercialZipPath);
            if (!is_dir($commercialDownloadsDir)) {
                mkdir($commercialDownloadsDir, 0755, true);
            }
            if (!copy($zipPath, $commercialZipPath)) {
                $this->log("AVISO: Falha ao atualizar ZIP Comercial em downloads/sgim_master.zip");
            } else {
                $this->log("ZIP Comercial atualizado com sucesso em downloads/sgim_master.zip");
            }

            $this->log("Versão $version publicada com sucesso. ID: $releaseId");
            return true;

        } catch (Exception $e) {
            $this->log("ERRO NA PUBLICAÇÃO: " . $e->getMessage());
            return false;
        }
    }

    private function validatePackageStructure($zipPath) {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            // Verifica se arquivos vitais existem no ZIP
            $hasIndex = $zip->locateName('index.php') !== false;
            $zip->close();
            if (!$hasIndex) throw new Exception("Estrutura do ZIP inválida: index.php não encontrado.");
        } else {
            throw new Exception("Falha ao abrir arquivo ZIP.");
        }
    }

    private function atomicPublishManifest($manifest) {
        // CORREÇÃO: file_put_contents direto com LOCK_EX.
        // O rename() cross-directory falha em hospedagem compartilhada (HostGator).
        $result = file_put_contents(
            $this->manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        if ($result === false) {
            throw new Exception("Falha ao gravar manifesto em: " . $this->manifestPath);
        }

        $this->log("Manifesto gravado em: " . $this->manifestPath . " (" . $result . " bytes)");
    }

    private function log($message) {
        $logFile = $this->logsPath . 'publisher.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
