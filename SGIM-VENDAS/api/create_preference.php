<?php
// API - Criar Preferência Mercado Pago
require_once '../config/database.php';
$mp_config = require_once '../config/mercadopago.php';

// 1. Receber dados do formulário
$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$documento = $_POST['documento'] ?? '';
$telefone = $_POST['telefone'] ?? '';
$senha = $_POST['senha'] ?? '';
$metodo = $_POST['metodo_pagamento'] ?? 'pix';
$cupom = $_POST['cupom'] ?? '';

// Cálculo de Preço Real via Banco de Dados
$subtotal = 3597.00;
$unit_price = $subtotal;

if (!empty($cupom)) {
    $stmt_c = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ? LIMIT 1");
    $stmt_c->execute([$cupom]);
    $cupom_db = $stmt_c->fetch(PDO::FETCH_ASSOC);

    if ($cupom_db) {
        $agora = date('Y-m-d');
        $valido = true;
        
        // Verificar expiração
        if ($cupom_db['validade'] && strtotime($cupom_db['validade']) < strtotime($agora)) {
            $valido = false;
        }
        
        // Verificar limite de usos
        if ($cupom_db['limite_usos'] > 0 && $cupom_db['usos_atuais'] >= $cupom_db['limite_usos']) {
            $valido = false;
        }

        if ($valido) {
            if ($cupom_db['tipo'] === 'porcentagem') {
                $desconto = $subtotal * ($cupom_db['valor'] / 100);
            } else {
                $desconto = $cupom_db['valor'];
            }
            $unit_price = max(1.00, $subtotal - $desconto);
            
            // Incrementar uso (opcional aqui, melhor no webhook de aprovação, mas incrementamos para controle simples)
            $pdo->query("UPDATE cupons SET usos_atuais = usos_atuais + 1 WHERE id = " . $cupom_db['id']);
        }
    }
}

if (empty($nome) || empty($email)) {
    die("Nome e E-mail são obrigatórios.");
}

// 1. Registrar ou selecionar cliente (Com senha e telefone)
$stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
$stmt->execute([$email]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, documento, senha) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nome, $email, $telefone, $documento, $senha_hash]);
    $cliente_id = $pdo->lastInsertId();
} else {
    $cliente_id = $cliente['id'];
}

// 2. Configurar Preferência
$data = [
    "items" => [
        [
            "title" => "Licença Vitalícia SGIM",
            "quantity" => 1,
            "unit_price" => $unit_price,
            "currency_id" => "BRL"
        ]
    ],
    "payer" => [
        "name" => $nome,
        "email" => $email
    ],
    "back_urls" => [
        "success" => $mp_config['success_url'],
        "failure" => $mp_config['failure_url'],
        "pending" => $mp_config['success_url']
    ],
    "auto_return" => "approved",
    "external_reference" => (string)$cliente_id
];

// 3. Filtrar Métodos de Pagamento (Conectar com a escolha do usuário)
if ($metodo === 'pix') {
    $data["payment_methods"] = ["included_payment_methods" => [["id" => "pix"]]];
} elseif ($metodo === 'boleto') {
    $data["payment_methods"] = ["included_payment_methods" => [["id" => "bolbradesco"]]];
} elseif ($metodo === 'card') {
    $data["payment_methods"] = ["excluded_payment_types" => [["id" => "ticket"], ["id" => "bank_transfer"]]];
}

$ch = curl_init("https://api.mercadopago.com/checkout/preferences");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $mp_config['access_token']
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

$pref = json_decode($response, true);

if (isset($pref['init_point'])) {
    header("Location: " . $pref['init_point']);
    exit;
} else {
    die("Erro ao criar preferência MP: " . ($pref['message'] ?? 'Desconhecido'));
}
?>
