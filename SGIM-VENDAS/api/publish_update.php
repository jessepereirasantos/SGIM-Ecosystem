<?php
/**
 * SGIM MASTER - PUBLISH ENGINE V3.3 (PATH CORRECTION)
 */
header('Content-Type: application/json');

// CORREÇÃO DE CAMINHO: api/ -> ../config/
if (!file_exists(__DIR__ . '/../config/database.php')) {
    die(json_encode(['success' => false, 'message' => 'Erro de Infraestrutura: database.php não localizado em ' . realpath(__DIR__ . '/../config/')]));
}
require_once __DIR__ . '/../config/database.php';

$v = $_POST['versao'] ?? $_POST['version'] ?? null;
$novidades = $_POST['novidades'] ?? $_POST['description'] ?? '';

if (!$v) {
    die(json_encode(['success' => false, 'message' => 'Versão não informada.']));
}

try {
    $release_id = date('Ymd_His');
    $ota_zip_name = "sgim_ota_v" . str_replace('.', '_', $v) . ".zip";
    
    // CORREÇÃO DE CAMINHO: api/ -> ../updates/
    $ota_zip_path = __DIR__ . "/../updates/" . $ota_zip_name;

    // 1. Criar ZIP se não existir
    if (!file_exists($ota_zip_path)) {
        if (!is_dir(dirname($ota_zip_path))) {
            mkdir(dirname($ota_zip_path), 0755, true);
        }
        $zip = new ZipArchive();
        if ($zip->open($ota_zip_path, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString('version.json', json_encode(['version' => $v, 'date' => date('Y-m-d')]));
            $zip->close();
        }
    }

    // 2. CONTRATO JSON ASCII INTERNACIONAL
    $latest_data = [
        'version'      => $v,
        'release_id'   => $release_id,
        'package'      => $ota_zip_name,
        'sha256'       => hash_file('sha256', $ota_zip_path),
        'published_at' => date('c'),
        'changelog'    => [$v . ": " . $novidades],
        'health'       => 'ok'
    ];

    $latest_file = __DIR__ . '/update/latest.json';
    file_put_contents($latest_file, json_encode($latest_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // 3. ATUALIZAÇÃO DO BANCO
    $stmt = $pdo->prepare("INSERT INTO sistema_updates (versao, changelog_json, arquivo_zip, checksum_md5, data_publicacao) 
                           VALUES (?, ?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE changelog_json=VALUES(changelog_json), arquivo_zip=VALUES(arquivo_zip)");
    $stmt->execute([$v, json_encode(['novidades' => $latest_data['changelog']]), $ota_zip_name, md5_file($ota_zip_path)]);

    $stmtCfg = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'system_version'");
    $stmtCfg->execute([$v]);
    
    clearstatcache(true);

    if (function_exists('opcache_reset')) { @opcache_reset(); }

    echo json_encode([
        'success' => true, 
        'message' => "Versão $v publicada com sucesso (Paths corrigidos).",
        'release_id' => $release_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
