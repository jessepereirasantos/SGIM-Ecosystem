<?php
/**
 * API: Verificar Atualização do SGIM
 * Rota: GET /api/update/check.php
 */
header('Content-Type: application/json');
error_log("[TRACE-ORPHAN] ACESSO DETECTADO NO SCRIPT ÓRFÃO. Payload: " . json_encode($_GET));

try {
    require_once '../../config/database.php';

    $license_key = trim($_GET['license_key'] ?? '');
    $current_version = trim($_GET['current_version'] ?? '');
    $domain = trim($_GET['domain'] ?? '');

    if (empty($license_key) || empty($current_version)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parâmetros incompletos.']);
        exit;
    }

    // 1. Validar licença no banco de dados de vendas (Tabela correta: licencas)
    $stmt = $pdo->prepare("SELECT status FROM licencas WHERE chave_licenca = ?");
    $stmt->execute([$license_key]);
    $licenca = $stmt->fetch(PDO::FETCH_ASSOC);

    $status_validados = ['approved', 'pago', 'ativa', 'active', 'aprovado'];
    if (!$licenca || !in_array(strtolower($licenca['status'] ?? ''), $status_validados)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Licença não está ativa ou status é incompatível (' . ($licenca['status'] ?? 'null') . ').']);
        exit;
    }

    // 2. Buscar última versão na fonte de verdade (Banco de Dados)
    $stmt = $pdo->prepare("SELECT valor FROM sistema_config WHERE chave = 'ultima_versao'");
    $stmt->execute();
    $latest_version = $stmt->fetchColumn() ?: '1.1.0';

    $stmt = $pdo->prepare("SELECT valor FROM sistema_config WHERE chave = 'changelog_json'");
    $stmt->execute();
    $changelog_raw = $stmt->fetchColumn() ?: '{}';
    $changelog = json_decode($changelog_raw, true);

    $stmt = $pdo->prepare("SELECT ultima_atualizacao FROM sistema_config WHERE chave = 'ultima_versao'");
    $stmt->execute();
    $raw_date = $stmt->fetchColumn();
    $release_date = $raw_date ? date('Y-m-d', strtotime($raw_date)) : date('Y-m-d');

    $has_update = version_compare($latest_version, $current_version, '>');
    error_log("[TRACE-ORPHAN-DECISION] Latest=$latest_version | Current=$current_version | has_update=" . ($has_update ? 'TRUE' : 'FALSE'));

    echo json_encode([
        'success' => true,
        'has_update' => $has_update,
        'latest_version' => $latest_version,
        'release_date' => $release_date,
        'description' => 'Nova atualização disponível via API SGIM.',
        'changelog' => $changelog,
        'update_url' => 'api/update/download.php?license_key=' . urlencode($license_key) . '&token=' . md5($license_key . 'SGIM_SECURE_SALT')
    ]);

} catch (Throwable $e) {
    http_response_code(200); // Retornar 200 com erro no JSON para evitar 500 genérico
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno na API Master: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
