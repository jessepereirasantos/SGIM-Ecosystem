<?php
/**
 * Funções Globais - SGIM-VENDAS
 * Replicado do Backup.
 */

function aplicarCupom($codigo, $valorPedido, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM cupons WHERE UPPER(codigo) = UPPER(?) LIMIT 1");
        $stmt->execute([$codigo]);
        $cupom = $stmt->fetch();

        if (!$cupom) {
            return ['success' => false, 'message' => 'Cupom não encontrado'];
        }

        // Verificação de Validade
        if (!empty($cupom['validade']) && strtotime($cupom['validade']) < strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => 'Este cupom expirou'];
        }

        // Verificação de Limite de Uso
        if (isset($cupom['limite_usos']) && $cupom['usos_atuais'] >= $cupom['limite_usos']) {
            return ['success' => false, 'message' => 'Lote de cupons esgotado'];
        }

        // Calcular desconto - Dashboard usa 'tipo' e 'valor'
        $desconto = 0;
        $tipo = $cupom['tipo'] ?? 'fixo';
        $v_desc = (float)($cupom['valor'] ?? 0);

        if ($tipo === 'fixo' || $tipo === 'valor') {
            $desconto = $v_desc;
        } else {
            $desconto = ($valorPedido * ($v_desc / 100));
        }

        $valorFinal = max(0.01, $valorPedido - $desconto);

        return [
            'success' => true,
            'desconto' => (float)$desconto,
            'valor_final' => (float)$valorFinal,
            'cupom_id' => $cupom['id'],
            'tipo' => $tipo,
            'valor' => $v_desc
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erro ao processar cupom: ' . $e->getMessage()];
    }
}
