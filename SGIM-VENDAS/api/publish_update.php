<?php
/**
 * SGIM MASTER - MOTOR DE PUBLICAÇÃO INDUSTRIAL V6.0
 * Fonte Única: latest.json (Atômico)
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config/database.php';

// 1. LOCK GLOBAL DE PUBLICAÇÃO (Evita race conditions)
$lock_file = sys_get_temp_dir() . '/sgim_publish.lock';
$lock_handle = fopen($lock_file, 'w');
if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    die(json_encode(['success' => false, 'message' => 'ERRO: Outra publicação já está em andamento.']));
}

$v = trim($_POST['versao'] ?? '');
$novidades = trim($_POST['novidades'] ?? '');

if (empty($v)) {
    flock($lock_handle, LOCK_UN);
    die(json_encode(['success' => false, 'message' => 'Versão é obrigatória.']));
}

// Logger Estruturado (NDJSON)
function logEvent($step, $level, $msg, $ctx = []) {
    $log_path = __DIR__ . '/../ota_pipeline.log';
    $entry = json_encode(array_merge([
        'ts' => date('c'),
        'step' => $step,
        'level' => $level,
        'msg' => $msg
    ], $ctx));
    file_put_contents($log_path, $entry . "\n", FILE_APPEND);
}

logEvent('START', 'info', "Iniciando publicação da versão $v");

try {
    $base = realpath(__DIR__ . '/../');
    $fonte = realpath($base . '/cliente-atualizacao');
    $ota_dir = $base . '/updates';
    $update_api_dir = $base . '/api/update';
    $latest_path = $update_api_dir . '/latest.json';

    if (!$fonte || !is_dir($fonte)) {
        throw new Exception("Pasta fonte cliente-atualizacao não encontrada.");
    }

    if (!is_dir($update_api_dir)) {
        mkdir($update_api_dir, 0755, true);
    }

    // 2. GERAÇÃO DO PACOTE ZIP
    $ota_zip_name = "sgim_ota_v" . str_replace('.', '_', $v) . ".zip";
    $ota_zip_path = $ota_dir . '/' . $ota_zip_name;
    @mkdir($ota_dir, 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($ota_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Falha ao criar ZIP em $ota_zip_path");
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fonte, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($fonte) + 1);
            // Filtros de segurança
            if (!str_contains($relativePath, 'db_config.php') && !str_contains($relativePath, '.git')) {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    $zip->close();
    
    // 3. HEALTHCHECK DO PACOTE (Integridade)
    if (!file_exists($ota_zip_path) || filesize($ota_zip_path) < 1000) {
        throw new Exception("Falha no Healthcheck: ZIP inexistente ou corrompido.");
    }
    $sha256 = hash_file('sha256', $ota_zip_path);

    // 4. GERAÇÃO ATÔMICA DO LATEST.JSON (Source of Truth)
    $latest_data = [
        'version' => $v,
        'release_id' => date('Ymd_His'),
        'package' => $ota_zip_name,
        'sha256' => $sha256,
        'published_at' => date('c'),
        'changelog' => array_filter(explode("\n", str_replace("\r", "", $novidades))),
        'health' => 'ok'
    ];

    $tmp_latest = $latest_path . '.tmp';
    file_put_contents($tmp_latest, json_encode($latest_data, JSON_PRETTY_PRINT));
    
    // O RENAME é atômico no sistema de arquivos
    if (!rename($tmp_latest, $latest_path)) {
        throw new Exception("Falha ao atualizar latest.json atomicamente.");
    }

    // 5. ATUALIZAÇÃO DO BANCO (Legado/Dashboard)
    // Atualiza a tabela de histórico
    $stmt = $pdo->prepare("INSERT INTO sistema_updates (versao, changelog_json, arquivo_zip, checksum_md5, data_publicacao) 
                           VALUES (?, ?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE changelog_json=VALUES(changelog_json), arquivo_zip=VALUES(arquivo_zip)");
    $stmt->execute([$v, json_encode(['novidades' => $latest_data['changelog']]), $ota_zip_name, md5_file($ota_zip_path)]);

    // ATUALIZAÇÃO CRÍTICA: Sincroniza a tabela de configurações para o Painel do Master
    $stmtCfg = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'system_version'");
    $stmtCfg->execute([$v]);
    
    // Prova de limpeza: resetar cache de estatísticas
    clearstatcache(true);

    // 6. INVALIDAÇÃO AGRESSIVA DE CACHE
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    clearstatcache(true);
    // Tocar nos arquivos da API para forçar re-check do servidor
    @touch(__DIR__ . '/update/v2/check.php');
    @touch($latest_path);

    logEvent('SUCCESS', 'info', "Versão $v publicada com sucesso.", ['sha256' => $sha256]);

    echo json_encode([
        'success' => true,
        'version' => $v,
        'latest_json' => $latest_data
    ]);

} catch (Exception $e) {
    logEvent('ERROR', 'error', $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    flock($lock_handle, LOCK_UN);
    fclose($lock_handle);
}
