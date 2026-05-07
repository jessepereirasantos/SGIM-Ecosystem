<?php
/**
 * SGIM OTA v2 - Endpoint de Download
 * Protege o binário e garante que apenas clientes ativos baixem o pacote.
 */
// CORRIGIDO: usa o mesmo config que o resto do Master
require_once '../../../config/database.php';

// Para download, usamos GET para facilitar o stream de arquivo
$license_key = $_GET['license_key'] ?? '';
$domain      = $_GET['domain']      ?? '';
$version     = $_GET['version']     ?? '';

if (empty($license_key) || empty($version)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Acesso negado: Parametros ausentes.']));
}

try {
    // 1. Validação de Licença (Lógica Resiliente Blindada)
    $license_key  = trim($license_key);
    $raw_key      = trim(str_ireplace('SGIM-', '', $license_key));
    $prefixed_key = 'SGIM-' . $raw_key;

    $stmtLic = $pdo->prepare("SELECT id, status, dominio FROM licencas WHERE (chave_licenca = ? OR chave_licenca = ? OR chave_licenca LIKE ?) AND status = 'ativa' LIMIT 1");
    $stmtLic->execute([$raw_key, $prefixed_key, "%$raw_key%"]);
    $licenca = $stmtLic->fetch(PDO::FETCH_ASSOC);

    if (!$licenca) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Licença inválida ou inativa no Master.']));
    }

    // 2. UNIFICADO: Localizar o arquivo na tabela 'sistema_updates' (Fonte de Verdade Unificada)
    $stmtVer = $pdo->prepare("SELECT arquivo_zip as url, checksum_md5 as hash FROM sistema_updates WHERE versao = ? ORDER BY id DESC LIMIT 1");
    $stmtVer->execute([$version]);
    $update = $stmtVer->fetch(PDO::FETCH_ASSOC);

    if (!$update) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => "Versão $version não encontrada no repositório."]));
    }

    // O arquivo ZIP está fisicamente na pasta /updates/
    $file_path = __DIR__ . "/../../../updates/" . $update['url'];

    if (!file_exists($file_path)) {
        http_response_code(500);
        die("Arquivo físico não encontrado no servidor Master.");
    }

    // 3. Stream do Arquivo
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="sgim_update_' . $version . '.zip"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    ob_clean();
    flush();
    readfile($file_path);
    
    // Log de download
    // $pdo->prepare("INSERT INTO log_downloads (licenca_id, versao) VALUES (?, ?)")->execute([$licenca['id'], $version]);
    
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die("Erro no servidor Master: " . $e->getMessage());
}
?>
