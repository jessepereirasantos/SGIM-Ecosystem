<?php 
    $log_view = "[" . date('Y-m-d H:i:s') . "] [CLIENT VIEW] Carregando novidades | Registros: " . count($novidades) . "\n";
    file_put_contents(__DIR__ . '/../../client_ota.log', $log_view, FILE_APPEND);
?>
<div class="mb-4">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Novidades e <span class="text-brand">Atualizações</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Acompanhe todas as melhorias e novidades do sistema</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mt-12" x-data="{ activeVersion: 0 }">
    <!-- Sidebar Historique -->
    <div class="lg:col-span-1 space-y-3">
        <?php foreach ($novidades as $index => $n): 
            $is_current = ($index === 0); // O primeiro da lista é o mais recente
        ?>
            <div @click="activeVersion = <?= $index ?>" 
                 :class="activeVersion === <?= $index ?> ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-darkborder bg-darkcard/50'"
                 class="p-4 rounded-xl border cursor-pointer transition-all hover:border-emerald-500/30 group relative overflow-hidden">
                
                <?php if ($is_current): ?>
                    <div class="flex items-center justify-between mb-2">
                        <span class="bg-emerald-500/20 text-emerald-400 text-[9px] font-black px-2 py-0.5 rounded uppercase tracking-tighter border border-emerald-500/20">Versão Atual</span>
                        <span class="material-symbols-outlined text-emerald-500 text-xs animate-pulse">check_circle</span>
                    </div>
                <?php endif; ?>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-white group-hover:text-emerald-400 transition-colors">v<?= htmlspecialchars($n['titulo']) ?></span>
                    <span class="text-[10px] text-gray-600 font-mono"><?= date('d/m/Y', strtotime($n['data_lancamento'])) ?></span>
                </div>

                <div x-show="activeVersion === <?= $index ?>" class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Conteúdo em Detalhes -->
    <div class="lg:col-span-3">
        <?php foreach ($novidades as $index => $n): 
            // Tenta decodificar o changelog estruturado
            $data = json_decode($n['descricao'], true);
            $has_structured = (json_last_error() === JSON_ERROR_NONE && is_array($data));
            $changelog = $has_structured ? $data : ['novidades' => [$n['descricao']], 'melhorias' => [], 'correcoes' => []];
        ?>
            <div x-show="activeVersion === <?= $index ?>" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 class="bg-darkcard border border-darkborder rounded-2xl p-8 shadow-2xl relative overflow-hidden">
                
                <!-- Glow decorativo -->
                <div class="absolute -right-20 -top-20 size-64 bg-emerald-500/5 rounded-full blur-3xl"></div>

                <div class="flex items-center gap-6 mb-10 pb-6 border-b border-darkborder relative z-10">
                    <div class="size-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-emerald-500 border border-emerald-500/20 shadow-[0_0_15px_rgba(16,185,129,0.1)]">
                        <span class="material-symbols-outlined text-3xl">rocket_launch</span>
                    </div>
                    <div>
                        <h3 class="text-3xl font-black text-white italic">v<?= htmlspecialchars($n['titulo']) ?></h3>
                        <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1"><?= date('d \d\e F, Y', strtotime($n['data_lancamento'])) ?></p>
                    </div>
                </div>

                <div class="space-y-8 relative z-10">
                    <!-- SECTION: NOVIDADES -->
                    <?php if (!empty($changelog['novidades'])): ?>
                        <div>
                            <h4 class="flex items-center gap-2 text-emerald-400 font-black text-xs uppercase tracking-widest mb-4">
                                <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                                Novidades
                            </h4>
                            <div class="space-y-3">
                                <?php foreach ($changelog['novidades'] as $item): ?>
                                    <div class="bg-black/30 border border-emerald-500/10 p-4 rounded-xl flex gap-3 group hover:border-emerald-500/30 transition-all">
                                        <div class="size-1.5 rounded-full bg-emerald-500 mt-2 flex-shrink-0 shadow-[0_0_8px_#10B981]"></div>
                                        <p class="text-sm text-gray-300 leading-relaxed"><?= htmlspecialchars($item) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- SECTION: MELHORIAS -->
                    <?php if (!empty($changelog['melhorias'])): ?>
                        <div>
                            <h4 class="flex items-center gap-2 text-blue-400 font-black text-xs uppercase tracking-widest mb-4">
                                <span class="material-symbols-outlined text-[18px]">trending_up</span>
                                Melhorias
                            </h4>
                            <div class="space-y-3">
                                <?php foreach ($changelog['melhorias'] as $item): ?>
                                    <div class="bg-black/30 border border-blue-500/10 p-4 rounded-xl flex gap-3 group hover:border-blue-500/30 transition-all">
                                        <div class="size-1.5 rounded-full bg-blue-500 mt-2 flex-shrink-0 shadow-[0_0_8px_#3b82f6]"></div>
                                        <p class="text-sm text-gray-300 leading-relaxed"><?= htmlspecialchars($item) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- SECTION: CORREÇÕES -->
                    <?php if (!empty($changelog['correcoes'])): ?>
                        <div>
                            <h4 class="flex items-center gap-2 text-orange-400 font-black text-xs uppercase tracking-widest mb-4">
                                <span class="material-symbols-outlined text-[18px]">pest_control</span>
                                Correções
                            </h4>
                            <div class="space-y-3">
                                <?php foreach ($changelog['correcoes'] as $item): ?>
                                    <div class="bg-black/30 border border-orange-500/10 p-4 rounded-xl flex gap-3 group hover:border-orange-500/30 transition-all">
                                        <div class="size-1.5 rounded-full bg-orange-500 mt-2 flex-shrink-0 shadow-[0_0_8px_#f97316]"></div>
                                        <p class="text-sm text-gray-300 leading-relaxed"><?= htmlspecialchars($item) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-12 pt-6 border-t border-darkborder flex justify-end">
                    <a href="dashboard.php" class="bg-white/5 hover:bg-emerald-500 text-white hover:text-black px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all italic border border-white/10 hover:border-emerald-500 shadow-xl">
                        Voltar ao Dashboard
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
