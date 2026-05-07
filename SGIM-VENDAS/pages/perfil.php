<header class="mb-10">
    <h2 class="text-4xl font-black text-white tracking-tight">Meu Perfil</h2>
    <p class="mt-2 text-slate-500">Gerencie suas informações e credenciais de acesso.</p>
</header>

<section class="mb-12 p-8 rounded-xl bg-surface-dark border border-white/5 flex flex-col md:flex-row items-center gap-8 shadow-sm">
    <div class="size-32 rounded-full overflow-hidden border-4 border-brand/20 bg-slate-800 flex items-center justify-center">
        <span class="material-symbols-outlined text-6xl text-brand">shield_person</span>
    </div>
    <div class="flex-1 text-center md:text-left">
        <h3 class="text-2xl font-bold text-white">Administrador Master</h3>
        <p class="text-brand font-medium">admin@sgim.com.br</p>
        <div class="mt-3 flex flex-wrap justify-center md:justify-start gap-4">
            <div class="flex items-center gap-2 text-xs text-slate-400 bg-white/5 px-3 py-1.5 rounded-full">
                <span class="material-symbols-outlined text-[16px]">verified_user</span>
                Status: Super Admin
            </div>
        </div>
    </div>
</section>

<div class="grid lg:grid-cols-2 gap-8">
    <div class="bg-surface-dark rounded-xl border border-white/5 p-8 shadow-sm">
        <h3 class="text-xl font-bold mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand">lock_reset</span>
            Alterar Senha
        </h3>
        <form class="space-y-6">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-300">Nova Senha</label>
                <input class="w-full px-4 py-3 rounded-lg border border-white/10 bg-black/40 focus:border-brand focus:ring-1 focus:ring-brand outline-none text-white transition-all" type="password" placeholder="Digite a nova senha">
            </div>
             <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-300">Confirmar Senha</label>
                <input class="w-full px-4 py-3 rounded-lg border border-white/10 bg-black/40 focus:border-brand focus:ring-1 focus:ring-brand outline-none text-white transition-all" type="password" placeholder="Repita a nova senha">
            </div>
            <button type="button" class="px-8 py-3 rounded-lg bg-brand text-black font-bold text-sm shadow-lg hover:brightness-110 transition-all">
                Atualizar Senha
            </button>
        </form>
    </div>

    <div class="bg-surface-dark rounded-xl border border-white/5 p-8 shadow-sm">
        <h3 class="text-xl font-bold mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand">settings_suggest</span>
            Configurações Rápidas
        </h3>
        <div class="space-y-4">
             <div class="flex items-center justify-between p-4 border border-white/5 rounded-lg">
                 <div>
                     <p class="text-sm font-bold text-white">Notificações por E-mail</p>
                     <p class="text-xs text-gray-400">Receber alerta a cada nova venda</p>
                 </div>
                 <div class="size-6 bg-brand rounded flex items-center justify-center"><span class="material-symbols-outlined text-black text-sm">check</span></div>
             </div>
             <div class="flex items-center justify-between p-4 border border-white/5 rounded-lg">
                 <div>
                     <p class="text-sm font-bold text-white">Modo de Manutenção</p>
                     <p class="text-xs text-gray-400">Suspender novas compras</p>
                 </div>
                 <div class="size-6 bg-white/10 rounded flex items-center justify-center"></div>
             </div>
        </div>
    </div>
</div>
