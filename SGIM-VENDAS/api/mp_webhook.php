<?php
/**
 * Webhook Mercado Pago - SGIM-VENDAS
 * Recebe notificações automáticas do Mercado Pago sobre mudanças de status.
 * Isso garante a aprovação mesmo que o cliente feche o navegador.
 */
require_once '../config/database.php';
require_once '../includes/MercadoPagoService.php';

// Log de entrada
$log_file = __DIR__ . '/webhook_mp.log';
$input = file_get_contents('php://input');
file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] WEBHOOK RECEIVED: " . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);

if (isset($data['type']) && $data['type'] === 'payment') {
    $payment_id = $data['data']['id'] ?? null;
    
    if ($payment_id) {
        try {
            $mp = new MercadoPagoService(MP_ACCESS_TOKEN);
            $payment = $mp->getPayment($payment_id);
            
            if (isset($payment['status'])) {
                $status_mp = strtolower($payment['status']);
                $pedido_id = $payment['external_reference'] ?? ($payment['metadata']['pedido_id'] ?? null);
                
                if ($pedido_id && ($status_mp === 'approved' || $status_mp === 'authorized')) {
                    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Approving Pedido: $pedido_id\n", FILE_APPEND);
                    
                    // 1. Buscar Pedido e Cliente
                    $stmt = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id = ?");
                    $stmt->execute([$pedido_id]);
                    $pedido = $stmt->fetch();
                    
                    if ($pedido) {
                        $cliente_id = $pedido['cliente_id'];
                        
                        if (!$pdo->inTransaction()) $pdo->beginTransaction();
                        
                        // A. Atualizar Pedido
                        $pdo->prepare("UPDATE pedidos SET status = 'APROVADO', data_venda = NOW() WHERE id = ?")->execute([$pedido_id]);
                        
                        // B. Atualizar Vendas
                        $pdo->prepare("UPDATE vendas SET status = 'APROVADO', data_venda = NOW() WHERE pedido_id = ? OR id = ?")->execute([$pedido_id, $pedido_id]);
                        
                        // C. Atualizar Pagamentos
                        $pdo->prepare("UPDATE pagamentos SET status = 'APROVADO' WHERE pedido_id = ? OR mercadopago_id = ?")->execute([$pedido_id, $payment_id]);
                        
                        // D. Gerar/Ativar Licença
                        $stmtLic = $pdo->prepare("SELECT id FROM licencas WHERE pedido_id = ?");
                        $stmtLic->execute([$pedido_id]);
                        if (!$stmtLic->fetch()) {
                            $parts = [];
                            for($i=0; $i<4; $i++) $parts[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
                            $chave = 'SGIM-' . implode('-', $parts);
                            $api_token = bin2hex(random_bytes(32));
                            
                            $sqlInsLic = "INSERT INTO licencas (cliente_id, pedido_id, chave_licenca, api_token, status, dominio, data_criacao) 
                                          VALUES (?, ?, ?, ?, 'ativa', 'venda_automática', NOW())";
                            $pdo->prepare($sqlInsLic)->execute([$cliente_id, $pedido_id, $chave, $api_token]);
                        } else {
                            $pdo->prepare("UPDATE licencas SET status = 'ativa', dominio = 'venda_automática' WHERE pedido_id = ?")->execute([$pedido_id]);
                        }
                        
                        $pdo->commit();
                        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] SUCCESS: Pedido $pedido_id approved via Webhook.\n", FILE_APPEND);
                    }
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

// O Mercado Pago exige resposta 200/201
http_response_code(200);
echo json_encode(['status' => 'ok']);
