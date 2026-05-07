<?php
/**
 * SGIM CLIENT - GLOBAL HEADER (v4.6 - IDENTITY SYNC)
 */
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Updater/UpdaterCore.php';
require_once __DIR__ . '/ota_helper.php'; // HELPER DE IDENTIDADE UNIFICADA

use App\Models\ThemeModel;
use App\Updater\UpdaterCore;

$themeModel = new ThemeModel($pdo);
$theme = $themeModel->getTheme();

if (!$theme) {
    $theme = [
        'logo_url' => '', 'cor_brand' => '#FFC107', 'cor_brand_dark' => '#D4AF37', 'cor_brand_light' => '#FFD54F',
        'darkbg' => '#050505', 'darkcard' => '#121212', 'darkborder' => '#1E1E1E',
        'lightbg' => '#F3F4F6', 'lightcard' => '#FFFFFF', 'lightborder' => '#E5E7EB', 'modo_padrao' => 'dark'
    ];
}

$theme_json = json_encode($theme);

// 1. DESCOBERTA DE VERSÃO (IDENTIDADE REAL)
$currentVersion = get_local_version();

// 2. LOGICA DE NOTIFICAÇÃO (SININHO)
$unreadCount = 0;
$notificacoes = [];
try {
    // Sincroniza notificações: marca como lidas as da versão atual
    $pdo->prepare("UPDATE sistema_novidades SET visto = 1 WHERE visto = 0 AND titulo LIKE ?")->execute(['%v' . $currentVersion . '%']);
    
    // Busca informações de update na sessão (alimentadas via AJAX na Central de Atualizações)
    $otaInfo = $_SESSION['ota_available'] ?? null;

    $stmtNotif = $pdo->query("SELECT * FROM sistema_novidades ORDER BY id DESC LIMIT 10");
    $notificacoes = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM sistema_novidades WHERE visto = 0");
    $unreadCount = $stmtCount->fetchColumn();
} catch (Exception $e) {
    // Silencer
}
$notificacoes_json = json_encode($notificacoes);
?>
<!DOCTYPE html>
<html :class="darkMode ? 'dark' : ''" lang="pt-br" x-data="themeManager(<?= htmlspecialchars($theme_json) ?>)" x-init="initTheme()">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?? 'SGIM - Dashboard Cliente' ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        body { color: var(--c-text); background-color: var(--c-bg); }
        .text-white { color: var(--c-text) !important; }
        .text-gray-300 { color: var(--c-text) !important; }
        .text-gray-400 { color: var(--c-text-muted) !important; }
        .text-gray-500 { color: var(--c-text-muted) !important; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="<?= $theme['cor_brand'] ?>">
</head>
<body class="bg-darkbg text-gray-100 font-sans antialiased min-h-screen flex transition-colors duration-300">
    <!-- Sidebar -->
    <aside class="w-64 fixed inset-y-0 left-0 bg-darkcard border-r border-darkborder flex flex-col z-50">
        <div class="p-6 flex flex-col border-b border-darkborder">
            <div class="flex items-center gap-3">
                <?php if (!empty($theme['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($theme['logo_url']) ?>" alt="Logo" class="h-10 max-w-[180px] object-contain">
                <?php else: ?>
                    <div class="size-9 bg-brand rounded-lg flex items-center justify-center text-black">
                        <span class="material-symbols-outlined text-xl font-bold">church</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-brand tracking-tighter leading-none">SGIM</h1>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-widest">v<?= $currentVersion ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-6">
            <div>
                <ul class="space-y-1">
                    <li><a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'dashboard') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> font-medium transition-all" href="dashboard.php"><span class="material-symbols-outlined text-[20px]">dashboard</span><span class="text-sm">Dashboard</span></a></li>
                    <li><a class="flex items-center justify-between px-3 py-2.5 rounded-twelve <?= ($current_page == 'novidades') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="novidades.php"><div class="flex items-center gap-3"><span class="material-symbols-outlined text-[20px]">notifications_active</span><span class="text-sm">Novidades</span></div><?php if ($unreadCount > 0): ?><span class="flex size-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white"><?= $unreadCount ?></span><?php endif; ?></a></li>
                    <li><a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'membros') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="membros.php"><span class="material-symbols-outlined text-[20px]">group</span><span class="text-sm">Membros</span></a></li>
                    <li><a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'financeiro') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="financeiro.php"><span class="material-symbols-outlined text-[20px]">payments</span><span class="text-sm">Financeiro</span></a></li>
                </ul>
            </div>
            <div class="pt-4 border-t border-darkborder">
                <h3 class="px-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Configurações</h3>
                <ul class="space-y-1">
                    <li><a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'configuracoes') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="configuracoes.php"><span class="material-symbols-outlined text-[20px]">settings</span><span class="text-sm">Configurações</span></a></li>
                    <li><a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'atualizacoes') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="atualizacoes.php"><span class="material-symbols-outlined text-[20px]">system_update</span><span class="text-sm">Atualizações</span></a></li>
                    <li><a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-all" href="logout.php"><span class="material-symbols-outlined text-[20px]">logout</span><span class="text-sm">Sair</span></a></li>
                </ul>
            </div>
        </nav>
    </aside>
    <!-- Main Content -->
    <main class="ml-64 flex-1 flex flex-col min-h-screen">
        <header class="h-20 bg-darkbg border-b border-darkborder flex items-center justify-between px-8 sticky top-0 z-40">
            <div class="flex items-center gap-4 bg-darkcard px-4 py-2.5 rounded-twelve border border-darkborder w-96">
                <span class="material-symbols-outlined text-gray-500">search</span>
                <input class="bg-transparent border-none focus:ring-0 text-sm w-full placeholder:text-gray-500 text-gray-300" placeholder="Pesquisar..." type="text"/>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-semibold text-white leading-none">Administrador</p>
                    <p class="text-[10px] text-brand uppercase font-bold mt-1">v<?= $currentVersion ?></p>
                </div>
                <div class="size-10 rounded-full bg-darkborder overflow-hidden border-2 border-brand/20 flex items-center justify-center">
                    <span class="material-symbols-outlined">person</span>
                </div>
            </div>
        </header>
        <div class="p-8 space-y-8">
