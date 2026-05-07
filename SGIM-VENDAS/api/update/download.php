<?php
/**
 * API: Baixar Pacote de Atualização do SGIM
 * Rota: GET /api/update/download.php
 */
header('Content-Type: application/zip');
require_once '../../config/database.php';

$license_key = trim($_GET['license_key'] ?? '');
$token = trim($_GET['token'] ?? '');

if (empty($license_key) || empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros incompletos.']);
    exit;
}

// 1. Validar token de segurança (anti-leech)
$expected_token = md5($license_key . 'SGIM_SECURE_SALT');
if ($token !== $expected_token) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de download inválido.']);
    exit;
}

// 2. Validar licença no banco
try {
    $stmt = $pdo->prepare("SELECT status FROM clientes WHERE license_key = ?");
    $stmt->execute([$license_key]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente || $cliente['status'] !== 'approved') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Licença não autorizada para download.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao validar permissão.']);
    exit;
}

// 3. Servir o arquivo ZIP (update_package.zip ou sgim_master.zip)
$filePath = __DIR__ . '/../../downloads/sgim_master.zip';

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pacote de atualização não encontrado no servidor.']);
    exit;
}

// Configurar headers para download de arquivo grande
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="sgim_update_' . date('Ymd') . '.zip"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;
