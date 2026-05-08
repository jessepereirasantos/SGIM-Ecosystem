<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/MercadoPagoService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválid']);
    exit;
}

// Receber dados via POST (Suporte a JSON e Form-Data)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$nome = $input['nome'] ?? '';
$email = $input['email'] ?? '';
$senha = $input['senha'] ?? '';
$telefone = $input['telefone'] ?? '';
$documento = $input['documento'] ?? '';
$payment_method = $input['payment_method'] ?? 'pix';
$mp_method_id = $payment_method;

if ($payment_method === 'boleto') {
    $mp_method_id = 'bolbradesco';
} elseif ($payment_method === 'card' && isset($input['payment_method_id'])) {
    $mp_method_id = $input['payment_method_id'];
}

if (empty($nome) || empty($email) || empty($senha)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Criar ou buscar usuário
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $usuario_id = $user['id'];
    } else {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, 'cliente')");
        $stmt->execute([$nome, $email, $senhaHash]);
        $usuario_id = $pdo->lastInsertId();
    }

    // 2. Criar ou buscar cliente
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        $cliente_id = $cliente['id'];
    } else {
        $ref_code = strtoupper(substr(md5(uniqid()), 0, 8));
        
        // CONSTRUÇÃO DINÂMICA DA QUERY DE INSERÇÃO (RESILIÊNCIA TOTAL)
        $cols = ['nome', 'email', 'telefone', 'documento'];
        $vals = [$nome, $email, $telefone, $documento];
        $places = ['?', '?', '?', '?'];

        // Verificar usuario_id ou id_usuario
        $checkUserCol = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'usuario_id'")->fetch();
        if ($checkUserCol) {
            $cols[] = 'usuario_id';
            $vals[] = $usuario_id;
            $places[] = '?';
        } else {
            $checkUserIdCol = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'id_usuario'")->fetch();
            if ($checkUserIdCol) {
                $cols[] = 'id_usuario';
                $vals[] = $usuario_id;
                $places[] = '?';
            }
        }

        // Verificar referral_code
        $checkRefCol = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'referral_code'")->fetch();
        if ($checkRefCol) {
            $cols[] = 'referral_code';
            $vals[] = $ref_code;
            $places[] = '?';
        }

        $sql = "INSERT INTO clientes (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $places) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        $cliente_id = $pdo->lastInsertId();
    }

    // 3. Calcular Valor (Cupom)
    $valor = PRODUCT_PRICE;
    $valor_desconto = 0;
    $cupom_codigo = $input['cupom'] ?? '';
    
    if (!empty($cupom_codigo)) {
        require_once '../../includes/functions.php';
        $resultado_cupom = aplicarCupom($cupom_codigo, $valor, $pdo);
        if ($resultado_cupom['success']) {
            $valor_desconto = $resultado_cupom['desconto'];
            $valor = $resultado_cupom['valor_final'];
            
            // Atualizar uso do cupom (DINÂMICO)
            $checkUsosCol = $pdo->query("SHOW COLUMNS FROM cupons LIKE 'usos_atuais'")->fetch();
            if ($checkUsosCol) {
                $stmt = $pdo->prepare("UPDATE cupons SET usos_atuais = usos_atuais + 1 WHERE id = ?");
                $stmt->execute([$resultado_cupom['cupom_id']]);
            } else {
                // Tenta usos_realizados se for o caso antigo
                $checkOldCol = $pdo->query("SHOW COLUMNS FROM cupons LIKE 'usos_realizados'")->fetch();
                if ($checkOldCol) {
                    $stmt = $pdo->prepare("UPDATE cupons SET usos_realizados = usos_realizados + 1 WHERE id = ?");
                    $stmt->execute([$resultado_cupom['cupom_id']]);
                }
            }
        }
    }

    // 4. Criar Pedido (DINÂMICO)
    $colsPed = ['cliente_id', 'valor', 'status'];
    $valsPed = [$cliente_id, $valor, 'PENDENTE'];
    $placesPed = ['?', '?', '?'];

    $checkDescCol = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'valor_desconto'")->fetch();
    if ($checkDescCol) { $colsPed[] = 'valor_desconto'; $valsPed[] = $valor_desconto; $placesPed[] = '?'; }

    $checkNomeCol = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'cliente_nome'")->fetch();
    if ($checkNomeCol) { $colsPed[] = 'cliente_nome'; $valsPed[] = $nome; $placesPed[] = '?'; }

    $checkDataCol = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'data_venda'")->fetch();
    if ($checkDataCol) { $colsPed[] = 'data_venda'; $valsPed[] = date('Y-m-d H:i:s'); $placesPed[] = '?'; }

    $sqlPed = "INSERT INTO pedidos (" . implode(', ', $colsPed) . ") VALUES (" . implode(', ', $placesPed) . ")";
    $stmt = $pdo->prepare($sqlPed);
    $stmt->execute($valsPed);
    $pedido_id = $pdo->lastInsertId();

    // 4b. Sincronizar com tabela VENDAS (Usada na Dashboard)
    try {
        $checkTableVendas = $pdo->query("SHOW TABLES LIKE 'vendas'")->fetch();
        if ($checkTableVendas) {
            $colsVenda = ['valor', 'status'];
            $valsVenda = [$valor, 'PENDENTE'];
            
            // Tenta pegar o nome do cliente se disponível
            $checkColVendaNome = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'cliente_nome'")->fetch();
            if ($checkColVendaNome) { $colsVenda[] = 'cliente_nome'; $valsVenda[] = $nome; }
            
            $checkColVendaData = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'data_venda'")->fetch();
            if ($checkColVendaData) { $colsVenda[] = 'data_venda'; $valsVenda[] = date('Y-m-d H:i:s'); }

            $checkColVendaPedId = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'pedido_id'")->fetch();
            if ($checkColVendaPedId) { $colsVenda[] = 'pedido_id'; $valsVenda[] = $pedido_id; }

            $placesVenda = array_fill(0, count($colsVenda), '?');
            $sqlVenda = "INSERT INTO vendas (" . implode(', ', $colsVenda) . ") VALUES (" . implode(', ', $placesVenda) . ")";
            $stmt = $pdo->prepare($sqlVenda);
            $stmt->execute($valsVenda);
        }
    } catch (Exception $e) {
        // Log silencioso ou ignorar se vendas for apenas auxiliar
    }

    // 5. Integrar com Mercado Pago
    $mp = new MercadoPagoService(MP_ACCESS_TOKEN);
    
    $documento_limpo = preg_replace('/[^0-9]/', '', $documento);
    $doc_type = (strlen($documento_limpo) > 11) ? "CNPJ" : "CPF";
    $nome_parts = explode(' ', trim($nome));
    $first_name = $nome_parts[0];
    $last_name = (count($nome_parts) > 1) ? implode(' ', array_slice($nome_parts, 1)) : 'Master';

    // URL do Webhook dinâmico
    $notification_url = rtrim(SITE_URL, '/') . '/api/mp_webhook.php';

    $payment_data = [
        "transaction_amount" => (float)round($valor, 2),
        "description" => PRODUCT_NAME . " - Pedido #" . $pedido_id,
        "payment_method_id" => $mp_method_id,
        "notification_url" => $notification_url,
        "payer" => [
            "email" => $email,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "identification" => [
                "type" => $doc_type,
                "number" => $documento_limpo
            ]
        ],
        "external_reference" => (string)$pedido_id,
        "metadata" => ["pedido_id" => $pedido_id]
    ];

    if ($payment_method === 'card') {
        $payment_data['token'] = $input['token'] ?? '';
        $payment_data['installments'] = (int)($input['installments'] ?? 1);
        $payment_data['issuer_id'] = (int)($input['issuer_id'] ?? 0);
    }

    $result = $mp->createPayment($payment_data);
    $payment = $result['response'];

    if ($result['status_code'] >= 200 && $result['status_code'] < 300 && isset($payment['id'])) {
        $mercadopago_id = $payment['id'];
        
        $qr_code = '';
        $qr_code_base64 = '';
        $ticket_url = '';

        if ($payment_method === 'pix') {
            $qr_code = $payment['point_of_interaction']['transaction_data']['qr_code'] ?? '';
            $qr_code_base64 = $payment['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
        } elseif ($payment_method === 'boleto') {
            $ticket_url = $payment['transaction_details']['external_resource_url'] ?? '';
        }

        // Atualizar pedido
        $stmt = $pdo->prepare("UPDATE pedidos SET payment_id = ? WHERE id = ?");
        $stmt->execute([$mercadopago_id, $pedido_id]);

        // Registrar Pagamento (DINÂMICO)
        $colsPag = ['pedido_id', 'mercadopago_id', 'status'];
        $valsPag = [$pedido_id, $mercadopago_id, 'PENDENTE'];
        $placesPag = ['?', '?', '?'];

        $checkQrCol = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'qr_code'")->fetch();
        if ($checkQrCol) {
            $colsPag[] = 'qr_code';
            $valsPag[] = $qr_code;
            $placesPag[] = '?';
        }
        $checkQrBaseCol = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'qr_code_base64'")->fetch();
        if ($checkQrBaseCol) {
            $colsPag[] = 'qr_code_base64';
            $valsPag[] = $qr_code_base64;
            $placesPag[] = '?';
        }

        $sqlPag = "INSERT INTO pagamentos (" . implode(', ', $colsPag) . ") VALUES (" . implode(', ', $placesPag) . ")";
        $stmt = $pdo->prepare($sqlPag);
        $stmt->execute($valsPag);

        $pdo->commit();

        // Iniciar sessão
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['usuario_id'] = $usuario_id;
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_email'] = $email;
        $_SESSION['usuario_nivel'] = 'cliente';

        echo json_encode([
            'success' => true,
            'pedido_id' => $pedido_id,
            'status' => $payment['status'] ?? 'pending',
            'qr_code' => $qr_code,
            'qr_code_base64' => $qr_code_base64,
            'ticket_url' => $ticket_url,
            'payment_method' => $payment_method
        ]);
    } else {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $payment['message'] ?? 'Erro no Mercado Pago', 'debug' => $payment]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
