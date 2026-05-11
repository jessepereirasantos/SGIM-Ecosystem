<?php
/**
 * SGIM OTA - INTERNAL AUDITOR (INDUSTRIAL MODEL)
 * Responsável por validar as capacidades técnicas do servidor sem exposição pública.
 */

namespace SGIM\OTA;

class OtaInternalAuditor {
    private $basePath;
    private $auditPath;

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->auditPath = $this->basePath . 'shared/system/audit/';
    }

    public function execute() {
        $report = [
            "audit_signature" => hash('sha256', php_uname() . PHP_VERSION . __DIR__),
            "audit_version" => "1.0",
            "generated_at" => date('c'),
            "environment_mode" => "production",
            "environment" => php_uname(),
            "php_version" => PHP_VERSION,
            "checks" => [
                "flock" => $this->checkFlock(),
                "rename_atomic" => $this->checkRename(),
                "ziparchive" => class_exists('ZipArchive'),
                "curl" => function_exists('curl_init'),
                "opcache_reset" => function_exists('opcache_reset'),
                "file_write" => $this->checkWrite(),
                "file_read" => $this->checkRead()
            ],
            "filesystem" => [
                "releases_exists" => file_exists($this->basePath . 'releases'),
                "shared_exists" => file_exists($this->basePath . 'shared'),
                "updates_exists" => file_exists($this->basePath . 'updates')
            ]
        ];

        $report["result"] = $this->evaluate($report) ? "PASS" : "FAIL";

        $this->saveReport($report);
        return $report;
    }

    private function checkFlock() {
        $file = $this->basePath . 'updates/workspace/lock.audit';
        $fp = fopen($file, 'w');
        $success = ($fp && flock($fp, LOCK_EX));
        if ($fp) { flock($fp, LOCK_UN); fclose($fp); @unlink($file); }
        return $success;
    }

    private function checkRename() {
        $src = $this->basePath . 'updates/workspace/src.audit';
        $dst = $this->basePath . 'updates/workspace/dst.audit';
        file_put_contents($src, 'test');
        $success = rename($src, $dst);
        @unlink($dst); @unlink($src);
        return $success;
    }

    private function checkWrite() {
        $file = $this->auditPath . 'write_test.tmp';
        $success = (file_put_contents($file, 'test', LOCK_EX) !== false);
        @unlink($file);
        return $success;
    }

    private function checkRead() {
        $file = $this->auditPath . 'read_test.tmp';
        file_put_contents($file, 'test');
        $success = (file_get_contents($file) === 'test');
        @unlink($file);
        return $success;
    }

    private function evaluate($report) {
        foreach ($report['checks'] as $check) {
            if ($check === false) return false;
        }
        foreach ($report['filesystem'] as $exists) {
            if ($exists === false) return false;
        }
        return true;
    }

    private function saveReport($report) {
        if (!file_exists($this->auditPath)) {
            mkdir($this->auditPath, 0755, true);
        }
        file_put_contents($this->auditPath . 'status.json', json_encode($report, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

// Execução Autônoma (Trigger Interno)
if (php_sapi_name() === 'cli' || defined('OTA_FORCE_AUDIT')) {
    $auditor = new OtaInternalAuditor(__DIR__ . '/../../');
    $auditor->execute();
}
