<?php
/**
 * SGIM SaaS API v1: Update Check
 * Retorna os dados da última versão disponível baseada no banco de dados.
 */
header('Content-Type: application/json');
error_log("[TRACE-LEGADO-V1] ACESSO DETECTADO NA ROTA V1. Payload: " . json_encode($_GET));
header('Access-Control-Allow-Origin: *');

try {
    require_once '../../../config/database.php';

    // 1. Garantir que a tabela de atualizações exista
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        versao VARCHAR(20) NOT NULL UNIQUE,
        changelog_json TEXT,
        sql_migration TEXT,
        arquivo_zip VARCHAR(255),
        checksum_md5 VARCHAR(32),
        data_publicacao DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $license_key = trim($_GET['license_key'] ?? '');
    $current_version = trim($_GET['current_version'] ?? '1.0.0');

    if (empty($license_key)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Chave de licença não informada.']);
        exit;
    }

    // 2. Validar Licença (Tabela licencas)
    $stmt = $pdo->prepare("SELECT status, dominio, data_vencimento FROM licencas WHERE chave_licenca = ?");
    $stmt->execute([$license_key]);
    $licenca = $stmt->fetch(PDO::FETCH_ASSOC);

    // Lista expandida de status de sucesso (SaaS Resilience 3.9)
    $status_ativos = ['approved', 'pago', 'ativa', 'active', 'aprovado', 'paid', 'concluido', 'finalizado', 'ativo'];
    
    $status_atual = strtolower($licenca['status'] ?? 'nao_encontrado');

    if (!$licenca || !in_array($status_atual, $status_ativos)) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Licença inválida ou inativa no Servidor Master (Status: ' . $status_atual . '). Por favor, contate o suporte.'
        ]);
        exit;
    }

    // Validar data de vencimento se existir
    if (!empty($licenca['data_vencimento']) && strtotime($licenca['data_vencimento']) < time()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Licença expirada em ' . date('d/m/Y', strtotime($licenca['data_vencimento'])) . '. Renove sua assinatura para continuar recebendo atualizações.'
        ]);
        exit;
    }

    // 3. Buscar Última Versão
    $stmt = $pdo->query("SELECT * FROM sistema_updates ORDER BY id DESC LIMIT 1");
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$latest) {
        // Fallback para versão base se nada estiver cadastrado
        echo json_encode([
            'success' => true,
            'has_update' => false,
            'latest_version' => '1.1.0',
            'message' => 'Nenhuma atualização pendente.'
        ]);
        exit;
    }

    $has_update = version_compare($latest['versao'], $current_version, '>');
    error_log("[TRACE-V1-DECISION] Latest={$latest['versao']} | Current=$current_version | has_update=" . ($has_update ? 'TRUE' : 'FALSE'));

    // Decodificar migrations (Se for JSON estruturado v3.1)
    $migrations = [];
    if (!empty($latest['sql_migration'])) {
        $decoded = json_decode($latest['sql_migration'], true);
        $migrations = is_array($decoded) ? $decoded : [['id' => 'legacy_' . $latest['versao'], 'sql' => $latest['sql_migration']]];
    }

    echo json_encode([
        'success'           => true,
        'has_update'        => $has_update,
        'latest_version'    => $latest['versao'],
        'release_date'      => date('d/m/Y', strtotime($latest['data_publicacao'])),
        'changelog'         => json_decode($latest['changelog_json'], true),
        'checksum'          => $latest['checksum_md5'],
        'migrations'        => $migrations,
        'update_url'        => "api/update/v1/download.php?license_key=" . urlencode($license_key) . "&v=" . $latest['versao']
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno na API: ' . $e->getMessage()
    ]);
}
