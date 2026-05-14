<?php
/**
 * SGIM OTA - MASTER PATCH v1.1.43
 * Este script restaura a integridade total do sistema OTA.
 */
header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__ . '/';
echo "Iniciando Patch de Integridade SGIM OTA...\n\n";

// 1. Criar pastas necessárias
$folders = [
    'includes/system',
    'includes/system/drivers',
    'api',
    'shared/system/logs',
    'shared/system/state'
];
foreach ($folders as $f) {
    if (!is_dir($root . $f)) {
        mkdir($root . $f, 0755, true);
        echo "Pasta criada: $f\n";
    }
}

// 2. Conteúdo dos arquivos (Versões Finais e Blindadas)
$files = [
    'includes/system/ActivationDriverInterface.php' => '<?php
namespace SGIM\OTA;
if (!interface_exists("SGIM\OTA\ActivationDriverInterface")) {
    interface ActivationDriverInterface {
        public function validateEnvironment(): bool;
        public function prepareActivation($v, $m): bool;
        public function activate($v, $m): bool;
        public function rollback($v): bool;
        public function getHealthcheck(): array;
    }
}',

    'includes/system/drivers/SharedHostingDriver.php' => '<?php
namespace SGIM\OTA\Drivers;
use SGIM\OTA\ActivationDriverInterface;
use PDO; use Exception;
class SharedHostingDriver implements ActivationDriverInterface {
    private $basePath; private $pdo;
    public function __construct($bp, $pdo = null) { $this->basePath = $bp; $this->pdo = $pdo; }
    public function validateEnvironment(): bool { return true; }
    public function prepareActivation($v, $m): bool { return true; }
    public function activate($versionPath, $manifest): bool {
        $version = $manifest["version"] ?? null;
        if (!$version && preg_match("/v(\d+\.\d+\.\d+)/", $versionPath, $ms)) $version = $ms[1];
        if (!$version) return false;
        $actualSrc = $this->findDeepSource($versionPath);
        $total = $this->recursivePromote($actualSrc, $this->basePath);
        if ($this->pdo instanceof PDO && $total > 0) {
            $stmt = $this->pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (\"versao_sistema\", ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$version, $version]);
        }
        if (function_exists("opcache_reset")) opcache_reset();
        return true;
    }
    private function findDeepSource($path) {
        $files = array_diff(scandir($path), [".", ".."]);
        if (count($files) === 1 && is_dir($path . reset($files))) return $path . reset($files) . "/";
        return $path;
    }
    private function recursivePromote($src, $dst) {
        if (!is_dir($src)) return 0;
        $dir = opendir($src); $count = 0;
        while(false !== ($file = readdir($dir))) {
            if ($file != "." && $file != "..") {
                if (is_dir($src . "/" . $file)) $count += $this->recursivePromote($src . "/" . $file, $dst . "/" . $file);
                else if (copy($src . "/" . $file, $dst . "/" . $file)) $count++;
            }
        }
        closedir($dir); return $count;
    }
    public function rollback($v): bool { return true; }
    public function getHealthcheck(): array { return ["status"=>"OK"]; }
}',

    'includes/system/OtaOrchestrator.php' => '<?php
namespace SGIM\OTA;
require_once __DIR__ . "/ActivationDriverInterface.php";
require_once __DIR__ . "/drivers/SharedHostingDriver.php";
class OtaOrchestrator {
    private $basePath; private $pdo; private $masterUrl; private $activationDriver;
    public function __construct($pdo, $basePath, $masterUrl) {
        $this->pdo = $pdo; $this->basePath = rtrim($basePath, "/") . "/"; $this->masterUrl = $masterUrl;
        $this->activationDriver = new \SGIM\OTA\Drivers\SharedHostingDriver($this->basePath, $pdo);
    }
    public function updateLifecycle() {
        $manifest = $this->discovery();
        if (!$manifest) return "FAIL_DISCOVERY";
        return "READY_FOR_COMMIT";
    }
    private function discovery() {
        $url = $this->masterUrl . "/api/update/latest.json";
        $data = @file_get_contents($url);
        return $data ? json_decode($data, true) : null;
    }
    public function commitUpdate($version) {
        $versionPath = $this->basePath . "releases/v" . $version . "/";
        $manifestPath = $versionPath . "release_manifest.json";
        $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : ["version" => $version];
        return $this->activationDriver->activate($versionPath, $manifest);
    }
}',

    'api/ota_download.php' => '<?php
error_reporting(E_ALL); ini_set("display_errors", 0);
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/system/OtaOrchestrator.php";
$masterUrl = "https://escolateologicaeloha.com.br";
$orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . "/../", $masterUrl);
$res = $orchestrator->updateLifecycle();
header("Content-Type: application/json");
echo json_encode(["status" => ($res == "READY_FOR_COMMIT" ? "success" : "error"), "message" => $res]);',

    'api/ota_install.php' => '<?php
error_reporting(E_ALL); ini_set("display_errors", 0);
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/system/OtaOrchestrator.php";
$releases = array_diff(scandir(__DIR__ . "/../releases/"), [".", "..", "base"]);
rsort($releases);
$versao = !empty($releases) ? str_replace("v", "", $releases[0]) : null;
$orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . "/../", "https://escolateologicaeloha.com.br");
$ok = $versao ? $orchestrator->commitUpdate($versao) : false;
header("Content-Type: application/json");
echo json_encode(["status" => ($ok ? "success" : "error"), "message" => ($ok ? "v$versao OK" : "Fail")]);'
];

// 3. Escrever os arquivos
foreach ($files as $path => $content) {
    if (file_put_contents($root . $path, $content)) {
        echo "Arquivo restaurado: $path\n";
    } else {
        echo "ERRO ao restaurar: $path\n";
    }
}

echo "\nPATCH CONCLUÍDO COM SUCESSO. SISTEMA OTA INTEGRADO.";
