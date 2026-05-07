<?php
/**
 * SGIM MASTER - PUBLISH ENGINE V3.2 (INTERNATIONAL ASCII STANDARD)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$v = $_POST['versao'] ?? $_POST['version'] ?? null;
$novidades = $_POST['novidades'] ?? $_POST['description'] ?? '';

if (!$v) {
    die(json_encode(['success' => false, 'message' => 'Versão não informada.']));
}

try {
    $release_id = date('Ymd_His');
    $ota_zip_name = "sgim_ota_v" . str_replace('.', '_', $v) . ".zip";
    $ota_zip_path = __DIR__ . "/../../updates/" . $ota_zip_name;

    // 1. Criar ZIP se não existir (Simulação para este teste)
    if (!file_exists($ota_zip_path)) {
        $zip = new ZipArchive();
        if ($zip->open($ota_zip_path, ZipArchive::CREATE) === TRUE) {
            $zip->addFromString('version.json', json_encode(['version' => $v, 'date' => date('Y-m-d')]));
            $zip->close();
        }
    }

    // 2. CONTRATO JSON ASCII INTERNACIONAL (Source of Truth)
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

    // 3. ATUALIZAÇÃO DO BANCO (Legado/Dashboard)
    $stmt = $pdo->prepare("INSERT INTO sistema_updates (versao, changelog_json, arquivo_zip, checksum_md5, data_publicacao) 
                           VALUES (?, ?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE changelog_json=VALUES(changelog_json), arquivo_zip=VALUES(arquivo_zip)");
    $stmt->execute([$v, json_encode(['novidades' => $latest_data['changelog']]), $ota_zip_name, md5_file($ota_zip_path)]);

    $stmtCfg = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'system_version'");
    $stmtCfg->execute([$v]);
    
    clearstatcache(true);

    // 4. INVALIDAÇÃO DE CACHE
    if (function_exists('opcache_reset')) { @opcache_reset(); }

    echo json_encode([
        'success' => true, 
        'message' => "Versão $v publicada com sucesso no padrão ASCII.",
        'release_id' => $release_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
