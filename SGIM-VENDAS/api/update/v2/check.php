<?php
/**
 * SGIM MASTER - CHECK UPDATE V3.2 (EMERGENCY BYPASS)
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
error_log("[TRACE-MASTER-V2] Requisição recebida. Payload: " . json_encode($_GET));

try {
    require_once __DIR__ . '/../../../config/database.php';
    $latest_path = __DIR__ . '/../latest.json';
    
    if (!file_exists($latest_path)) {
        die(json_encode(['success' => false, 'message' => 'Manifesto latest.json não encontrado.']));
    }

    $json = json_decode(file_get_contents($latest_path), true);
    $v_master = $json['version'] ?? null;

    $license_key = trim($_GET['license_key'] ?? '');
    $client_version = trim($_GET['version'] ?? '1.1.0');

    // --- BLOCO DE SEGURANÇA (REGRAS DE BYPASS PARA TESTE) ---
    $is_valid_license = false;
    
    // 1. Bypass para suporte/desenvolvimento
    if ($license_key === 'SUPORTE_VIP_TESTE' || empty($license_key)) {
        $is_valid_license = true;
    } else {
        // 2. Consulta real no banco
        $stmt = $pdo->prepare("SELECT status FROM licencas WHERE chave_licenca = ?");
        $stmt->execute([$license_key]);
        $lic = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $status_ativos = ['approved', 'pago', 'ativa', 'active', 'aprovado', 'paid', 'concluido', 'ativo'];
        if ($lic && in_array(strtolower($lic['status'] ?? ''), $status_ativos)) {
            $is_valid_license = true;
        }
    }

    // 3. FALHA DE SEGURANÇA (Se não passou nas regras acima)
    if (!$is_valid_license) {
        die(json_encode(['success' => false, 'message' => 'Licença Rejeitada pelo Master.']));
    }

    $has_update = version_compare($v_master, $client_version, '>');
    error_log("[TRACE-V2-DECISION] Master=$v_master | Client=$client_version | has_update=" . ($has_update ? 'TRUE' : 'FALSE'));

    echo json_encode([
        'success' => true,
        'current' => $client_version,
        'latest' => $v_master,
        'has_update' => $has_update,
        'hash' => $json['sha256'] ?? '',
        'url' => "https://escolateologicaeloha.com.br/api/update/v2/download.php?version=$v_master&license_key=$license_key&t=" . time(),
        'release_id' => $json['release_id'] ?? 'legacy'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro Master: ' . $e->getMessage()]);
}
