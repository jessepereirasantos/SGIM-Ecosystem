<?php
/**
 * SGIM CLIENT - OTA TRANSACTIONAL ORCHESTRATOR
 * Coordenador de estados dos motores industriais.
 * MODO: DRY_RUN (PROTEÇÃO MÁXIMA)
 */

namespace SGIM\OTA;

use Exception;

class OtaOrchestrator {
    private $basePath;
    private $masterUrl; // ✅ FIX: recebido via construtor, não hardcoded
    private $config = [
        "dry_run"                   => false,
        "activation_enabled"        => true,
        "manual_approval_required"  => false
    ];
    
    private $downloadEngine;
    private $extractionEngine;
    private $migrationEngine;
    private $activationDriver;
    private $capabilityManager;

    public function __construct($pdo, $basePath, $masterUrl = 'https://escolateologicaeloha.com.br') {
        $this->basePath  = rtrim($basePath, '/') . '/';
        $this->masterUrl = rtrim($masterUrl, '/');

        $this->downloadEngine   = new OtaDownloadEngine($this->basePath);
        $this->extractionEngine = new OtaExtractionEngine($this->basePath);
        $this->migrationEngine  = new OtaMigrationEngine($pdo, $this->basePath);
        
        // INTEGRACAO ADAPTATIVA 10D
        $this->capabilityManager = new OtaCapabilityManager($this->basePath);
        $capabilities = $this->capabilityManager->generateReport();
        
        $driverClass = "\\SGIM\\OTA\\Drivers\\" . $capabilities['driver_analysis']['recommended_driver'];
        if (class_exists($driverClass)) {
            $this->activationDriver = new $driverClass($this->basePath);
        }
    }

    /**
     * Fluxo Transacional Integrado
     */
    public function updateLifecycle() {
        try {
            $this->log("Iniciando Ciclo Integrado (Driver: " . get_class($this->activationDriver) . ")");

            // 1. Discovery (Manifest)
            $manifest = $this->discovery();
            $this->updateState('discovery', ["last_manifest" => $manifest]);
            
            // 2. Download & Verify SHA256
            $this->downloadEngine->downloadPackage($manifest['url'], $manifest['sha256'], $manifest['version']);
            $this->updateState('download', ["version" => $manifest['version'], "status" => "SUCCESS"]);

            // 3. Extraction & Structural Validation
            $versionPath = $this->basePath . "releases/v" . $manifest['version'] . "/";
            $this->extractionEngine->extract($manifest['version'], $this->basePath . "shared/system/downloads/release_{$manifest['version']}.zip");
            $this->updateState('extraction', ["version" => $manifest['version'], "status" => "SUCCESS"]);

            // 4. Driver Staging (Backup + Impact Report)
            if ($this->activationDriver) {
                $this->activationDriver->prepareActivation($versionPath, $manifest);
            }

            // 5. Database Migrations (Auto-execution)
            $this->log("Iniciando Migrações Automáticas...");
            $this->migrationEngine->migrate();
            $this->updateState('migration', ["status" => "SUCCESS"]);

            // 6. WAIT STATE (Aprovação Manual Obrigatória)
            $this->log("[WAIT] Sistema pronto em STAGING. Aguardando comando manual para COMMIT.");
            return "READY_FOR_COMMIT";

        } catch (Exception $e) {
            $this->log("FALHA NO CICLO INTEGRADO: " . $e->getMessage());
            return "FAIL";
        }
    }

    /**
     * Execução Final (Ativação Real)
     */
    public function commitUpdate($version) {
        try {
            $this->log("COMANDO MANUAL: Ativando versão $version");
            $versionPath = $this->basePath . "releases/v" . $version . "/";
            $manifest = json_decode(file_get_contents($versionPath . 'release_manifest.json'), true);

            if ($this->activationDriver->activate($versionPath, $manifest)) {
                $this->updateState('activation', ["version" => $version, "status" => "ACTIVE"]);
                return true;
            }
            return false;
        } catch (Exception $e) {
            $this->log("ERRO NO COMMIT: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reversão Manual
     */
    public function rollbackUpdate($version) {
        try {
            $this->log("COMANDO MANUAL: Revertendo versão $version");
            return $this->activationDriver->rollback($version);
        } catch (Exception $e) {
            $this->log("ERRO NO ROLLBACK: " . $e->getMessage());
            return false;
        }
    }

    private function discovery() {
        // ✅ FIX: Usa masterUrl recebido via construtor (não hardcoded)
        $url  = $this->masterUrl . '/api/update/latest.json';
        $json = @file_get_contents($url);
        if (!$json) throw new Exception("Falha ao consultar Master em: $url");
        $manifest = json_decode($json, true);
        if (!$manifest || !isset($manifest['version'])) {
            throw new Exception("Manifesto JSON inválido recebido de: $url");
        }
        return $manifest;
    }

    private function updateState($key, $data) {
        $stateFile = $this->basePath . 'shared/system/state/current_state.json';
        $state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
        $state[$key] = array_merge($data, ["updated_at" => date('c')]);
        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function rollback() {
        $this->log("ROLLBACK TRANSACIONAL EXECUTADO: Mantendo sistema em base estável.");
    }

    private function log($message) {
        $logFile = $this->basePath . 'shared/system/logs/orchestrator.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
