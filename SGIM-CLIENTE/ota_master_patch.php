<?php
/**
 * SGIM OTA - BULLDOZER PATCH v1.1.44
 * Localiza a raiz do sistema em qualquer nível de profundidade e promove.
 */
header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__ . '/';
echo "Iniciando BULLDOZER PATCH SGIM OTA...\n\n";

// 1. Criar pastas necessárias
$folders = ['includes/system', 'includes/system/drivers', 'api', 'shared/system/logs'];
foreach ($folders as $f) { if (!is_dir($root . $f)) mkdir($root . $f, 0755, true); }

// 2. Conteúdo dos arquivos (Versão Bulldozer)
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
        
        // BULLDOZER: Procura a pasta que contém a "includes" ou "api"
        $actualSrc = $this->bulldozerSource($versionPath);
        $total = $this->recursivePromote($actualSrc, $this->basePath);
        
        if ($this->pdo instanceof PDO && $total > 0) {
            $stmt = $this->pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (\"versao_sistema\", ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute([$version, $version]);
        }
        if (function_exists("opcache_reset")) opcache_reset();
        return true;
    }
    private function bulldozerSource($path) {
        if (!is_dir($path)) return $path;
        $items = array_diff(scandir($path), [".", ".."]);
        if (in_array("includes", $items) || in_array("api", $items)) return $path;
        foreach ($items as $item) {
            if (is_dir($path . $item)) {
                $sub = $this->bulldozerSource($path . $item . "/");
                if ($sub !== ($path . $item . "/")) return $sub;
            }
        }
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
        $url = $this->masterUrl . "/api/update/latest.json";
        $data = @file_get_contents($url);
        return $data ? "READY_FOR_COMMIT" : "FAIL_DISCOVERY";
    }
    public function commitUpdate($version) {
        $versionPath = $this->basePath . "releases/v" . $version . "/";
        return $this->activationDriver->activate($versionPath, ["version" => $version]);
    }
}',

    'api/ota_download.php' => '<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/system/OtaOrchestrator.php";
$orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . "/../", "https://escolateologicaeloha.com.br");
$res = $orchestrator->updateLifecycle();
header("Content-Type: application/json");
echo json_encode(["status" => ($res == "READY_FOR_COMMIT" ? "success" : "error"), "message" => $res]);',

    'api/ota_install.php' => '<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/system/OtaOrchestrator.php";
$releases = array_diff(scandir(__DIR__ . "/../releases/"), [".", "..", "base"]);
rsort($releases);
$v = !empty($releases) ? str_replace("v", "", $releases[0]) : null;
$orchestrator = new \SGIM\OTA\OtaOrchestrator($pdo, __DIR__ . "/../", "https://escolateologicaeloha.com.br");
$ok = $v ? $orchestrator->commitUpdate($v) : false;
header("Content-Type: application/json");
echo json_encode(["status" => ($ok ? "success" : "error"), "message" => ($ok ? "v$v OK" : "Fail")]);'
];

foreach ($files as $path => $content) {
    if (file_put_contents($root . $path, $content)) echo "OK: $path\n";
}

echo "\nBULLDOZER PATCH CONCLUÍDO.";
