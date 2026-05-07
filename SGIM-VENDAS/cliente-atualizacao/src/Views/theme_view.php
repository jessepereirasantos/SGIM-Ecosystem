<div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">White-Label & Aparência</h2>
        <p class="text-sm text-gray-500 mt-1">Personalize as cores, logomarca e o modo ativo do sistema para refletir a marca do Ministério.</p>
    </div>
    <form method="POST" class="mt-4 md:mt-0">
        <input type="hidden" name="acao" value="restaurar">
        <button type="submit" onclick="return confirm('Isso removerá todas as cores customizadas e trará as cores originais da fábrica. Continuar?')" class="px-4 py-2 bg-red-500/10 text-red-500 border border-red-500/20 rounded-twelve font-bold text-sm hover:bg-red-500 hover:text-white transition-colors">
            Restaurar Padrão SGIM
        </button>
    </form>
</div>

<?php if ($mensagem): ?>
    <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
        <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
        <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- Logo Section -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-brand">branding_watermark</span>
                <h3 class="text-lg font-bold text-white">Identidade Principal</h3>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Logomarca Atual</label>
                    <?php if(!empty($cfg['logo_url'])): ?>
                        <div class="p-4 bg-darkbg border border-darkborder rounded-lg mb-2 flex justify-center">
                            <img src="<?= htmlspecialchars($cfg['logo_url']) ?>" alt="Logo Atual" class="h-20 object-contain">
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-darkbg border border-darkborder rounded-lg mb-2 flex flex-col items-center justify-center text-gray-500 gap-2">
                            <span class="material-symbols-outlined text-4xl">church</span>
                            <span class="text-xs">Logo Padrão Ativa</span>
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" name="logo_upload" accept="image/*" class="w-full text-sm text-gray-400
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-twelve file:border-0
                        file:text-sm file:font-semibold
                        file:bg-brand/10 file:text-brand hover:file:bg-brand/20 transition-colors" />
                    <p class="text-[10px] text-gray-500 mt-1">Recomendado: PNG Transparente.</p>
                </div>

                <div class="pt-4 border-t border-darkborder">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Comportamento Geral</label>
                    <select name="modo_padrao" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                        <option value="dark" <?= ($cfg['modo_padrao'] == 'dark') ? 'selected' : '' ?>>O sistema nasce sempre no Modo Escuro</option>
                        <option value="light" <?= ($cfg['modo_padrao'] == 'light') ? 'selected' : '' ?>>O sistema nasce sempre no Modo Claro</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Brand Colors -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-brand">palette</span>
                <h3 class="text-lg font-bold text-white">Cor de Destaque (Brand)</h3>
            </div>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">Cor Principal</p>
                        <p class="text-xs text-gray-500">Usada em botões e títulos principais.</p>
                    </div>
                    <input type="color" name="cor_brand" value="<?= htmlspecialchars($cfg['cor_brand']) ?>" class="size-10 rounded-full border-0 outline-none cursor-pointer bg-transparent">
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">Tom Escuro (Hover)</p>
                        <p class="text-xs text-gray-500">Quando passa o mouse no botão.</p>
                    </div>
                    <input type="color" name="cor_brand_dark" value="<?= htmlspecialchars($cfg['cor_brand_dark']) ?>" class="size-10 rounded-full border-0 outline-none cursor-pointer bg-transparent">
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">Tom Claro (Amuletos)</p>
                        <p class="text-xs text-gray-500">Para realces sutis no fundo claro.</p>
                    </div>
                    <input type="color" name="cor_brand_light" value="<?= htmlspecialchars($cfg['cor_brand_light']) ?>" class="size-10 rounded-full border-0 outline-none cursor-pointer bg-transparent">
                </div>
            </div>
        </div>

        <!-- Dark Theme Colors -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-gray-400">dark_mode</span>
                <h3 class="text-lg font-bold text-white">Configurações do Modo Escuro</h3>
            </div>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">Fundo do Sistema</p>
                        <p class="text-xs text-gray-500">Padrão: Preto Escuro (#050505)</p>
                    </div>
                    <input type="color" name="darkbg" value="<?= htmlspecialchars($cfg['darkbg']) ?>" class="size-10 rounded border border-darkborder cursor-pointer bg-transparent">
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">Apoio (Cards/Painéis)</p>
                        <p class="text-xs text-gray-500">Padrão: Preto Claro (#121212)</p>
                    </div>
                    <input type="color" name="darkcard" value="<?= htmlspecialchars($cfg['darkcard']) ?>" class="size-10 rounded border border-darkborder cursor-pointer bg-transparent">
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">Divisórias (Bordas)</p>
                        <p class="text-xs text-gray-500">Padrão: Cinza Escuro (#1E1E1E)</p>
                    </div>
                    <input type="color" name="darkborder" value="<?= htmlspecialchars($cfg['darkborder']) ?>" class="size-10 rounded border border-darkborder cursor-pointer bg-transparent">
                </div>
            </div>
        </div>

        <!-- Light Theme Colors -->
        <div class="bg-white p-6 rounded-twelve border border-gray-200 shadow-sm relative overflow-hidden text-gray-800">
            <div class="flex items-center gap-3 mb-4">
                <span class="material-symbols-outlined text-yellow-500">light_mode</span>
                <h3 class="text-lg font-bold text-gray-900">Configurações do Modo Claro</h3>
            </div>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900">Fundo do Sistema</p>
                        <p class="text-xs text-gray-500">Padrão: Cinza Gelo (#F3F4F6)</p>
                    </div>
                    <input type="color" name="lightbg" value="<?= htmlspecialchars($cfg['lightbg']) ?>" class="size-10 rounded border border-gray-300 cursor-pointer bg-transparent">
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900">Apoio (Cards/Painéis)</p>
                        <p class="text-xs text-gray-500">Padrão: Branco Puro (#FFFFFF)</p>
                    </div>
                    <input type="color" name="lightcard" value="<?= htmlspecialchars($cfg['lightcard']) ?>" class="size-10 rounded border border-gray-300 cursor-pointer bg-transparent">
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900">Divisórias (Bordas)</p>
                        <p class="text-xs text-gray-500">Padrão: Cinza Claro (#E5E7EB)</p>
                    </div>
                    <input type="color" name="lightborder" value="<?= htmlspecialchars($cfg['lightborder']) ?>" class="size-10 rounded border border-gray-300 cursor-pointer bg-transparent">
                </div>
            </div>
        </div>
        
    </div>

    <div class="pt-6 border-t border-darkborder flex justify-end">
        <button type="submit" class="px-12 py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all text-sm">
            Salvar Identidade Visual (Via MVC Engine)
        </button>
    </div>
</form>
