<?php
// API - Callback Mercado Pago (Retorno da Compra)
require_once '../config/database.php';
require_once '../includes/license_generator.php';
$mp_config = require_once '../config/mercadopago.php';

$payment_id = $_GET['payment_id'] ?? '';
$cliente_id = $_GET['external_reference'] ?? '';

if (empty($payment_id)) {
    header("Location: ../index.php?page=falha");
    exit;
}

// 5. Consultar API do Mercado Pago diretamente
$ch = curl_init("https://api.mercadopago.com/v1/payments/" . $payment_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $mp_config['access_token']
]);

$response = curl_exec($ch);
curl_close($ch);

$payment = json_decode($response, true);

// Validar Approved
if (isset($payment['status']) && $payment['status'] === 'approved') {
    
    // Registrar Venda
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO vendas (cliente_id, valor, payment_id, status) VALUES (?, ?, ?, 'approved')");
        $stmt->execute([$cliente_id, $payment['transaction_amount'], $payment_id]);
        
        // Gerar Licença SGIM-XXXX-XXXX
        $chave = gerarChaveLicenca();
        $api_token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d', strtotime('+1 year'));

        $stmtLic = $pdo->prepare("INSERT INTO licencas (cliente_id, chave_licenca, api_token, status, data_expiracao) VALUES (?, ?, ?, 'pendente', ?)");
        $stmtLic->execute([$cliente_id, $chave, $api_token, $expira]);

        // Redirecionar para página de sucesso personalizada (Stitch)
        header("Location: ../index.php?page=sucesso&key=" . $chave);
        exit;
        
    } catch (PDOException $e) {
        die("Erro ao processar licença: " . $e->getMessage());
    }
} else {
    header("Location: ../index.php?page=pendente");
    exit;
}
?>
