<!-- Header Section -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h2 class="text-3xl font-black tracking-tight text-white">Contatos & E-mail</h2>
        <p class="text-slate-500 dark:text-slate-400 mt-1">Comunicação direta e disparos em massa.</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="document.getElementById('modalMassa').classList.remove('hidden')" class="flex items-center gap-2 px-4 py-2 bg-brand text-black rounded-lg text-sm font-bold hover:bg-brand-dark transition-colors">
            <span class="material-symbols-outlined text-[18px]">bolt</span>
            E-mail em Massa
        </button>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-8">
    <!-- Lista de Clientes -->
    <div class="lg:col-span-2 bg-[#121212] border border-white/5 rounded-lg overflow-hidden">
        <div class="p-6 border-b border-white/5">
            <h3 class="font-bold text-white">Selecione um Destinatário</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Cliente</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php
                    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC");
                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-white"><?= htmlspecialchars($row['nome']) ?></span>
                                <span class="text-xs text-gray-400"><?= htmlspecialchars($row['email']) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[10px] bg-green-500/10 text-green-500 px-2 py-0.5 rounded font-bold">ATIVO</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button onclick="prepararEmail('<?= $row['email'] ?>', '<?= $row['nome'] ?>')" class="text-brand hover:text-brand-dark transition-all">
                                <span class="material-symbols-outlined">send</span>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulário de Envio Individual -->
    <div class="bg-[#121212] border border-white/5 rounded-lg p-6 h-fit sticky top-28">
        <h3 class="text-lg font-bold text-white mb-6">Novo E-mail</h3>
        <form method="POST" action="api/send_email.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Para:</label>
                <input id="emailPara" name="para" required class="w-full bg-darkbg border border-white/10 rounded-lg p-3 text-white outline-none focus:ring-1 focus:ring-brand" placeholder="email@exemplo.com">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Assunto:</label>
                <input name="assunto" required class="w-full bg-darkbg border border-white/10 rounded-lg p-3 text-white outline-none focus:ring-1 focus:ring-brand" placeholder="Assunto da mensagem">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Mensagem:</label>
                <textarea name="mensagem" required rows="6" class="w-full bg-darkbg border border-white/10 rounded-lg p-3 text-white outline-none focus:ring-1 focus:ring-brand" placeholder="Escreva sua mensagem aqui..."></textarea>
            </div>
            <button type="submit" class="w-full py-4 bg-brand text-black font-bold rounded-lg hover:bg-brand-dark transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">email</span>
                Enviar Mensagem
            </button>
        </form>
    </div>
</div>

<!-- Modal Massa -->
<div id="modalMassa" class="hidden fixed inset-0 bg-black/90 flex items-center justify-center z-[110] p-4">
    <div class="bg-surface-dark border border-white/10 p-8 rounded-xl max-w-2xl w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-white">Disparo em Massa</h1>
            <button onclick="document.getElementById('modalMassa').classList.add('hidden')" class="text-gray-500 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <p class="text-yellow-500 bg-yellow-500/10 border border-yellow-500/20 p-4 rounded-lg text-sm mb-6">
            <b>CUIDADO:</b> Esta ação enviará o e-mail para TODOS os clientes cadastrados (<?= $stmt->rowCount() ?> destinatários). 
        </p>
        <form action="api/send_mass_email.php" method="POST" class="space-y-4">
             <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Assunto Geral:</label>
                <input name="assunto" required class="w-full bg-darkbg border border-white/10 rounded-lg p-3 text-white outline-none focus:ring-1 focus:ring-brand">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Conteúdo do Comunicado:</label>
                <textarea name="mensagem" required rows="8" class="w-full bg-darkbg border border-white/10 rounded-lg p-3 text-white outline-none focus:ring-1 focus:ring-brand"></textarea>
            </div>
            <button type="submit" class="w-full py-4 bg-red-600 text-white font-bold rounded-lg hover:bg-red-500 transition-all">
                INICIAR DISPARO EM MASSA
            </button>
        </form>
    </div>
</div>

<script>
function prepararEmail(email, nome) {
    document.getElementById('emailPara').value = email;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
