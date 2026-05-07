<?php
// API - Processar Pagamento Checkout Transparente (PIX)
header('Content-Type: application/json');
require_once '../config/database.php';
$mp_config = require_once '../config/mercadopago.php';

// 1. Receber dados
$data_input = json_decode(file_get_contents('php://input'), true);

$nome = $data_input['nome'] ?? '';
$email = $data_input['email'] ?? '';
$documento = $data_input['documento'] ?? '';
$telefone = $data_input['telefone'] ?? '';
$senha = $data_input['senha'] ?? '';
$metodo = $data_input['metodo'] ?? '';
$token = $data_input['token'] ?? ''; // Para cartão
$parcelas = $data_input['installments'] ?? 1;
$issuer_id = $data_input['issuer_id'] ?? null;
$cupom = $data_input['cupom'] ?? '';

if (empty($nome) || empty($email) || empty($senha)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (Nome, E-mail e Senha são obrigatórios).']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Criar ou buscar Usuário
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $usuario_id = $user['id'];
    } else {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, 'cliente')");
        $stmt->execute([$nome, $email, $senha_hash]);
        $usuario_id = $pdo->lastInsertId();
    }

    // 2. Criar ou buscar Cliente
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $cliente_db = $stmt->fetch();

    if ($cliente_db) {
        $cliente_id = $cliente_db['id'];
        if (empty($cliente_db['usuario_id'])) {
            $pdo->prepare("UPDATE clientes SET usuario_id = ? WHERE id = ?")->execute([$usuario_id, $cliente_id]);
        }
    } else {
        $referral_code = strtoupper(substr(md5(uniqid()), 0, 8));
        $stmt = $pdo->prepare("INSERT INTO clientes (usuario_id, nome, email, telefone, documento, referral_code) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $nome, $email, $telefone, $documento, $referral_code]);
        $cliente_id = $pdo->lastInsertId();
    }

    // 3. Cálculo de Preço e Cupom
    $subtotal = 3597.00;
    $unit_price = $subtotal;
    $v_desconto = 0;

    if (!empty($cupom)) {
        $stmt_c = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ? LIMIT 1");
        $stmt_c->execute([$cupom]);
        $cupom_db = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if ($cupom_db) {
            $agora = date('Y-m-d');
            if ((!$cupom_db['validade'] || strtotime($cupom_db['validade']) >= strtotime($agora)) && 
                ($cupom_db['limite_usos'] == 0 || $cupom_db['usos_atuais'] < $cupom_db['limite_usos'])) {
                
                if ($cupom_db['tipo'] === 'porcentagem') {
                    $v_desconto = $subtotal * ($cupom_db['valor'] / 100);
                } else {
                    $v_desconto = $cupom_db['valor'];
                }
                $unit_price = max(1.00, $subtotal - $v_desconto);
                
                $pdo->prepare("UPDATE cupons SET usos_atuais = usos_atuais + 1 WHERE id = ?")->execute([$cupom_db['id']]);
            }
        }
    }

    // 4. Criar Pedido (Venda)
    $stmt = $pdo->prepare("INSERT INTO vendas (cliente_id, valor, valor_desconto, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$cliente_id, $unit_price, $v_desconto]);
    $venda_id = $pdo->lastInsertId();

    // 5. Mapeamento de Métodos Mercado Pago
    $payment_method_id = $metodo;
    if ($metodo === 'boleto') $payment_method_id = 'bolbradesco';

    // 6. Criar Pagamento no Mercado Pago
    $payment_data = [
        "transaction_amount" => (float)round($unit_price, 2),
        "description" => "Licença Vitalícia SGIM - Pedido #$venda_id",
        "payment_method_id" => $payment_method_id,
        "payer" => [
            "email" => $email,
            "first_name" => explode(' ', $nome)[0],
            "identification" => [
                "type" => "CPF",
                "number" => preg_replace('/\D/', '', $documento)
            ]
        ],
        "external_reference" => (string)$venda_id
    ];

    if ($metodo === 'card' && !empty($token)) {
        $payment_data["token"] = $token;
        $payment_data["installments"] = (int)$parcelas;
        $payment_data["issuer_id"] = (int)$issuer_id;
    }

    $ch = curl_init("https://api.mercadopago.com/v1/payments");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $mp_config['access_token'],
        "X-Idempotency-Key: " . uniqid()
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $res = json_decode($response, true);

    if ($http_code == 201 || $http_code == 200) {
        $mercadopago_id = $res['id'];
        $pdo->prepare("UPDATE vendas SET payment_id = ? WHERE id = ?")->execute([$mercadopago_id, $venda_id]);
        $pdo->commit();

        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['usuario_id'] = $usuario_id;
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_nivel'] = 'cliente';

        $resp = ['success' => true, 'pedido_id' => $venda_id, 'payment_id' => $mercadopago_id, 'metodo' => $metodo];
        if ($metodo === 'pix') {
            $resp['qr_code'] = $res['point_of_interaction']['transaction_data']['qr_code'] ?? '';
            $resp['qr_code_base64'] = $res['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
        } elseif ($metodo === 'boleto') {
            $resp['barcode'] = $res['transaction_details']['barcode']['content'] ?? '';
            $resp['ticket_url'] = $res['transaction_details']['external_resource_url'] ?? '';
        }
        echo json_encode($resp);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $res['message'] ?? 'Erro no MP', 'details' => $res['cause'] ?? []]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
