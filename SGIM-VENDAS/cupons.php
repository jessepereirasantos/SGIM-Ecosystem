<?php
/**
 * SGIM MASTER - CUPONS (Obsidian Amber v8.0 - DESIGN FINAL)
 */
$current_page = 'cupons';
require_once 'templates/header.php';

try {
    $stmt = $pdo->query("SELECT * FROM cupons ORDER BY validade DESC");
    $cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $error_sql = $e->getMessage(); }
?>

<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Cupons & Descontos</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Gestão de ofertas e benefícios exclusivos.</p>
    </div>
    <button onclick="document.getElementById('modalCupom').classList.remove('hidden')" class="px-8 py-4 rounded-2xl bg-primary text-on-primary text-sm font-black hover:scale-105 transition-all shadow-xl shadow-amber-500/10">
        NOVO CUPOM
    </button>
</div>

<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left">
        <thead>
            <tr class="bg-surface-container-low/50">
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Código</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Desconto</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Validade</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Uso</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase text-right">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/10">
            <?php foreach ($cupons as $row): ?>
            <tr id="row-<?= $row['id'] ?>" class="hover:bg-white/[0.01] transition-all group">
                <td class="px-8 py-6">
                    <span class="font-mono font-bold text-primary px-3 py-1 bg-primary/10 rounded-lg border border-primary/20"><?= htmlspecialchars($row['codigo']) ?></span>
                </td>
                <td class="px-8 py-6">
                    <span class="bg-emerald-500/10 text-emerald-500 text-xs font-black px-2 py-1 rounded">
                        <?= $row['tipo'] == 'porcentagem' ? $row['valor'].'%' : 'R$ '.$row['valor'] ?> OFF
                    </span>
                </td>
                <td class="px-8 py-6 text-sm text-zinc-500"><?= $row['validade'] ? date('d/m/Y', strtotime($row['validade'])) : 'Permanente' ?></td>
                <td class="px-8 py-6 text-sm font-bold text-white"><?= $row['usos_realizados'] ?? 0 ?> / <?= $row['limite_usos'] ?? '∞' ?></td>
                <td class="px-8 py-6 text-right">
                    <button onclick="deleteRecord('cupons', <?= $row['id'] ?>)" class="size-10 bg-zinc-900 rounded-xl inline-flex items-center justify-center text-zinc-600 hover:text-red-500 transition-all border border-zinc-800">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Novo Cupom (DESIGN FINAL - INPUTS BRANCOS) -->
<div id="modalCupom" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
    <div class="bg-[#121212] border border-white/5 rounded-[32px] w-full max-w-md shadow-2xl p-10 relative">
        <div class="flex justify-between items-center mb-10">
            <h3 class="text-2xl font-bold text-white">Criar <span class="text-primary italic">Cupom</span></h3>
            <button onclick="document.getElementById('modalCupom').classList.add('hidden')" class="text-zinc-500 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="formCupom" class="space-y-8">
            <input type="hidden" name="table" value="cupons">
            
            <div class="space-y-3">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Código</label>
                <input type="text" name="data[codigo]" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20 transition-all placeholder:text-zinc-400" placeholder="EX: SGIM2024">
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-3">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Valor</label>
                    <input type="number" step="0.01" name="data[valor]" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20">
                </div>
                <div class="space-y-3">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Tipo</label>
                    <div class="relative">
                        <select name="data[tipo]" class="w-full bg-[#1c1c1c] border border-white/10 rounded-xl px-6 py-4 text-white appearance-none focus:ring-4 focus:ring-primary/20">
                            <option value="porcentagem">Porcentagem (%)</option>
                            <option value="fixo">Fixo (R$)</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">expand_more</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full py-5 bg-primary text-on-primary font-black rounded-xl shadow-xl hover:opacity-90 transition-all uppercase tracking-widest text-sm mt-4">
                SALVAR CUPOM
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('formCupom').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('api/save_record.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => { if(data.success) location.reload(); else alert(data.message); });
});
function deleteRecord(table, id) {
    if(!confirm('Excluir?')) return;
    const fd = new FormData(); fd.append('table', table); fd.append('id', id);
    fetch('api/delete_record.php', { method: 'POST', body: fd })
    .then(r => r.json()).then(data => { if(data.success) document.getElementById('row-'+id).remove(); });
}
</script>

<?php include 'templates/footer.php'; ?>
