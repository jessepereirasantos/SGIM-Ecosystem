<?php
/**
 * API: Verificar Ativação de Domínio
 * Rota: GET /api/check-domain.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$domain = trim($_GET['domain'] ?? '');

if (empty($domain)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Domínio não informado.']);
    exit;
}

// Higienização do domínio (remove www. para busca resiliente)
$domain_clean = preg_replace('/^www\./', '', $domain);

try {
    // 1. Busca Unificada em Licenças, Solicitações e Pedidos
    // Status aceitáveis: ativa, active, aprovado, pago, approved
    $status_ativos = "'ativa', 'active', 'aprovado', 'pago', 'approved'";

    // 3. Busca por Pedidos Aprovados (Suporte a Venda Automática e Vínculo Manual)
    // Nota: Esta query é a mais abrangente para garantir que o cliente não seja bloqueado após a compra.
    $auto = null;
    try {
        $stmtAuto = $pdo->prepare("SELECT l.chave_licenca, l.status, l.dominio, 'auto_sale_check' as origem
                                   FROM licencas l 
                                   JOIN pedidos p ON l.pedido_id = p.id 
                                   WHERE p.status IN ('APROVADO', 'PAGO', 'APPROVED')
                                   AND (l.dominio = 'venda_automática' OR l.dominio = ? OR l.dominio = ? OR l.dominio LIKE ?)
                                   LIMIT 1");
        $stmtAuto->execute([$domain, $domain_clean, "%$domain_clean%"]);
        $auto = $stmtAuto->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $dbEx) {
        error_log("Check-Domain API Warning: Falha na query de pedidos aprovados. Verifique se a coluna pedido_id existe em licencas. Detalhes: " . $dbEx->getMessage());
    }

    if ($auto) {
        echo json_encode([
            'success' => true,
            'is_activated' => true,
            'license_key' => $auto['chave_licenca'],
            'source' => $auto['origem'],
            'status' => $auto['status']
        ]);
        exit;
    }

    $sql = "SELECT chave_licenca, status, dominio, 'licencas' as origem 
            FROM licencas 
            WHERE (dominio = ? OR dominio = ? OR dominio LIKE ?) AND LOWER(status) IN ($status_ativos)
            UNION
            SELECT license_key as chave_licenca, status, domain as dominio, 'activation_requests' as origem 
            FROM activation_requests 
            WHERE (domain = ? OR domain = ? OR domain LIKE ?) AND LOWER(status) IN ($status_ativos)
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $domain, $domain_clean, "%$domain_clean%",
        $domain, $domain_clean, "%$domain_clean%"
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'is_activated' => true,
            'license_key' => $result['chave_licenca'],
            'source' => $result['origem'],
            'status' => $result['status']
        ]);
        exit;
    }

    // Se não encontrou nada, retorna status 'none'
    echo json_encode([
        'success' => true,
        'is_activated' => false,
        'status' => 'none',
        'message' => 'Nenhum registro encontrado para este domínio.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao consultar domínio: ' . $e->getMessage()]);
}
?>
