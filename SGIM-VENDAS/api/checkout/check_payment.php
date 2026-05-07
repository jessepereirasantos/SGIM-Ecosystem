<?php
/**
 * Verificação de Pagamento Ativa (Polling) - SGIM-VENDAS
 * Este arquivo é consultado pelo checkout a cada 5 segundos.
 * Se o pagamento for aprovado, ele realiza todas as atualizações e libera o acesso.
 */
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../../config/database.php';
require_once '../../includes/MercadoPagoService.php';

$pedido_id = $_GET['pedido_id'] ?? 0;

if (!$pedido_id) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido não fornecido.']);
    exit;
}

try {
    // 1. Buscar o pedido para obter o cliente e o payment_id
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        error_log("[SGIM-ERROR] Pedido $pedido_id não encontrado.");
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    // 2. Se o status local já for 'APROVADO', retornar sinal de redirecionamento
    if (in_array(strtoupper($pedido['status'] ?? ''), ['APROVADO', 'PAGO', 'CONCLUIDO', 'APPROVED'])) {
        $redirect_url = rtrim(SITE_URL, '/') . '/cliente/dashboard.php?success=1';
        echo json_encode(['success' => true, 'status' => 'approved', 'redirect' => $redirect_url]);
        exit;
    }

    // 3. Se não temos payment_id ainda, aguarda
    if (empty($pedido['payment_id'])) {
        echo json_encode(['success' => true, 'status' => 'pending']);
        exit;
    }

    // 4. Consultar API do Mercado Pago (Verificação Ativa)
    $mp = new MercadoPagoService(MP_ACCESS_TOKEN);
    $payment = $mp->getPayment($pedido['payment_id']);

    // Log para depuração profunda - Usando caminho relativo
    $log_file = __DIR__ . '/debug_payment.log';
    $log_msg = "[" . date('Y-m-d H:i:s') . "] Pedido: $pedido_id | MP_ID: {$pedido['payment_id']} | Status MP: " . ($payment['status'] ?? 'FALHA') . " | Raw Status: " . json_encode($payment) . "\n";
    file_put_contents($log_file, $log_msg, FILE_APPEND);

    if (!isset($payment['status'])) {
        echo json_encode(['success' => false, 'message' => 'Não foi possível consultar o Mercado Pago.']);
        exit;
    }

    $status_mp = strtolower($payment['status']);

    // 5. Se Aprovado no MP, processar a liberação total no sistema
    if ($status_mp === 'approved' || $status_mp === 'authorized' || $status_mp === 'approved_manual') {
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] PROCESSING APPROVAL for Pedido: $pedido_id\n", FILE_APPEND);
        try {
            // Sincronização forçada imediata
            require_once __DIR__ . '/../../sync_all.php';

            // Redirecionamento para a Dashboard de Compras (v2)
            $redirect_url = rtrim(SITE_URL, '/') . '/cliente/dashboard.php?success=1';
            echo json_encode(['success' => true, 'status' => 'approved', 'redirect' => $redirect_url]);
            exit;
        } catch (Exception $e) {
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Erro crítico: ' . $e->getMessage()]);
            exit;
        }
    }

    // 6. Caso contrário, retorna o status atual
    echo json_encode(['success' => true, 'status' => $status_mp]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
