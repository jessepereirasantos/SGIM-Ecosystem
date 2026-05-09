<?php
require_once 'config/database.php';
$current_page = 'pedidos';

// Busca de Pedidos
$stmt = $pdo->query("SELECT * FROM pedidos ORDER BY id DESC");
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Top Navigation -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-2 text-on-surface-variant font-bold text-xs uppercase tracking-widest">
                <span class="material-symbols-outlined text-primary">receipt_long</span>
                Transaction History
            </div>
        </header>

        <div class="p-10 max-w-[1600px] mx-auto">
            <div class="flex justify-between items-end mb-10">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Histórico de <span class="text-primary">Vendas</span></h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Rastreamento completo de transações, faturamento e status de pagamento.</p>
                </div>
                <button class="px-5 py-2.5 rounded-lg border border-outline-variant/20 text-on-surface font-semibold hover:bg-surface-variant/10 transition-all flex items-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-sm">download</span>
                    Exportar CSV
                </button>
            </div>

            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-8 border-b border-outline-variant/10">
                    <h3 class="text-title-sm font-bold text-on-surface italic opacity-80">Registro Geral de Receita</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low/50 text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                                <th class="px-8 py-4 font-bold">Pedido #</th>
                                <th class="px-8 py-4 font-bold">Cliente / Produto</th>
                                <th class="px-8 py-4 font-bold">Valor</th>
                                <th class="px-8 py-4 font-bold">Status</th>
                                <th class="px-8 py-4 font-bold text-right">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php if (count($pedidos) > 0): ?>
                                <?php foreach ($pedidos as $p): ?>
                                <tr class="hover:bg-surface-variant/5 transition-colors">
                                    <td class="px-8 py-5 text-sm font-bold text-on-surface font-mono"><?= $p['id'] ?></td>
                                    <td class="px-8 py-5">
                                        <p class="text-body-md font-bold text-white"><?= htmlspecialchars($p['cliente_nome'] ?? 'Cliente SGIM') ?></p>
                                        <p class="text-[10px] text-on-surface-variant uppercase tracking-widest opacity-60">Plano Profissional</p>
                                    </td>
                                    <td class="px-8 py-5 text-sm font-black text-on-surface">R$ <?= number_format($p['valor'] ?? 0, 2, ',', '.') ?></td>
                                    <td class="px-8 py-5">
                                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest <?= $p['status'] == 'pago' ? 'bg-secondary-container/20 text-secondary' : 'bg-error-container/20 text-error' ?>">
                                            <?= htmlspecialchars($p['status'] ?? 'pendente') ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-right text-xs text-on-surface-variant font-mono"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-20 text-center opacity-40 italic text-sm">Sem registros de faturamento no período.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
