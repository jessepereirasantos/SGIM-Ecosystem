<?php
/**
 * SGIM CLIENT - OTA DIRECT INSTALLER v1.1.41 (DEFINITIVE EDITION)
 * Engenharia Baseada em Evidência: Smart Flatten & Root Overwrite Promotion.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado.']);
    exit;
}

// ✅ AJUSTES DE AMBIENTE
set_time_limit(600);
ini_set('memory_limit', '512M');
ob_start();

// Reset de cache para garantir que o motor novo seja lido
if(function_exists('opcache_reset')) { opcache_reset(); }

if (!class_exists('ZipArchive')) {
    echo json_encode(['status' => 'error', 'message' => 'Extensão ZipArchive necessária.']);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

// --- 1. CONFIGURAÇÃO ---
$masterUrl      = 'https://escolateologicaeloha.com.br';
define('OTA_VERSION', '1.1.41');
define('OTA_LOG_FILE', __DIR__ . '/../shared/system/logs/installer.log');

function otaLog($msg) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(OTA_LOG_FILE, "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

// 2. BUSCAR MANIFESTO (Detectar Versão Alvo)
$manifestPath = __DIR__ . '/../manifest.json';
$versaoAlvo = '1.1.41'; // Fallback
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (isset($manifest['version'])) {
        $versaoAlvo = $manifest['version'];
    }
}

otaLog("=== INÍCIO OTA v" . OTA_VERSION . " (Alvo: $versaoAlvo) ===");

// 3. CAMINHOS CRÍTICOS
$installRoot = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR;
$extractPath = $installRoot . 'releases' . DIRECTORY_SEPARATOR . 'v' . $versaoAlvo . DIRECTORY_SEPARATOR;

if (!is_dir($extractPath)) {
    otaLog("ERRO: Pasta de extração não encontrada: $extractPath");
    echo json_encode(['status' => 'error', 'message' => 'Arquivos de atualização não encontrados.']);
    exit;
}

// 4. PROMOÇÃO REAL (FORÇAR SOBREPOSIÇÃO NA RAIZ)
function PromoteFiles($src, $dst, $versaoAlvo) {
    if (!is_dir($src)) return 0;
    $dir = opendir($src);
    @mkdir($dst);
    $count = 0;
    
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                // Se a pasta for a própria versão (ex: 1.1.41/), entramos nela mas mantemos o destino na raiz
                if ($file === $versaoAlvo) {
                    $count += PromoteFiles($src . '/' . $file, $dst, $versaoAlvo);
                } else {
                    $count += PromoteFiles($src . '/' . $file, $dst . '/' . $file, $versaoAlvo);
                }
            } else {
                if (copy($src . '/' . $file, $dst . '/' . $file)) {
                    $count++;
                }
            }
        }
    }
    closedir($dir);
    return $count;
}

$totalCopiados = PromoteFiles($extractPath, $installRoot, $versaoAlvo);
otaLog("PROMOÇÃO CONCLUÍDA: $totalCopiados arquivos movidos para a raiz.");

// 5. ATUALIZAR BANCO DE DADOS
try {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute([$versaoAlvo, $versaoAlvo]);
        otaLog("BANCO ATUALIZADO: v$versaoAlvo");
    }
} catch (Exception $e) {
    otaLog("ERRO BANCO: " . $e->getMessage());
}

// 6. LIMPEZA DO WORKSPACE
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                else
                    unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        rmdir($dir);
    }
}
rrmdir($extractPath);
otaLog("WORKSPACE LIMPO.");

// Finalizar Log
otaLog("=== OTA v" . OTA_VERSION . " FINALIZADO COM SUCESSO ===");

// 7. RESET OPCache FINAL
if(function_exists('opcache_reset')) { opcache_reset(); }

echo json_encode([
    'status' => 'success',
    'message' => 'Sistema atualizado para v' . $versaoAlvo . ' com sucesso!',
    'version' => $versaoAlvo
]);
exit;
