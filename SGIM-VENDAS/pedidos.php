<?php
/**
 * SGIM MASTER - HISTÓRICO DE VENDAS v7.6 (DESIGN PREMIUM)
 */
$current_page = 'pedidos';
require_once 'templates/header.php';

try {
    $stmt = $pdo->query("SELECT v.*, c.nome as cliente_nome_db FROM vendas v LEFT JOIN clientes c ON v.cliente_id = c.id ORDER BY v.id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        $stmt = $pdo->query("SELECT p.*, c.nome as cliente_nome_db FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id ORDER BY p.id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $rows = []; }
?>

<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Histórico de Vendas</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Rastreamento completo de transações e pagamentos.</p>
    </div>
</div>

<div class="glass-card rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container-low/50">
                    <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">ID / Pedido</th>
                    <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">Cliente</th>
                    <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">Valor</th>
                    <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase text-right">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10">
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-surface-variant/5 transition-colors">
                    <td class="px-8 py-6 font-mono text-primary font-bold">#<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td class="px-8 py-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-surface-container-highest flex items-center justify-center font-bold text-tertiary">
                                <?= substr($r['cliente_nome_db'] ?? 'CL', 0, 2) ?>
                            </div>
                            <span class="text-sm font-bold text-white"><?= htmlspecialchars($r['cliente_nome_db'] ?? 'Cliente Avulso') ?></span>
                        </div>
                    </td>
                    <td class="px-8 py-6 text-sm font-black text-amber-500 italic">R$ <?= number_format($r['valor'], 2, ',', '.') ?></td>
                    <td class="px-8 py-6 text-right">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase <?= in_array(strtoupper($r['status'] ?? ''), ['APPROVED', 'APROVADO', 'PAGO']) ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                            <?= in_array(strtoupper($r['status'] ?? ''), ['APPROVED', 'APROVADO', 'PAGO']) ? 'Pago' : 'Pendente' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
