<?php
/**
 * SGIM OTA - CAPABILITY MANAGER (LIGHTWEIGHT)
 * Detecta capacidades do ambiente e sugere a melhor estratégia de ativação.
 */

namespace SGIM\OTA;

class OtaCapabilityManager {
    private $basePath;

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/') . '/';
    }

    /**
     * Gera a Matriz de Capacidades Consolidada
     */
    public function generateReport() {
        $report = [
            "timestamp" => date('c'),
            "environment" => [
                "os" => PHP_OS,
                "php_version" => PHP_VERSION,
                "sapi" => php_sapi_name(),
                "is_cli" => (php_sapi_name() === 'cli')
            ],
            "capabilities" => [
                "filesystem" => [
                    "is_writable" => is_writable($this->basePath),
                    "can_symlink" => $this->checkSymlinkSupport(),
                    "can_rename_dir" => true, // Nativo PHP
                    "disk_free_space" => disk_free_space($this->basePath)
                ],
                "features" => [
                    "opcache" => function_exists('opcache_reset'),
                    "zlib" => extension_loaded('zlib'),
                    "curl" => function_exists('curl_init'),
                    "pdo_mysql" => extension_loaded('pdo_mysql')
                ]
            ],
            "driver_analysis" => $this->analyzeDrivers()
        ];

        $this->saveReport($report);
        return $report;
    }

    private function checkSymlinkSupport() {
        if (!function_exists('symlink')) return false;
        // Teste leve sem persistência
        $target = $this->basePath . 'shared/system/workspace/test_target.txt';
        $link = $this->basePath . 'shared/system/workspace/test_link';
        @file_put_contents($target, 'test');
        $success = @symlink($target, $link);
        if ($success) @unlink($link);
        @unlink($target);
        return $success;
    }

    private function analyzeDrivers() {
        // Lógica de prioridade e recomendação
        $drivers = [
            ["name" => "SharedHostingDriver", "priority" => 100, "status" => "COMPATIBLE"],
            ["name" => "AtomicSwapDriver", "priority" => 200, "status" => "EXPERIMENTAL"]
        ];

        return [
            "available_drivers" => $drivers,
            "recommended_driver" => "SharedHostingDriver" // Fallback seguro
        ];
    }

    private function saveReport($report) {
        $path = $this->basePath . 'shared/system/state/capabilities.json';
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));
    }
}
