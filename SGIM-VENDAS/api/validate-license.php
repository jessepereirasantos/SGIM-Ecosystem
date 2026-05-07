<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/db.php';

// Obter JSON raw
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['license_key']) || !isset($data['domain'])) {
    echo json_encode(['status' => 'error', 'message' => 'Parâmetros ausentes']);
    exit;
}

$license_key = $data['license_key'];
$domain = $data['domain'];
$api_token = $data['api_token'] ?? null;

// Normalização e Busca Resiliente
$raw_key = str_ireplace('SGIM-', '', $license_key);
$prefixed_key = 'SGIM-' . $raw_key;

$stmt = $pdo->prepare("SELECT * FROM licencas WHERE chave_licenca = ? OR chave_licenca = ?");
$stmt->execute([$raw_key, $prefixed_key]);
$licenca = $stmt->fetch(PDO::FETCH_ASSOC);

if ($licenca) {
    // Atualiza a variável para o valor exato no banco
    $license_key = $licenca['chave_licenca'];
} else {
    // Busca em activation_requests caso a licença ainda não esteja na tabela principal
    $stmtReq = $pdo->prepare("SELECT status FROM activation_requests WHERE (license_key = ? OR license_key = ?) AND domain = ? ORDER BY created_at DESC LIMIT 1");
    $stmtReq->execute([$raw_key, $prefixed_key, $domain]);
    $req = $stmtReq->fetch(PDO::FETCH_ASSOC);
    
    if ($req) {
        if ($req['status'] === 'pending') {
             echo json_encode(['status' => 'pending', 'message' => 'Pedido de ativação em análise.']);
             exit;
        } elseif ($req['status'] === 'rejected') {
             echo json_encode(['status' => 'rejected', 'message' => 'Pedido de ativação foi rejeitado.']);
             exit;
        }
    }

    echo json_encode(['status' => 'invalid', 'message' => 'Licença não encontrada ou não solicitada para este domínio.']);
    exit;
}

// Se a licença existe mas está pendente/inativa na tabela principal
if ($licenca['status'] === 'pendente' || $licenca['status'] === 'inativa') {
    // Verifica se tem pedido aprovado mas ainda não refletido (redundância)
    $stmtReq = $pdo->prepare("SELECT status FROM activation_requests WHERE license_key = ? AND domain = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
    $stmtReq->execute([$license_key, $domain]);
    $req = $stmtReq->fetch(PDO::FETCH_ASSOC);
    
    if ($req) {
        // Se no activation_requests está aprovado, vamos considerar ativo
        echo json_encode([
            'status' => 'active', 
            'message' => 'Licença aprovada!',
            'api_token' => $licenca['api_token']
        ]);
        exit;
    }
    
    echo json_encode(['status' => 'pending', 'message' => 'Aguardando liberação do administrador.']);
    exit;
}

// Se chegou aqui a licença deve ser 'ativa' e o domínio deve bater
if ($licenca['status'] !== 'ativa' || $licenca['dominio'] !== $domain) {
    echo json_encode(['status' => 'invalid', 'message' => 'Domínio não autorizado ou licença inativa']);
    exit;
}

// Validar token da API se fornecido (para requisições contínuas a cada 24h)
if ($api_token && $licenca['api_token'] !== $api_token) {
    echo json_encode(['status' => 'invalid', 'message' => 'Token API incorreto']);
    exit;
}

// Verificar expiração
if ($licenca['data_expiracao'] && strtotime($licenca['data_expiracao']) < time()) {
    echo json_encode(['status' => 'invalid', 'message' => 'Licença expirada']);
    // Atualiza status se desejar: $pdo->exec("UPDATE licencas SET status='expirada'...");
    exit;
}

// Tudo certo. Atualizar último acesso
$updateStmt = $pdo->prepare("UPDATE licencas SET ultimo_acesso = NOW() WHERE id = :id");
$updateStmt->execute(['id' => $licenca['id']]);

// Emite resposta (incluindo api_token para quando o cliente acabou de ser aprovado e ainda não tem o token)
echo json_encode([
    'status' => 'active', 
    'message' => 'Licença válida e ativa',
    'api_token' => $licenca['api_token']
]);
?>
