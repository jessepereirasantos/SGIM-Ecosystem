<?php
/**
 * SGIM SaaS API v1: Update Download
 * Entrega o arquivo ZIP da versão solicitada com validação de licença.
 */
try {
    require_once '../../../config/database.php';

    $license_key = trim($_GET['license_key'] ?? '');
    $version     = trim($_GET['v']           ?? '');

    if (empty($license_key) || empty($version)) {
        header('Content-Type: application/json');
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Parâmetros incompletos.']));
    }

    // 1. Validar Licença
    $stmt = $pdo->prepare("SELECT status FROM licencas WHERE chave_licenca = ?");
    $stmt->execute([$license_key]);
    $licenca = $stmt->fetch(PDO::FETCH_ASSOC);

    $status_ativos = ['approved', 'pago', 'ativa', 'active', 'aprovado', 'paid', 'concluido', 'ativo'];
    if (!$licenca || !in_array(strtolower($licenca['status'] ?? ''), $status_ativos)) {
        header('Content-Type: application/json');
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Licença inválida para download.']));
    }

    // 2. Localizar o arquivo ZIP (Busca inteligente v4.3)
    $stmt = $pdo->prepare("SELECT arquivo_zip FROM sistema_updates WHERE versao = ?");
    $stmt->execute([$version]);
    $update = $stmt->fetch(PDO::FETCH_ASSOC);

    $filePath = null;
    
    // Tentativa 1: Arquivo específico da versão na pasta updates/
    if ($update && !empty($update['arquivo_zip'])) {
        $pathTry = __DIR__ . '/../../../updates/' . $update['arquivo_zip'];
        if (file_exists($pathTry)) $filePath = $pathTry;
    }

    // Tentativa 2: Nome fixo na pasta downloads/ (Preferência do usuário p/ manual)
    if (!$filePath) {
        $pathTry = __DIR__ . '/../../../downloads/sgim-master.zip';
        if (file_exists($pathTry)) $filePath = $pathTry;
    }

    if (!$filePath) {
        header('Content-Type: application/json');
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => "FALHA CRÍTICA: Arquivo da versão $version não encontrado. Por favor, publique a versão novamente ou suba o sgim-master.zip na pasta downloads/ do Master."]));
    }

    // 3. Forçar Download do ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="sgim_update_' . $version . '.zip"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($filePath);
    exit;

} catch (Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]));
}
