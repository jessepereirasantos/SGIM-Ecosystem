<?php
/**
 * SGIM CLIENT - GLOBAL HEADER (v4.6 - IDENTITY SYNC)
 */
// Fallback Seguro (Padrão Premium SGIM)
$theme = [
    'logo_url' => '', 'cor_brand' => '#ffc880', 'cor_brand_dark' => '#d4a35d', 'cor_brand_light' => '#ffd9a8',
    'darkbg' => '#050505', 'darkcard' => '#121212', 'darkborder' => '#1e1e1e',
    'lightbg' => '#F3F4F6', 'lightcard' => '#FFFFFF', 'lightborder' => '#E5E7EB', 'modo_padrao' => 'dark'
];

try {
    require_once __DIR__ . '/../src/autoload.php';
    if (isset($pdo) && $pdo) {
        $themeModel = new ThemeModel($pdo);
        $dbTheme = $themeModel->getTheme();
        if ($dbTheme) $theme = array_merge($theme, $dbTheme);
    }
} catch (Throwable $e) {
    error_log("SGIM Theme Warning: Usando fallback devido a erro: " . $e->getMessage());
}

$theme_json = json_encode($theme);

// 1. LOGICA DE NOTIFICAÇÃO (SININHO)
$unreadCount = 0;
?>
<!DOCTYPE html>
<html :class="darkMode ? 'dark' : ''" lang="pt-br" x-data="themeManager(<?= htmlspecialchars($theme_json) ?>)" x-init="init()">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?? 'SGIM - Dashboard Cliente' ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    
    <script>
        let isDarkMode = localStorage.getItem('sgim_theme') ? localStorage.getItem('sgim_theme') === 'dark' : ('<?= $theme['modo_padrao'] ?>' === 'dark');
        if(!isDarkMode) {
            document.documentElement.style.setProperty('--c-bg', '<?= $theme['lightbg'] ?>');
            document.documentElement.style.setProperty('--c-card', '<?= $theme['lightcard'] ?>');
            document.documentElement.style.setProperty('--c-border', '<?= $theme['lightborder'] ?>');
            document.documentElement.style.setProperty('--c-text', '#111827');
            document.documentElement.style.setProperty('--c-text-muted', '#6B7280');
        } else {
            document.documentElement.style.setProperty('--c-bg', '<?= $theme['darkbg'] ?>');
            document.documentElement.style.setProperty('--c-card', '<?= $theme['darkcard'] ?>');
            document.documentElement.style.setProperty('--c-border', '<?= $theme['darkborder'] ?>');
            document.documentElement.style.setProperty('--c-text', '#FFFFFF');
            document.documentElement.style.setProperty('--c-text-muted', '#9CA3AF');
        }

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '<?= $theme['cor_brand'] ?>', dark: '<?= $theme['cor_brand_dark'] ?>', light: '<?= $theme['cor_brand_light'] ?>' },
                        darkbg: 'var(--c-bg)', darkcard: 'var(--c-card)', darkborder: 'var(--c-border)'
                    },
                    borderRadius: { 'twelve': '12px' },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
        
        document.addEventListener('alpine:init', () => {
            Alpine.data('themeManager', (serverTheme) => ({
                darkMode: localStorage.getItem('sgim_theme') ? localStorage.getItem('sgim_theme') === 'dark' : (serverTheme.modo_padrao === 'dark'),
                theme: serverTheme,
                initTheme() { this.applyTheme(); },
                toggleTheme() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('sgim_theme', this.darkMode ? 'dark' : 'light');
                    this.applyTheme();
                },
                applyTheme() {
                    const doc = document.documentElement;
                    if (!this.darkMode) {
                        doc.style.setProperty('--c-bg', this.theme.lightbg);
                        doc.style.setProperty('--c-card', this.theme.lightcard);
                        doc.style.setProperty('--c-border', this.theme.lightborder);
                        doc.style.setProperty('--c-text', '#111827');
                        doc.style.setProperty('--c-text-muted', '#6B7280');
                        doc.classList.remove('dark');
                    } else {
                        doc.style.setProperty('--c-bg', this.theme.darkbg);
                        doc.style.setProperty('--c-card', this.theme.darkcard);
                        doc.style.setProperty('--c-border', this.theme.darkborder);
                        doc.style.setProperty('--c-text', '#FFFFFF');
                        doc.style.setProperty('--c-text-muted', '#9CA3AF');
                        doc.classList.add('dark');
                    }
                },
                showNotifications: false,
                unreadCount: <?= $unreadCount ?? 0 ?>,
                notifications: <?= isset($notificacoes_json) ? $notificacoes_json : '[]' ?>,
                
                // --- MOTOR DE POLLING OTA ---
                otaAvailable: false,
                otaNotes: '',
                otaVersion: '',
                
                async checkOTA() {
                    try {
                        let res = await fetch('ota.php');
                        let data = await res.json();
                        
                        // Se detectar versão maior, dispara frontend
                        if (data.status === 'success' && data.has_update) {
                            this.otaAvailable = true;
                            this.otaVersion = data.latest_version;
                            this.otaNotes = data.notes;
                            
                            // Injeta no Sino
                            this.unreadCount++;
                            this.notifications.unshift({
                                id: 'ota_alert',
                                titulo: 'Atualização ' + data.latest_version + ' Disponível',
                                mensagem: data.notes,
                                data_criacao: new Date().toISOString(),
                                visto: 0,
                                link: 'admin_ota.php' 
                            });
                        }
                    } catch (e) { 
                        console.error('FALHA DE POLLING OTA:', e); 
                    }
                },
                
                init() {
                    this.initTheme();
                    this.checkOTA(); // Dispara sondagem ao carregar
                },

                async markAllRead() {
                    let fd = new FormData(); fd.append('acao', 'marcar_lidas');
                    await fetch('api/v1/notificacoes_action.php', { method: 'POST', body: fd });
                    this.unreadCount = 0;
                    this.notifications.forEach(n => n.visto = 1);
                },
                async deleteAll() {
                    if (!confirm('Deseja excluir todas as notificações?')) return;
                    let fd = new FormData(); fd.append('acao', 'excluir_todas');
                    await fetch('api/v1/notificacoes_action.php', { method: 'POST', body: fd });
                    this.notifications = [];
                    this.unreadCount = 0;
                }
            }));
        });
    </script>
    <style>
        :root {
            --brand-primary: #ffc880;
            --brand-dark: #d4a35d;
            --brand-light: #ffd9a8;
            --bg-main: #050505;
            --bg-card: #121212;
            --border-color: #1e1e1e;
        }

        body { 
            background-color: var(--bg-main); 
            color: #e5e7eb; 
            font-family: 'Inter', sans-serif;
        }

        .glass-card {
            background: rgba(18, 18, 18, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
        }

        .sidebar-item {
            display: flex;
            items-center: center;
            gap: 12px;
            padding: 10px 16px;
            border-radius: 12px;
            transition: all 0.2s;
            color: #9ca3af;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .sidebar-item:hover {
            background: rgba(255, 200, 128, 0.05);
            color: var(--brand-primary);
        }

        .sidebar-item.active {
            background: linear-gradient(90deg, rgba(255, 200, 128, 0.1) 0%, rgba(255, 200, 128, 0) 100%);
            color: var(--brand-primary);
            border-left: 3px solid var(--brand-primary);
            border-radius: 4px 12px 12px 4px;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ffc880">
</head>
<body class="bg-[#050505] text-gray-100 antialiased min-h-screen flex">
    
    <!-- BANNER OTA FLUTUANTE (Renderizado via Polling) -->
    <template x-if="otaAvailable">
        <div class="fixed top-6 right-6 z-[100] max-w-sm w-full bg-gradient-to-r from-emerald-900 to-green-900 border border-emerald-500/30 rounded-xl shadow-2xl p-4 animate-bounce">
            <div class="flex items-start gap-4">
                <div class="size-10 bg-emerald-500/20 rounded-lg flex items-center justify-center text-emerald-400">
                    <span class="material-symbols-outlined">system_update</span>
                </div>
                <div class="flex-1">
                    <h4 class="text-white font-bold text-sm">Atualização v<span x-text="otaVersion"></span></h4>
                    <p class="text-emerald-200 text-xs mt-1 leading-relaxed" x-text="otaNotes"></p>
                    <a href="admin_ota.php" class="mt-3 inline-block bg-emerald-500 text-black text-xs font-bold px-4 py-2 rounded shadow hover:bg-emerald-400 transition-colors">ATUALIZAR AGORA</a>
                </div>
                <button @click="otaAvailable = false" class="text-emerald-400 hover:text-white">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>
    </template>
    
    <!-- Sidebar -->
    <aside class="w-72 fixed inset-y-0 left-0 bg-[#050505] border-r border-[#1e1e1e] flex flex-col z-50">
        <div class="p-8 mb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-gradient-to-br from-[#ffc880] to-[#d4a35d] rounded-xl flex items-center justify-center shadow-lg shadow-[#ffc880]/10">
                    <span class="material-symbols-outlined text-black font-bold text-2xl">church</span>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white tracking-tight leading-none">SGIM</h1>
                    <p class="text-[10px] text-[#ffc880] uppercase tracking-widest font-bold mt-1">SaaS Edition</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-6 overflow-y-auto py-4">
            <!-- GRUPO: PRINCIPAL -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-3">Painel</p>
                <div class="space-y-1">
                    <a href="dashboard.php" class="sidebar-item <?= ($current_page == 'dashboard') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span>Início</span>
                    </a>
                    <a href="novidades.php" class="sidebar-item <?= ($current_page == 'novidades') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">campaign</span>
                        <div class="flex justify-between items-center w-full">
                            <span>Comunicados</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="bg-[#ffc880] text-black text-[9px] font-black px-1.5 py-0.5 rounded-full"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            </div>

            <!-- GRUPO: GESTÃO MINISTERIAL -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-3">Ministerial</p>
                <div class="space-y-1">
                    <a href="membros.php" class="sidebar-item <?= ($current_page == 'membros') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">group</span>
                        <span>Membros</span>
                    </a>
                    <a href="congregacoes.php" class="sidebar-item <?= ($current_page == 'congregacoes') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">church</span>
                        <span>Congregações</span>
                    </a>
                    <a href="departamentos.php" class="sidebar-item <?= ($current_page == 'departamentos') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">corporate_fare</span>
                        <span>Departamentos</span>
                    </a>
                    <a href="eventos.php" class="sidebar-item <?= ($current_page == 'eventos') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">calendar_month</span>
                        <span>Eventos</span>
                    </a>
                </div>
            </div>

            <!-- GRUPO: SECRETARIA E COMUNICAÇÃO -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-3">Secretaria</p>
                <div class="space-y-1">
                    <a href="carteirinha_digital.php" class="sidebar-item <?= ($current_page == 'carteirinhas') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">badge</span>
                        <span>Carteirinhas</span>
                    </a>
                    <a href="whatsapp.php" class="sidebar-item <?= ($current_page == 'whatsapp') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">chat_bubble</span>
                        <span>WhatsApp</span>
                    </a>
                    <a href="comunicacao.php" class="sidebar-item <?= ($current_page == 'comunicacao') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">mail</span>
                        <span>E-mail Marketing</span>
                    </a>
                </div>
            </div>

            <!-- GRUPO: FINANCEIRO -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-3">Tesouraria</p>
                <div class="space-y-1">
                    <a href="financeiro.php" class="sidebar-item <?= ($current_page == 'financeiro') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">payments</span>
                        <span>Dashboard Financeira</span>
                    </a>
                    <a href="financeiro_relatorio.php" class="sidebar-item <?= ($current_page == 'relatorios_fin') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">analytics</span>
                        <span>Relatórios PDF</span>
                    </a>
                </div>
            </div>

            <!-- GRUPO: ESTATÍSTICAS E SISTEMA -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-3">Administração</p>
                <div class="space-y-1">
                    <a href="relatorios.php" class="sidebar-item <?= ($current_page == 'relatorios') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">assessment</span>
                        <span>Relatórios Gerais</span>
                    </a>
                    <a href="configuracoes.php" class="sidebar-item <?= ($current_page == 'configuracoes') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">settings</span>
                        <span>Configurações</span>
                    </a>
                    <a href="logout.php" class="sidebar-item hover:text-red-400">
                        <span class="material-symbols-outlined">logout</span>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </nav>

        <!-- Footer da Sidebar (Opcional - Status do Plano) -->
        <div class="p-6">
            <div class="bg-[#121212] border border-[#1e1e1e] rounded-2xl p-4">
                <p class="text-[10px] text-gray-500 font-bold uppercase mb-2">Suporte Direto</p>
                <a href="#" class="text-xs text-[#ffc880] hover:underline flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">help</span>
                    Central de Ajuda
                </a>
            </div>
        </div>
    </aside>

    <!-- Conteúdo Principal -->
    <main class="ml-72 flex-1 flex flex-col min-h-screen relative">
        <!-- Header Superior -->
        <header class="h-24 flex items-center justify-between px-10 sticky top-0 z-40 bg-[#050505]/80 backdrop-blur-md border-b border-[#1e1e1e]">
            <div>
                <h2 class="text-2xl font-bold text-white tracking-tight"><?= $page_title ?? 'Dashboard' ?></h2>
                <p class="text-xs text-gray-500">Bem-vindo ao SGIM, Administrador.</p>
            </div>

            <div class="flex items-center gap-6">
                <button class="size-11 flex items-center justify-center rounded-xl bg-[#121212] border border-[#1e1e1e] text-gray-400 hover:text-[#ffc880] transition-colors relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="absolute top-2.5 right-2.5 size-2 bg-[#ffc880] rounded-full ring-4 ring-[#050505]"></span>
                    <?php endif; ?>
                </button>
                
                <div class="h-12 w-[1px] bg-[#1e1e1e]"></div>

                <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-white leading-none">Administrador</p>
                        <p class="text-[10px] text-[#ffc880] font-bold uppercase mt-1 tracking-tighter">Nível Total</p>
                    </div>
                    <div class="size-12 rounded-2xl bg-gradient-to-br from-[#1e1e1e] to-[#121212] border border-[#1e1e1e] flex items-center justify-center text-[#ffc880] shadow-xl">
                        <span class="material-symbols-outlined text-2xl">person</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Container de Conteúdo -->
        <div class="p-10">
