<!-- Header Section -->
<div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
    <div class="space-y-2">
        <h2 class="text-4xl font-extrabold tracking-tight text-white">Cupons</h2>
        <p class="text-slate-500 dark:text-slate-400 text-lg">Gerencie seus cupons de desconto e benefícios exclusivos.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openAdminModal('modalCupom')" class="flex items-center gap-2 bg-brand text-black px-6 py-3 rounded-xl font-bold hover:brightness-110 transition-all shadow-lg shadow-brand/20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
            Novo Cupom
        </button>
    </div>
</div>

<!-- Coupons Table -->
<div class="bg-surface-dark rounded-xl border border-border-dark overflow-hidden">
    <div class="p-6 border-b border-brand/10 flex items-center justify-between">
        <h3 class="text-xl font-bold text-white">Lista de Benefícios</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-white/5">
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-400">Código</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-400">Desconto</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-400">Expira em</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-400">Uso</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-400 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php
                $stmt = $pdo->query("SELECT * FROM cupons ORDER BY validade DESC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <tr id="row-<?= $row['id'] ?>" class="hover:bg-brand/5 transition-colors">
                    <td class="px-6 py-5">
                        <span class="font-mono font-bold text-brand px-3 py-1 bg-brand/10 rounded border border-brand/20"><?= htmlspecialchars($row['codigo']) ?></span>
                    </td>
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-2">
                            <span class="bg-brand/20 text-brand text-xs font-black px-2 py-1 rounded">
                                <?= $row['tipo'] == 'porcentagem' ? $row['valor'].'%' : 'R$ '.$row['valor'] ?> OFF
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-5 text-sm text-slate-400"><?= $row['validade'] ? date('d/m/Y', strtotime($row['validade'])) : 'Permanente' ?></td>
                    <td class="px-6 py-5">
                        <span class="text-sm font-bold text-white"><?= $row['usos_atuais'] ?> / <?= $row['limite_usos'] ?? '∞' ?></span>
                    </td>
                    <td class="px-6 py-5 text-right flex justify-end gap-2">
                        <button onclick="deleteRecord('cupons', <?= $row['id'] ?>)" class="text-slate-500 hover:text-red-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Novo Cupom -->
<div id="modalCupom" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-darkcard border border-white/10 rounded-xl w-full max-w-md shadow-2xl">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white">Criar Novo Cupom</h3>
            <button onclick="closeAdminModal('modalCupom')" class="text-gray-500 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="formCupom" class="p-6 space-y-4">
             <input type="hidden" name="table" value="cupons">
             <div class="space-y-1">
                 <label class="text-xs font-bold text-gray-500 uppercase">Código do Cupom</label>
                 <input type="text" name="data[codigo]" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand" placeholder="EX: SGIM10">
             </div>
             <div class="grid grid-cols-2 gap-4">
                 <div class="space-y-1">
                     <label class="text-xs font-bold text-gray-500 uppercase">Valor</label>
                     <input type="number" step="0.01" name="data[valor]" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand">
                 </div>
                 <div class="space-y-1">
                     <label class="text-xs font-bold text-gray-500 uppercase">Tipo</label>
                     <select name="data[tipo]" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand">
                         <option value="porcentagem">Porcentagem (%)</option>
                         <option value="fixo">Valor Fixo (R$)</option>
                     </select>
                 </div>
             </div>
             <div class="grid grid-cols-2 gap-4">
                 <div class="space-y-1">
                     <label class="text-xs font-bold text-gray-500 uppercase">Limite de Usos</label>
                     <input type="number" name="data[limite_usos]" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand" value="100">
                 </div>
                 <div class="space-y-1">
                     <label class="text-xs font-bold text-gray-500 uppercase">Validade</label>
                     <input type="date" name="data[validade]" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand">
                 </div>
             </div>
             <button type="submit" class="w-full py-3 bg-brand text-black font-black rounded-lg mt-4 hover:bg-brand-dark transition-all">SALVAR CUPOM</button>
        </form>
    </div>
</div>

<script>

document.getElementById('formCupom').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('api/save_record.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) location.reload();
        else alert('Erro: ' + data.message);
    });
});

function deleteRecord(table, id) {
    if(!confirm('Deseja realmente excluir este registro?')) return;
    const formData = new FormData();
    formData.append('table', table);
    formData.append('id', id);
    fetch('api/delete_record.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) document.getElementById('row-'+id).remove();
        else alert('Erro: ' + data.message);
    });
}
</script>
