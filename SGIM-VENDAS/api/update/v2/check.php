<?php
/**
 * SGIM MASTER - CHECK UPDATE V2.2 (SaaS Professional Edition)
 * DIAGNOSTICO: FIX-1064-DEFENSIVO-V3
 * Este arquivo unifica a validação de licença e entrega de versão.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('X-SGIM-Timestamp: ' . time());

if (ob_get_level()) ob_end_clean();

try {
    require_once __DIR__ . '/../../../config/database.php';

    /**
     * Função Universal de Migração Defensiva (SGIM v5.1 - Standalone)
     */
    if (!function_exists('ensureColumnExists')) {
        function ensureColumnExists($pdo, $table, $column, $definition) {
            try {
                if (!($pdo instanceof PDO)) return false;
                $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                if ($check->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
                    return true;
                }
            } catch (Exception $e) {
                error_log("Erro de Migração Master ($table.$column): " . $e->getMessage());
            }
            return false;
        }
    }

    // 1. MIGRAÇÃO DE SEGURANÇA UNIFICADA (Compatível com HostGator)
    ensureColumnExists($pdo, 'licencas', 'data_vencimento', 'DATE NULL AFTER status');

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
    $client_version = trim($_GET['version'] ?? '1.1.0');
    $domain = trim($_GET['domain'] ?? '');

    if (empty($license_key)) {
        die(json_encode(['success' => false, 'message' => 'Licença não fornecida.']));
    }

    // 2. VALIDAR LICENÇA
    $stmt = $pdo->prepare("SELECT status, data_vencimento FROM licencas WHERE chave_licenca = ?");
    $stmt->execute([$license_key]);
    $lic = $stmt->fetch(PDO::FETCH_ASSOC);

    $status_ativos = ['approved', 'pago', 'ativa', 'active', 'aprovado', 'paid', 'concluido', 'finalizado', 'ativo'];
    $status_atual = strtolower($lic['status'] ?? 'nao_encontrado');

    if (!$lic || !in_array($status_atual, $status_ativos)) {
        die(json_encode(['success' => false, 'message' => "Licença Inativa ou Não Encontrada (Status: $status_atual)"]));
    }

    // 3. BUSCAR ÚLTIMA VERSÃO (Ordenação Cronológica e por ID para desempate)
    $stmtVer = $pdo->query("SELECT * FROM sistema_updates ORDER BY data_publicacao DESC, id DESC LIMIT 1");
    $latest = $stmtVer->fetch(PDO::FETCH_ASSOC);

    $v_master = $latest['versao'] ?? '1.1.0';
    $has_update = version_compare($v_master, $client_version, '>');

    // Log para auditoria
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $log = "[" . date('Y-m-d H:i:s') . "] [CHECK v2] $ip | Key: $license_key | $client_version -> $v_master\n";
    file_put_contents(__DIR__ . '/../../../ota_v2.log', $log, FILE_APPEND);

    echo json_encode([
        'success' => true,
        'current' => $client_version,
        'latest' => $v_master,
        'has_update' => $has_update,
        'hash' => $latest['checksum_md5'] ?? '',
        'url' => "https://escolateologicaeloha.com.br/api/update/v2/download.php?version=$v_master&license_key=$license_key",
        'changelog' => json_decode($latest['changelog_json'] ?? '[]', true)['novidades'] ?? ['Melhorias gerais de estabilidade.']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro Master: ' . $e->getMessage()]);
}
