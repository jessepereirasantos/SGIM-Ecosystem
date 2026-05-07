<?php
/**
 * SGIM MASTER - CHECK UPDATE V3.0 (Industrial Edition)
 * Fonte Única de Verdade: latest.json
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-SGIM-Timestamp: ' . time());

// 1. NONCE / TIMESTAMP VALIDATION
$nonce = $_GET['t'] ?? $_GET['nonce'] ?? null;

try {
    require_once __DIR__ . '/../../../config/database.php';

    $latest_path = __DIR__ . '/../latest.json';
    
    // 2. LEITURA DETERMINÍSTICA DO LATEST.JSON
    if (!file_exists($latest_path)) {
        // Fallback para o banco caso o latest.json ainda não tenha sido gerado na primeira vez
        $stmtVer = $pdo->query("SELECT * FROM sistema_updates ORDER BY data_publicacao DESC, id DESC LIMIT 1");
        $latest_db = $stmtVer->fetch(PDO::FETCH_ASSOC);
        
        if (!$latest_db) {
            die(json_encode(['success' => false, 'message' => 'Nenhuma versão disponível no Master.']));
        }
        
        $latest_data = [
            'version' => $latest_db['versao'],
            'package' => $latest_db['arquivo_zip'],
            'sha256' => hash_file('sha256', __DIR__ . '/../../../updates/' . $latest_db['arquivo_zip']),
            'changelog' => json_decode($latest_db['changelog_json'] ?? '[]', true)['novidades'] ?? []
        ];
    } else {
        $latest_data = json_decode(file_get_contents($latest_path), true);
    }

    $license_key = trim($_GET['license_key'] ?? '');
    $client_version = trim($_GET['version'] ?? '1.1.0');
    $domain = trim($_GET['domain'] ?? '');

    if (empty($license_key)) {
        die(json_encode(['success' => false, 'message' => 'Licença obrigatória.']));
    }

    // 3. VALIDAÇÃO DE LICENÇA (Cacheada no banco)
    $stmt = $pdo->prepare("SELECT status FROM licencas WHERE chave_licenca = ?");
    $stmt->execute([$license_key]);
    $lic = $stmt->fetch(PDO::FETCH_ASSOC);

    $status_ativos = ['approved', 'pago', 'ativa', 'active', 'aprovado', 'paid', 'concluido', 'finalizado', 'ativo'];
    $status_atual = strtolower($lic['status'] ?? 'inativo');

    if (!$lic || !in_array($status_atual, $status_ativos)) {
        die(json_encode(['success' => false, 'message' => "Licença Inválida ($status_atual)"]));
    }

    // 4. RESPOSTA DETERMINÍSTICA
    $v_master = $latest_data['version'];
    $has_update = version_compare($v_master, $client_version, '>');

    echo json_encode([
        'success' => true,
        'current' => $client_version,
        'latest' => $v_master,
        'has_update' => $has_update,
        'hash' => $latest_data['sha256'] ?? '',
        'url' => "https://escolateologicaeloha.com.br/api/update/v2/download.php?version=$v_master&license_key=$license_key&t=" . time(),
        'changelog' => $latest_data['changelog'] ?? [],
        'release_id' => $latest_data['release_id'] ?? 'legacy'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro Master: ' . $e->getMessage()]);
}
