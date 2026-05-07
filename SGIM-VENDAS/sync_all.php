<?php
/**
 * SGIM-VENDAS - Sincronização Forçada e Auto-Reparo (v1.6.9)
 * Este script força a atualização de todos os pedidos pendentes consultando o Mercado Pago.
 * É o coração da automação para quebrar o ciclo de aprovação manual.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/MercadoPagoService.php';

// Evita output duplicado se for incluído por outros arquivos
if (basename($_SERVER['PHP_SELF']) == 'sync_all.php') {
    header('Content-Type: application/json');
}

$log_file = __DIR__ . '/debug_sync.log';

try {
    $mp = new MercadoPagoService(MP_ACCESS_TOKEN);
    $synchronized_logs = [];

    // 1. Buscar todos os pedidos marcados como PENDENTE nas últimas 48 horas
    $stmt = $pdo->query("SELECT id, payment_id, cliente_id FROM pedidos WHERE status = 'PENDENTE' AND data_venda >= DATE_SUB(NOW(), INTERVAL 2 DAY)");
    $pedidos_pendentes = $stmt->fetchAll();

    foreach ($pedidos_pendentes as $p) {
        $pid = $p['id'];
        $cid = $p['cliente_id'];
        
        // Tenta buscar o payment_id na tabela pagamentos caso esteja vazio no pedido
        $mp_id = !empty($p['payment_id']) ? $p['payment_id'] : null;
        
        if (empty($mp_id)) {
            $stmtPag = $pdo->prepare("SELECT mercadopago_id FROM pagamentos WHERE pedido_id = ? LIMIT 1");
            $stmtPag->execute([$pid]);
            $pag = $stmtPag->fetch();
            $mp_id = $pag['mercadopago_id'] ?? null;
        }

        // Se ainda não temos ID do Mercado Pago, não há o que sincronizar
        if (empty($mp_id)) continue;

        // 2. Consultar o Mercado Pago diretamente
        $payment_data = $mp->getPayment($mp_id);
        $status_mp = strtolower($payment_data['status'] ?? '');
        
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Pedido #$pid | MP_ID: $mp_id | Status MP: $status_mp\n", FILE_APPEND);

        // 3. Se estiver Aprovado ou Autorizado, forçar a liberação total
        if ($status_mp === 'approved' || $status_mp === 'authorized') {
            try {
                if (!$pdo->inTransaction()) $pdo->beginTransaction();

                // 1. Atualizar Pedido
                $pdo->prepare("UPDATE pedidos SET status = 'APROVADO', data_venda = NOW() WHERE id = ?")->execute([$pid]);

                // 2. Atualizar Vendas (Sincronização Dashboard Master)
                $checkVenda = $pdo->prepare("SELECT id FROM vendas WHERE pedido_id = ? OR id = ?");
                $checkVenda->execute([$pid, $pid]);
                if ($vRow = $checkVenda->fetch()) {
                    $pdo->prepare("UPDATE vendas SET status = 'APROVADO', data_venda = NOW() WHERE id = ?")->execute([$vRow['id']]);
                } else {
                    $pdo->prepare("INSERT INTO vendas (pedido_id, cliente_id, status, valor, data_venda) VALUES (?, ?, 'APROVADO', 0, NOW())")->execute([$pid, $cid]);
                }

                // 3. Atualizar Pagamentos
                $pdo->prepare("UPDATE pagamentos SET status = 'APROVADO' WHERE pedido_id = ? OR mercadopago_id = ?")->execute([$pid, $mp_id]);

                // 4. Gerar ou Ativar Licença
                $stmtLic = $pdo->prepare("SELECT id FROM licencas WHERE pedido_id = ?");
                $stmtLic->execute([$pid]);
                if (!$stmtLic->fetch()) {
                    $p_parts = [];
                    for($i=0; $i<4; $i++) $p_parts[] = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
                    $new_key = 'SGIM-' . implode('-', $p_parts);
                    $new_token = bin2hex(random_bytes(32));
                    
                    $sqlInsLic = "INSERT INTO licencas (cliente_id, pedido_id, chave_licenca, api_token, status, dominio, data_criacao) 
                                  VALUES (?, ?, ?, ?, 'ativa', 'venda_automática', NOW())";
                    $pdo->prepare($sqlInsLic)->execute([$cid, $pid, $new_key, $new_token]);
                } else {
                    $pdo->prepare("UPDATE licencas SET status = 'ativa', dominio = 'venda_automática' WHERE pedido_id = ?")->execute([$pid]);
                }

                $pdo->commit();
                $synchronized_logs[] = "Pedido #$pid: STATUS UNIFICADO PARA APROVADO EM TODAS AS TABELAS.";
                file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] ATOMIC SUCCESS: Pedido #$pid unificado.\n", FILE_APPEND);

                // --- NOVO: Envio de E-mail de Confirmação com a Chave de Licença ---
                try {
                    require_once __DIR__ . '/includes/EmailService.php';
                    
                    // Buscar dados do cliente e a chave gerada para o e-mail
                    $stmtCli = $pdo->prepare("SELECT nome, email FROM clientes WHERE id = ?");
                    $stmtCli->execute([$cid]);
                    $cliente = $stmtCli->fetch();
                    
                    $stmtKey = $pdo->prepare("SELECT chave_licenca FROM licencas WHERE pedido_id = ?");
                    $stmtKey->execute([$pid]);
                    $lic = $stmtKey->fetch();
                    
                    if ($cliente && $lic) {
                        EmailService::sendOrderApproved(
                            $cliente['email'], 
                            $cliente['nome'], 
                            $lic['chave_licenca'], 
                            $pid
                        );
                        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] EMAIL SENT: Pedido #$pid para {$cliente['email']}\n", FILE_APPEND);
                    }
                } catch (Exception $eMail) {
                    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] EMAIL ERROR Pedido #$pid: " . $eMail->getMessage() . "\n", FILE_APPEND);
                }
                // --- FIM DO ENVIO DE EMAIL ---

            } catch (Exception $sqlE) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] SQL ERROR Pedido #$pid: " . $sqlE->getMessage() . "\n", FILE_APPEND);
            }
        }
    }

    if (basename($_SERVER['PHP_SELF']) == 'sync_all.php') {
        echo json_encode(['success' => true, 'synchronized' => $synchronized_logs]);
    }

} catch (Exception $e) {
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] GLOBAL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    if (basename($_SERVER['PHP_SELF']) == 'sync_all.php') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
