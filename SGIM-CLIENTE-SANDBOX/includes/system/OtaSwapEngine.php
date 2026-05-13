<?php
/**
 * SGIM CLIENT - OTA SWAP ENGINE (INDUSTRIAL MODEL)
 * Responsável pela troca atômica de releases e rollback automático.
 */

namespace SGIM\OTA;

use Exception;

class OtaSwapEngine {
    private $statePath;
    private $logsPath;

    public function __construct($basePath) {
        $this->statePath = rtrim($basePath, '/') . '/shared/system/state/';
        $this->logsPath = rtrim($basePath, '/') . '/shared/system/logs/';
    }

    /**
     * Swap Atômico de Versão
     */
    public function swap($targetVersion) {
        $currentFile = $this->statePath . 'current_release.txt';
        $lkgFile = $this->statePath . 'last_known_good.txt';
        $rollbackFile = $this->statePath . 'rollback_state.json';

        try {
            $this->log("Iniciando Swap Atômico para versão: $targetVersion");

            // 1. Lock de Ativação
            $fp = fopen($currentFile, 'r+');
            if (!flock($fp, LOCK_EX)) throw new Exception("Falha ao obter lock de ativação.");

            // 2. Salvar Estado Atual para Rollback
            $currentVersion = trim(file_get_contents($currentFile));
            file_put_contents($rollbackFile, json_encode([
                "from" => $currentVersion,
                "to" => $targetVersion,
                "timestamp" => date('c')
            ]));

            // 3. Atualizar Ponteiro (Atomic Write)
            if (file_put_contents($currentFile, $targetVersion, LOCK_EX) === false) {
                throw new Exception("Falha ao gravar novo ponteiro de versão.");
            }

            // 4. Limpeza de Opcache (HostGator Hardening)
            $this->clearCache();

            // 5. Healthcheck Pós-Swap
            if (!$this->healthCheck($targetVersion)) {
                $this->rollback($currentVersion, "Falha no Healthcheck pós-swap.");
                throw new Exception("Healthcheck falhou. Rollback executado.");
            }

            // 6. Confirmar Last Known Good
            file_put_contents($lkgFile, $targetVersion, LOCK_EX);

            flock($fp, LOCK_UN);
            fclose($fp);

            $this->log("SWAP CONCLUÍDO: $currentVersion -> $targetVersion");
            return true;

        } catch (Exception $e) {
            $this->log("ERRO NO SWAP: " . $e->getMessage());
            return false;
        }
    }

    private function healthCheck($version) {
        // Simulação de healthcheck estrutural
        // Na Fase 5 final, isso verificará se o bootstrap carrega
        return true; 
    }

    private function rollback($previousVersion, $reason) {
        $this->log("ROLLBACK INICIADO: $reason");
        file_put_contents($this->statePath . 'current_release.txt', $previousVersion, LOCK_EX);
        $this->clearCache();
    }

    private function clearCache() {
        if (function_exists('opcache_reset')) opcache_reset();
        clearstatcache();
    }

    private function log($message) {
        $logFile = $this->logsPath . 'swap.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
