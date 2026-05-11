<?php
/**
 * SGIM CLIENT - OTA PASSIVE REPORTER
 * Consolida a telemetria e estados internos para observabilidade sem intervenção.
 */

namespace SGIM\OTA;

class OtaPassiveReporter {
    private $basePath;
    private $statePath;
    private $logsPath;
    private $auditPath;

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->statePath = $this->basePath . 'shared/system/state/';
        $this->logsPath = $this->basePath . 'shared/system/logs/';
        $this->auditPath = $this->basePath . 'shared/system/audit/';
    }

    /**
     * Gera a Matriz de Telemetria Completa
     */
    public function getTelemetryMatrix() {
        return [
            "system_health" => $this->getSystemHealth(),
            "ota_infrastructure" => $this->getInfraState(),
            "last_operations" => $this->getLastOperations(),
            "available_releases" => $this->getAvailableReleases()
        ];
    }

    private function getSystemHealth() {
        $statusFile = $this->auditPath . 'status.json';
        return file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : ["status" => "UNKNOWN"];
    }

    private function getInfraState() {
        return [
            "current_release" => trim(@file_get_contents($this->statePath . 'current_release.txt') ?: 'base'),
            "last_known_good" => trim(@file_get_contents($this->statePath . 'last_known_good.txt') ?: 'base'),
            "migrations_applied" => $this->getJsonState('migration_state.json'),
            "download_state" => $this->getJsonState('download_state.json')
        ];
    }

    private function getLastOperations() {
        $logFile = $this->logsPath . 'orchestrator.log';
        if (!file_exists($logFile)) return [];
        
        $lines = explode("\n", file_get_contents($logFile));
        return array_slice(array_filter($lines), -10); // Últimos 10 eventos
    }

    private function getAvailableReleases() {
        $path = $this->basePath . 'releases/';
        if (!is_dir($path)) return [];
        return array_diff(scandir($path), array('.', '..', '.htaccess', 'index.html', 'base'));
    }

    private function getJsonState($file) {
        $path = $this->statePath . $file;
        return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
    }
}
