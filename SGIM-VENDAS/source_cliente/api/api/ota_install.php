<?php
/**
 * SGIM CLIENT - OTA DIRECT INSTALLER v2.0
 * Endpoint de instalação única: detecta, baixa, valida e aplica a atualização.
 * Chamado via AJAX pelo botão "ATUALIZAR AGORA".
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

// Segurança: apenas admins logados
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado.']);
    exit;
}

// ✅ AJUSTES DE AMBIENTE (HostGator / Shared Hosting)
set_time_limit(600); // 10 minutos
ini_set('memory_limit', '512M');
ob_start(); // Buffer para capturar erros fatais e não quebrar o JSON

if (!class_exists('ZipArchive')) {
    echo json_encode(['status' => 'error', 'message' => 'Extensão ZipArchive não habilitada no servidor.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';


// ─── 1. CONFIGURAÇÃO ─────────────────────────────────────────────────────────
$masterUrl      = 'https://escolateologicaeloha.com.br'; // fallback
$currentVersion = '1.1.0';                               // fallback

if ($pdo instanceof PDO) {
    try {
        $s = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('master_url','versao_sistema')");
        foreach ($s->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($k === 'master_url' && $v && $v !== 'PADRÃO' && filter_var($v, FILTER_VALIDATE_URL)) {
                $masterUrl = rtrim($v, '/');
            }
            if ($k === 'versao_sistema' && $v) {
                $currentVersion = $v;
            }
        }
    } catch (Throwable $e) {}
}

$logFile = __DIR__ . '/../shared/system/logs/installer.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

function otaLog($msg, $file) {
    @file_put_contents($file, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function otaFail($msg, $logFile) {
    otaLog('ERRO: ' . $msg, $logFile);
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}


otaLog("=== INÍCIO DA INSTALAÇÃO === Solicitado por user_id=" . $_SESSION['user_id'], $logFile);

// ─── 2. BUSCA MANIFESTO NO MASTER ────────────────────────────────────────────
$manifestUrl = $masterUrl . '/api/update/latest.json';
otaLog("Consultando manifesto: $manifestUrl", $logFile);

$manifestJson = false;
if (function_exists('curl_init')) {
    $ch = curl_init($manifestUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $manifestJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) $manifestJson = false;
}
if (!$manifestJson) {
    $manifestJson = @file_get_contents($manifestUrl);
}
if (!$manifestJson) {
    otaFail("Não foi possível acessar o Master em: $manifestUrl", $logFile);
}

$manifest = json_decode($manifestJson, true);
if (!$manifest || !isset($manifest['version'])) {
    otaFail("Manifesto JSON inválido: " . substr($manifestJson, 0, 200), $logFile);
}
otaLog("Manifesto obtido: v{$manifest['version']} | sha256={$manifest['sha256']} | url={$manifest['url']}", $logFile);

// ─── 3. VERIFICA SE HÁ ATUALIZAÇÃO ──────────────────────────────────────────
if (!version_compare($manifest['version'], $currentVersion, '>')) {
    otaLog("Sistema já está na versão $currentVersion. Nenhuma ação necessária.", $logFile);
    echo json_encode([
        'status'  => 'already_updated',
        'message' => "Sistema já está na versão mais recente ($currentVersion).",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── 4. VALIDAÇÃO DA URL DE DOWNLOAD ─────────────────────────────────────────
$downloadUrl = $manifest['url'] ?? '';
if (!$downloadUrl || !filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
    otaFail("URL de download ausente ou inválida no manifesto. url='$downloadUrl'", $logFile);
}

// ─── 5. DOWNLOAD DO PACOTE ───────────────────────────────────────────────────
$tmpDir  = __DIR__ . '/../shared/system/downloads/';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

$zipFile = $tmpDir . 'update_' . $manifest['version'] . '_' . time() . '.zip';
otaLog("Baixando pacote de: $downloadUrl → $zipFile", $logFile);

$fp = fopen($zipFile, 'w+');
if (!$fp) {
    otaFail("Sem permissão de escrita em: $tmpDir", $logFile);
}

$ch = curl_init($downloadUrl);
curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT      => 'SGIM-OTA-Installer/2.0',
]);
$dlSuccess = curl_exec($ch);
$dlHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$dlError    = curl_error($ch);
curl_close($ch);
fclose($fp);

if (!$dlSuccess || $dlHttpCode !== 200 || !file_exists($zipFile) || filesize($zipFile) < 1000) {
    @unlink($zipFile);
    otaFail("Falha no download. HTTP=$dlHttpCode | cURL=$dlError | URL=$downloadUrl", $logFile);
}
otaLog("Download concluído: " . filesize($zipFile) . " bytes", $logFile);

// ─── 6. VALIDAÇÃO DE INTEGRIDADE SHA256 ─────────────────────────────────────
$expectedHash = $manifest['sha256'] ?? '';
if ($expectedHash && strlen($expectedHash) === 64) {
    $actualHash = hash_file('sha256', $zipFile);
    if ($actualHash !== $expectedHash) {
        @unlink($zipFile);
        otaFail("Falha de integridade! Esperado=$expectedHash | Recebido=$actualHash", $logFile);
    }
    otaLog("SHA256 validado com sucesso.", $logFile);
} else {
    otaLog("AVISO: sha256 ausente no manifesto. Pulando validação de integridade.", $logFile);
}

// ─── 7. EXTRAÇÃO DO ZIP ──────────────────────────────────────────────────────
$extractDir = __DIR__ . '/../shared/system/workspace/extract_' . $manifest['version'] . '/';
if (is_dir($extractDir)) {
    // Limpa extração anterior
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
    rmdir($extractDir);
}
mkdir($extractDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($zipFile) !== TRUE) {
    @unlink($zipFile);
    otaFail("Falha ao abrir o arquivo ZIP baixado.", $logFile);
}
$zip->extractTo($extractDir);
$zip->close();
otaLog("ZIP extraído em: $extractDir", $logFile);

// ─── 8. APLICAÇÃO DOS ARQUIVOS (CÓPIA FÍSICA) ────────────────────────────────
$basePath = realpath(__DIR__ . '/../') . '/';

// Arquivos protegidos — nunca sobreescrever
$protectedFiles = [
    'config/db_config.php',
    '.installed',
    'shared/',
    'releases/',
    'backups/',
];

$copied = 0;
$failed = [];
$skipped = [];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $file) {
    if ($file->isDir()) continue;

    $relativePath = substr($file->getPathname(), strlen(realpath($extractDir)) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    // Pula arquivos protegidos
    $isProtected = false;
    foreach ($protectedFiles as $p) {
        if (strpos($relativePath, $p) !== false) { $isProtected = true; break; }
    }
    if ($isProtected) { $skipped[] = $relativePath; continue; }

    $dest    = $basePath . $relativePath;
    $destDir = dirname($dest);
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

    if (@copy($file->getPathname(), $dest)) {
        $copied++;
    } else {
        $failed[] = $relativePath;
    }
}

otaLog("Arquivos copiados: $copied | Falhas: " . count($failed) . " | Protegidos: " . count($skipped), $logFile);
if ($failed) {
    otaLog("FALHAS: " . implode(', ', array_slice($failed, 0, 20)), $logFile);
}

// ─── 9. ATUALIZA VERSÃO E EXECUTA MIGRATIONS ──────────────────────────────────
if ($pdo instanceof PDO) {
    try {
        // Executa Migrations se existirem
        $migrationFile = $extractDir . '/database/migrations_v1.sql';
        if (file_exists($migrationFile)) {
            otaLog("Detectada migration SQL: migrations_v1.sql. Executando...", $logFile);
            $sql = file_get_contents($migrationFile);
            // Divide por ponto e vírgula para executar múltiplas queries
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($queries as $q) {
                if (!empty($q)) $pdo->exec($q);
            }
            otaLog("Migrations aplicadas com sucesso.", $logFile);
        }

        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'versao_sistema'");

        $updated = $stmt->execute([$manifest['version']]);
        if (!$updated || $stmt->rowCount() === 0) {
            // Linha não existia — insere
            $stmt2 = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt2->execute([$manifest['version']]);
        }
        otaLog("Versão atualizada no banco para: " . $manifest['version'], $logFile);
    } catch (Throwable $e) {
        otaLog("AVISO: Falha ao atualizar versão no banco: " . $e->getMessage(), $logFile);
    }
}

// ─── 10. LIMPEZA ─────────────────────────────────────────────────────────────
@unlink($zipFile);
otaLog("=== INSTALAÇÃO CONCLUÍDA ===", $logFile);

// ✅ LOG DE AUDITORIA (Histórico de Sucesso)
$auditLog = __DIR__ . '/../shared/system/logs/ota_audit.log';
$auditMsg = "[" . date('c') . "] SUCCESS: v{$currentVersion} -> v{$manifest['version']} | Files: $copied | User: {$_SESSION['user_id']}\n";
@file_put_contents($auditLog, $auditMsg, FILE_APPEND | LOCK_EX);

$output = [

    'status'         => 'success',
    'message'        => "✅ Atualização v{$manifest['version']} instalada com sucesso! $copied arquivos atualizados.",
    'version_antiga' => $currentVersion,
    'version_nova'   => $manifest['version'],
    'files_copied'   => $copied,
    'files_skipped'  => count($skipped),
    'files_failed'   => count($failed),
    'failures'       => array_slice($failed, 0, 10),
];

// Limpa qualquer aviso/erro que tenha caído no buffer
if (ob_get_length()) ob_end_clean();

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

