<?php
/**
 * SGIM CLIENT - OTA DIRECT INSTALLER v1.1.36
 * Engenharia Baseada em Evidência: Smart Flatten & Root Promotion.
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

if (!class_exists('ZipArchive')) {
    echo json_encode(['status' => 'error', 'message' => 'Extensão ZipArchive necessária.']);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

// --- 1. CONFIGURAÇÃO ---
$masterUrl      = 'https://escolateologicaeloha.com.br';
$currentVersion = '1.1.0';

if ($pdo instanceof PDO) {
    try {
        $s = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('master_url','versao_sistema')");
        foreach ($s->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($k === 'master_url' && $v && $v !== 'PADRÃO') $masterUrl = rtrim($v, '/');
            if ($k === 'versao_sistema' && $v) $currentVersion = $v;
        }
    } catch (Throwable $e) {}
}

$logFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'installer.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function otaLog($msg, $file) {
    @file_put_contents($file, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function otaFail($msg, $logFile) {
    otaLog("ERRO FATAL: " . $msg, $logFile);
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

otaLog("=== INÍCIO OTA v1.1.36 === User: " . $_SESSION['user_id'], $logFile);

// --- 2. BUSCA MANIFESTO ---
$manifestUrl = $masterUrl . '/api/update/latest.json';
$ch = curl_init($manifestUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false
]);
$manifestJson = curl_exec($ch);
curl_close($ch);

if (!$manifestJson) otaFail("Master inacessível: $manifestUrl", $logFile);

$manifest = json_decode($manifestJson, true);
if (!$manifest || !isset($manifest['version'])) otaFail("Manifesto inválido.", $logFile);

// --- 3. DOWNLOAD ---
$tmpDir  = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR;
if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);

$zipFile = $tmpDir . 'update_' . $manifest['version'] . '.zip';
$fp = fopen($zipFile, 'w+');
$ch = curl_init($manifest['url']);
curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_TIMEOUT => 300, CURLOPT_SSL_VERIFYPEER => false]);
curl_exec($ch);
curl_close($ch);
fclose($fp);

if (!file_exists($zipFile) || filesize($zipFile) < 1000) otaFail("Falha no download do pacote.", $logFile);

// --- 4. EXTRAÇÃO ---
$extractDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'workspace' . DIRECTORY_SEPARATOR . 'extract_' . $manifest['version'] . DIRECTORY_SEPARATOR;
if (is_dir($extractDir)) {
    // Limpeza profunda recursiva
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $fileinfo) { ($fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath())); }
    rmdir($extractDir);
}
@mkdir($extractDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($extractDir);
    $zip->close();
    otaLog("Extração concluída em: $extractDir", $logFile);
} else {
    otaFail("Não foi possível abrir o ZIP.", $logFile);
}

// --- 5. SMART FLATTEN (Inteligência de Mergulho) ---
$items = array_diff(scandir($extractDir), ['.', '..', '.DS_Store', '__MACOSX', 'build_info.json', 'manifest.txt']);
otaLog("CONTEÚDO EXTRAÍDO: " . implode(', ', $items), $logFile);

$potentialFolders = [];
foreach ($items as $item) {
    if (is_dir($extractDir . $item)) $potentialFolders[] = $item;
}

if (count($potentialFolders) === 1) {
    $wrapper = $potentialFolders[0];
    // Validação: a pasta deve conter indícios de ser o core (config ou includes)
    if (is_dir($extractDir . $wrapper . DIRECTORY_SEPARATOR . 'config') || is_dir($extractDir . $wrapper . DIRECTORY_SEPARATOR . 'includes')) {
        $extractDir = rtrim($extractDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $wrapper . DIRECTORY_SEPARATOR;
        otaLog("SMART FLATTEN ATIVADO: Mergulhando em '$wrapper'.", $logFile);
    }
}

// --- 6. PROMOÇÃO DE ARQUIVOS (Overwrite Real) ---
$basePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$protectedPaths = ['config/db_config.php', 'uploads/', 'storage/', 'releases/', 'backups/', '.installed', 'shared/'];

$copied = 0;
$failed = [];
$skipped = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);

foreach ($it as $file) {
    $relativePath = substr($file->getPathname(), strlen(realpath($extractDir)) + 1);
    $relativePath = str_replace('\\', '/', $relativePath); // Normaliza para comparação

    $isProtected = false;
    foreach ($protectedPaths as $p) {
        if (strpos($relativePath, $p) === 0) { $isProtected = true; break; }
    }

    if ($isProtected) {
        $skipped[] = $relativePath;
        continue;
    }

    $dest = $basePath . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $destDir = dirname($dest);
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

    if (copy($file->getPathname(), $dest)) {
        $copied++;
        otaLog("PROMOTING: $relativePath -> $dest", $logFile);
    } else {
        $failed[] = $relativePath;
        otaLog("FALHA DE ESCRITA: $relativePath", $logFile);
    }
}

        // Após promoção, remover a pasta versionada que acabou de ser promovida (rollback mantido separadamente)
        if (!empty($versaoAlvo) && is_dir($basePath . $versaoAlvo)) {
            $filesOld = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($basePath . $versaoAlvo, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($filesOld as $fo) {
                $fo->isDir() ? rmdir($fo->getRealPath()) : unlink($fo->getRealPath());
            }
            rmdir($basePath . $versaoAlvo);
            otaLog("Pasta versionada removida após promoção: $versaoAlvo/", $logFile);
        }
        // Retenção de rollback (mantém duas versões mais recentes)
        $pastasVersionadas = array_filter(scandir($basePath), function($d) use ($versionPattern) {
            return is_dir($basePath . $d) && preg_match($versionPattern, $d);
        });
        usort($pastasVersionadas, 'version_compare');
        $mantidas = array_slice($pastasVersionadas, -2);
        foreach ($mantidas as $m) {
            otaLog("RETENÇÃO: Pasta mantida (rollback): $m/", $logFile);
        }
        // Opcional: remover versões excedentes (já tratamos acima)

        // Fim da retenção


@unlink($zipFile);
otaLog("=== OTA CONCLUÍDO === Copiados: $copied | Falhas: " . count($failed), $logFile);

if (ob_get_length()) ob_end_clean();
echo json_encode([
    'status' => count($failed) > 0 ? 'partial' : 'success',
    'message' => count($failed) > 0 ? 'Alguns arquivos falharam.' : "Sistema atualizado para v{$manifest['version']}.",
    'copied' => $copied,
    'failed_files' => $failed
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
