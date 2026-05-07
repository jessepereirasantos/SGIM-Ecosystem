<?php
/**
 * SGIM MASTER - CHECK UPDATE V3.1 (COMPATIBILITY LAYER)
 * Suporta chaves ASCII (novo) e PT-BR (legado)
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    require_once __DIR__ . '/../../../config/database.php';
    $latest_path = __DIR__ . '/../latest.json';
    
    if (!file_exists($latest_path)) {
        die(json_encode(['success' => false, 'message' => 'Manifesto latest.json não encontrado.']));
    }

    $raw_json = file_get_contents($latest_path);
    $json = json_decode($raw_json, true);

    // COMPATIBILIDADE TRANSITÓRIA (MANDATÓRIA)
    $v_master = $json['version'] ?? $json['versão'] ?? $json['versao'] ?? null;
    $package  = $json['package'] ?? $json['pacote'] ?? '';
    $hash     = $json['sha256']  ?? $json['hash'] ?? '';

    if (!$v_master) {
        die(json_encode(['success' => false, 'message' => 'Erro de integridade no manifesto (version_null).']));
    }

    $license_key = trim($_GET['license_key'] ?? '');
    $client_version = trim($_GET['version'] ?? '1.1.0');
    $domain = trim($_GET['domain'] ?? '');

    // Validação básica de licença
    $stmt = $pdo->prepare("SELECT status FROM licencas WHERE chave_licenca = ?");
    $stmt->execute([$license_key]);
    $lic = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lic || !in_array(strtolower($lic['status'] ?? ''), ['approved', 'pago', 'ativa', 'active', 'aprovado', 'paid', 'concluido', 'ativo'])) {
        die(json_encode(['success' => false, 'message' => 'Licença Inválida ou Expirada.']));
    }

    $has_update = version_compare($v_master, $client_version, '>');

    echo json_encode([
        'success' => true,
        'current' => $client_version,
        'latest' => $v_master,
        'has_update' => $has_update,
        'hash' => $hash,
        'url' => "https://escolateologicaeloha.com.br/api/update/v2/download.php?version=$v_master&license_key=$license_key&t=" . time(),
        'release_id' => $json['release_id'] ?? 'legacy'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro Master: ' . $e->getMessage()]);
}
