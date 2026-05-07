<?php
/**
 * SGIM MASTER - PUBLICADOR OTA v8.0 (DESIGN FINAL PADRONIZADO)
 */
$current_page = 'publish';
require_once 'templates/header.php';

$ultima_versao = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'system_version'")->fetchColumn() ?: '1.1.0';
?>

<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Publicador OTA</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Distribuição de atualizações controlada pelo administrador.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 glass-card p-12 rounded-2xl flex flex-col justify-between min-h-[450px]">
        <div>
            <div class="size-16 bg-primary/10 rounded-2xl flex items-center justify-center text-primary mb-8">
                <span class="material-symbols-outlined text-4xl">rocket_launch</span>
            </div>
            <h3 class="text-headline-md font-black text-white mb-4 italic tracking-tighter">Deploy Manual</h3>
            <p class="text-on-surface-variant text-lg leading-relaxed mb-8">Configure a nova versão abaixo para iniciar o processo de sincronização e deploy atômico.</p>
            
            <div class="space-y-4 mb-10">
                <div class="flex items-center gap-3 text-sm text-zinc-400">
                    <span class="material-symbols-outlined text-emerald-500 text-sm">check_circle</span> Sincronização MASTER/CLIENTE
                </div>
                <div class="flex items-center gap-3 text-sm text-zinc-400">
                    <span class="material-symbols-outlined text-emerald-500 text-sm">check_circle</span> Geração de pacote OTA
                </div>
            </div>
        </div>

        <button onclick="document.getElementById('modalConfig').classList.remove('hidden')" class="w-full py-5 bg-primary text-on-primary font-black rounded-2xl shadow-2xl hover:scale-105 transition-all uppercase tracking-widest text-sm">
            INICIAR PUBLICAÇÃO AGORA
        </button>
    </div>

    <div class="glass-card p-10 rounded-2xl bg-gradient-to-br from-primary/10 to-transparent flex flex-col justify-center text-center">
        <h4 class="text-title-sm font-bold text-white mb-4">Versão Atual</h4>
        <p class="text-[48px] font-black text-primary italic leading-none mb-2">v<?= $ultima_versao ?></p>
        <p class="text-[10px] uppercase tracking-widest text-zinc-500 font-bold">Produção Estável</p>
    </div>
</div>

<!-- 1. MODAL DE CONFIGURAÇÃO (PADRÃO IMAGEM) -->
<div id="modalConfig" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
    <div class="bg-[#121212] border border-white/5 rounded-[32px] w-full max-w-md p-10 shadow-2xl relative">
        <div class="flex justify-between items-center mb-10">
            <h3 class="text-2xl font-bold text-white">Setup <span class="text-primary italic">OTA</span></h3>
            <button onclick="document.getElementById('modalConfig').classList.add('hidden')" class="text-zinc-500 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="publishForm" class="space-y-8">
            <div class="space-y-3">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Número da Versão</label>
                <input type="text" name="version" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20 transition-all" value="<?= $ultima_versao ?>">
            </div>
            <div class="space-y-3">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Descrição</label>
                <textarea name="description" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20 h-24 resize-none" placeholder="O que mudou?"></textarea>
            </div>
            <button type="submit" class="w-full py-5 bg-primary text-on-primary font-black rounded-xl shadow-xl hover:opacity-90 transition-all uppercase tracking-widest text-sm">
                PUBLICAR AGORA
            </button>
        </form>
    </div>
</div>

<!-- 2. MODAL DE STATUS (RESTAURADO E ESTÁVEL) -->
<div id="modalUpdate" class="fixed inset-0 z-[110] hidden flex items-center justify-center bg-black/90 backdrop-blur-md p-4">
    <div class="bg-[#121212] border border-white/5 rounded-[32px] w-full max-w-md p-12 text-center shadow-2xl">
        <div id="updateIcon" class="size-20 bg-primary/10 rounded-full flex items-center justify-center text-primary mx-auto mb-6 animate-spin">
            <span class="material-symbols-outlined text-4xl">sync</span>
        </div>
        <h3 id="updateTitle" class="text-2xl font-bold text-white mb-2 italic tracking-tight">Publicando Atualização...</h3>
        <p id="updateSub" class="text-zinc-500 text-sm">Aguarde, o sistema está gerando o pacote OTA e notificando os clientes.</p>
    </div>
</div>

<script>
document.getElementById('publishForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    // Mapeamento para o backend original
    formData.append('versao', formData.get('version'));
    formData.append('novidades', formData.get('description'));

    document.getElementById('modalConfig').classList.add('hidden');
    document.getElementById('modalUpdate').classList.remove('hidden');
    
    fetch('api/publish_update.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            document.getElementById('updateIcon').classList.remove('animate-spin');
            document.getElementById('updateIcon').classList.add('bg-emerald-500/10', 'text-emerald-500');
            document.getElementById('updateIcon').innerHTML = '<span class="material-symbols-outlined text-4xl">check_circle</span>';
            document.getElementById('updateTitle').innerText = 'Sucesso!';
            document.getElementById('updateSub').innerText = data.message;
            
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            alert('Erro: ' + data.message);
            location.reload();
        }
    })
    .catch(err => {
        alert('Erro de conexão: ' + err.message);
        location.reload();
    });
});
</script>

<?php include 'templates/footer.php'; ?>