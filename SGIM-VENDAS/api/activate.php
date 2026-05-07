<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/db.php';

function activation_log($msg) {
    $logFile = __DIR__ . '/activation.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    file_put_contents($logFile, "[$timestamp] [$ip] $msg" . PHP_EOL, FILE_APPEND);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    activation_log("ERROR: Invalid JSON received.");
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$nome = $data['nome'] ?? '';
$email = $data['email'] ?? '';
$telefone = $data['telefone'] ?? '';
$license_key = $data['license_key'] ?? '';
$domain = $data['domain'] ?? '';

activation_log("ATTEMPT: Key=$license_key | Domain=$domain | User=$email");

if (empty($nome) || empty($email) || empty($license_key) || empty($domain)) {
    activation_log("ERROR: Missing fields (Nome=$nome, Email=$email, Key=$license_key, Domain=$domain)");
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Normalização e Busca Resiliente (Suporte a chaves antigas e novas)
$raw_key = str_ireplace('SGIM-', '', $license_key);
$prefixed_key = 'SGIM-' . $raw_key;

// Higienização do domínio (remove www. para busca resiliente)
$domain_clean = preg_replace('/^www\./', '', $domain);

// 1. Verificar se a chave existe na tabela 'licencas'
$stmtLic = $pdo->prepare("SELECT id, status, dominio, chave_licenca FROM licencas WHERE chave_licenca = ? OR chave_licenca = ?");
$stmtLic->execute([$raw_key, $prefixed_key]);
$lic = $stmtLic->fetch(PDO::FETCH_ASSOC);

if (!$lic) {
    // Se a chave não existe no banco de vendas, bloqueia imediatamente.
    // O fluxo profissional exige que o domínio seja fixado ANTES do download.
    activation_log("ERROR: Invalid license key. No record found for: $prefixed_key");
    echo json_encode(['status' => 'error', 'message' => 'Chave de licença inválida. Por favor, verifique sua dashboard de compras.']);
    exit;
} else {
    // Atualizar a chave para o formato oficial do banco para consistência
    $license_key = $lic['chave_licenca'];

    // Vínculo Permanente: Se o domínio já estiver fixado e for diferente do atual, bloqueia.
    $lic_dom_clean = !empty($lic['dominio']) ? preg_replace('/^www\./', '', $lic['dominio']) : '';
    
    if (!empty($lic['dominio']) && $lic['dominio'] !== 'venda_automática' && $lic_dom_clean !== $domain_clean) {
        activation_log("WARNING: Key $license_key is LOCKED to " . $lic['dominio'] . ". Attempt from: $domain_clean");
        echo json_encode(['status' => 'error', 'message' => 'Esta licença é exclusiva para o domínio: ' . $lic['dominio']]);
        exit;
    }

    // Se o domínio na licença for 'venda_automática', significa que o cliente pulou o setup_vendas.php (não recomendado)
    // Mas se o domínio bater ou for a primeira ativação real, procedemos.
    if (empty($lic['dominio']) || $lic['dominio'] === 'venda_automática' || $lic_dom_clean === $domain_clean) {
        activation_log("INFO: Activating license for $domain_clean");
        
        // Atualiza a licença para ativa e fixa o domínio de vez
        $stmtUpdate = $pdo->prepare("UPDATE licencas SET status = 'ativa', dominio = ?, ultimo_acesso = NOW() WHERE id = ?");
        $stmtUpdate->execute([$domain_clean, $lic['id']]);
        
        echo json_encode([
            'status' => 'approved', 
            'message' => 'Sua licença foi ativada com sucesso para este domínio!'
        ]);
        exit;
    }
}

// Check if already requested and pending
$stmtCheckReq = $pdo->prepare("SELECT status FROM activation_requests WHERE license_key = ? AND domain = ? ORDER BY created_at DESC LIMIT 1");
$stmtCheckReq->execute([$license_key, $domain]);
$existingReq = $stmtCheckReq->fetch(PDO::FETCH_ASSOC);

if ($existingReq) {
    if ($existingReq['status'] === 'pending') {
         // Already requested
         echo json_encode(['status' => 'pending', 'message' => 'Pedido já foi enviado. Aguarde aprovação.']);
         exit;
    } elseif ($existingReq['status'] === 'approved') {
         // Should not trigger usually unless they requested again
         echo json_encode(['status' => 'approved', 'message' => 'Sistema já está aprovado e ativo!']);
         exit;
    }
}

// Insert new request
try {
    $stmtInsert = $pdo->prepare("INSERT INTO activation_requests (nome, email, telefone, license_key, domain, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmtInsert->execute([$nome, $email, $telefone, $license_key, $domain]);
    
    activation_log("SUCCESS: Activation request pending for $domain");
    echo json_encode([
        'status' => 'pending', 
        'message' => 'Pedido de ativação enviado com sucesso. Aguarde a liberação do administrador.'
    ]);
} catch (PDOException $e) {
    activation_log("FATAL ERROR: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro interno no servidor']);
}
?>
