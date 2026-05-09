<!-- Header Section -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h2 class="text-3xl font-black tracking-tight text-white">Pedidos</h2>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Gerencie e acompanhe todos os pedidos da plataforma.</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="openAdminModal('modalPedido')" class="flex items-center gap-2 px-4 py-2 bg-brand text-black rounded-lg text-sm font-bold hover:bg-brand-dark transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            Novo Pedido
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-[#121212] border border-white/10 rounded-lg text-sm font-semibold hover:bg-white/5 transition-colors text-slate-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Exportar CSV
        </button>
    </div>
</div>

<!-- Filters and Search -->
<div class="flex flex-col lg:flex-row gap-4 mb-8">
    <div class="relative flex-1">
        <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        <input class="w-full pl-12 pr-4 py-3 bg-[#121212] border border-white/10 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-slate-500 text-slate-200" placeholder="Buscar por ID, cliente ou status..." type="text"/>
    </div>
    <div class="flex items-center gap-2">
        <button class="whitespace-nowrap px-4 py-2.5 bg-brand text-black rounded-lg text-xs font-bold">Todos</button>
        <button class="whitespace-nowrap px-4 py-2.5 bg-[#121212] border border-white/10 rounded-lg text-xs font-bold text-slate-300">Aprovados</button>
    </div>
</div>

<!-- Table Section -->
<div class="bg-[#121212] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/5 border-b border-white/5">
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">ID</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Cliente</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Data</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Valor</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php
                // Fallback inteligente: Tenta 'vendas', se vazia tenta 'pedidos'
                try {
                    $stmt = $pdo->query("SELECT v.*, c.nome as cliente_nome_db, c.email as cliente_email_db 
                                        FROM vendas v 
                                        LEFT JOIN clientes c ON v.cliente_id = c.id 
                                        ORDER BY v.id DESC");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) { $rows = []; }
                
                if (empty($rows)) {
                    try {
                        $stmt = $pdo->query("SELECT p.*, c.nome as cliente_nome_db, c.email as cliente_email_db 
                                            FROM pedidos p 
                                            LEFT JOIN clientes c ON p.cliente_id = c.id 
                                            ORDER BY p.id DESC");
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) { $rows = []; }
                }

                foreach ($rows as $row):
                    // Normalização de colunas comum para ambas as tabelas
                    $data_exibicao = $row['data_venda'] ?? ($row['created_at'] ?? 'now');
                    // Tenta encontrar o nome em várias colunas possíveis, priorizando o join da tabela clientes
                    $cliente_nome_exib = $row['cliente_nome_db'] ?? ($row['cliente_nome'] ?? ($row['nome'] ?? ('Cliente #'.$row['cliente_id'])));
                ?>
                <tr id="row-<?= $row['id'] ?>" class="hover:bg-white/[0.02] transition-colors border-b border-white/5">
                    <td class="px-6 py-5 text-sm font-bold text-white">#<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium"><?= htmlspecialchars($cliente_nome_exib) ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-5 text-sm text-slate-400"><?= date('d/m/Y', strtotime($data_exibicao)) ?></td>
                    <td class="px-6 py-5 text-sm font-semibold text-white">R$ <?= number_format($row['valor'], 2, ',', '.') ?></td>
                    <td class="px-6 py-5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= in_array(strtoupper($row['status'] ?? ''), ['APPROVED', 'APROVADO', 'PAGO']) ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                            <?= in_array(strtoupper($row['status'] ?? ''), ['APPROVED', 'APROVADO', 'PAGO']) ? 'Aprovado' : 'Pendente' ?>
                        </span>
                    </td>
                    <td class="px-6 py-5 text-right flex justify-end gap-2">
                        <button onclick="deleteRecord('vendas', <?= $row['id'] ?>)" class="text-slate-500 hover:text-red-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Novo Pedido -->
<div id="modalPedido" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-darkcard border border-white/10 rounded-xl w-full max-w-md shadow-2xl">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white">Lançar Novo Pedido</h3>
            <button onclick="closeAdminModal('modalPedido')" class="text-gray-500 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form id="formPedido" class="p-6 space-y-4">
             <input type="hidden" name="table" value="vendas">
             <div class="space-y-1">
                 <label class="text-xs font-bold text-gray-500 uppercase">Nome do Cliente</label>
                 <input type="text" name="data[cliente_nome]" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand">
             </div>
             <div class="grid grid-cols-2 gap-4">
                 <div class="space-y-1">
                     <label class="text-xs font-bold text-gray-500 uppercase">Valor (BRL)</label>
                     <input type="number" step="0.01" name="data[valor]" required class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand">
                 </div>
                 <div class="space-y-1">
                     <label class="text-xs font-bold text-gray-500 uppercase">Status</label>
                     <select name="data[status]" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white outline-none focus:border-brand">
                         <option value="approved">Aprovado</option>
                         <option value="pending">Pendente</option>
                     </select>
                 </div>
             </div>
             <button type="submit" class="w-full py-3 bg-brand text-black font-black rounded-lg mt-4 hover:bg-brand-dark transition-all">SALVAR PEDIDO</button>
        </form>
    </div>
</div>

<script>

document.getElementById('formPedido').addEventListener('submit', function(e) {
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
