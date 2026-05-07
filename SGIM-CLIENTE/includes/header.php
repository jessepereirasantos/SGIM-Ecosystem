<?php
// Bootstrap & MVC Engine
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Updater/UpdaterCore.php';

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

// Passa o tema default para o front-end via JS
$theme_json = json_encode($theme);

// Busca Notificações para o "Sininho"
$unreadCount = 0;
$notificacoes = [];
try {
    // 1. Verificar se há atualizações pendentes (Motor v3.0)
    
    // Obter configuração do master_url
    $stmtCfg = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
    $masterUrl = $stmtCfg->fetchColumn() ?: 'https://escolateologicaeloha.com.br/';
    
    // Obter chave de licença
    $stmtLic = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'license_key'");
    $licenseKey = $stmtLic->fetchColumn() ?: '';

    // Obter versão atual
    $stmtVer = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
    $currentVersion = $stmtVer->fetchColumn() ?: '1.1.0';

    $updater = new UpdaterCore($pdo, $licenseKey, $currentVersion);
    $updater->setApiUrl($masterUrl);
    
    $checkUpdate = false;
    $interval = (isset($current_page) && ($current_page == 'dashboard' || $current_page == 'atualizacoes')) ? 60 : 1800;

    if (!isset($_SESSION['last_ota_check']) || (time() - $_SESSION['last_ota_check'] > $interval) || isset($_GET['force_ota'])) {
        $checkUpdate = true;
    }
    if ($checkUpdate) {
        // LOG DE REQUISIÇÃO (PROVA 1)
        error_log("[SGIM-OTA] [CLIENT] CHECK REQUEST: version=$currentVersion | master=$masterUrl");
        
        $ota = $updater->checkForUpdate();
        
        // LOG DE RESPOSTA (PROVA 2)
        error_log("[SGIM-OTA] [CLIENT] CHECK RESPONSE: " . json_encode($ota));

        if (isset($ota['has_update']) && $ota['has_update']) {
            $ver = $ota['latest_version'] ?? $ota['latest'] ?? '0.0.0';
            
            // TRAVA DE SEGURANÇA: Só processar se a versão for REALMENTE maior
            if (version_compare($ver, $currentVersion, '<=')) {
                error_log("[SGIM-OTA] [CLIENT] DECISION: has_update=false (Bloqueio de versão obsoleta: $ver <= $currentVersion)");
                unset($_SESSION['ota_available']);
                $ota['has_update'] = false;
            } else {
                $_SESSION['ota_available'] = $ota;
                error_log("[SGIM-OTA] [CLIENT] DECISION: has_update=true | version=$ver");
            }
        }

        if ($checkUpdate && isset($_SESSION['ota_available'])) {
            $ver = $_SESSION['ota_available']['latest_version'] ?? $_SESSION['ota_available']['latest'] ?? '?.?.?';
            
            // Inserir novidade no banco se ainda não existir
            $stmtExists = $pdo->prepare("SELECT COUNT(*) FROM sistema_novidades WHERE titulo LIKE ?");
            $stmtExists->execute(['%v' . $ver . '%']);
            if ($stmtExists->fetchColumn() == 0) {
                $stmtInsert = $pdo->prepare("INSERT INTO sistema_novidades (titulo, descricao, icone, visto, data_lancamento) VALUES (?, ?, ?, 0, NOW())");
                $res = $stmtInsert->execute([
                    'Nova Versão Disponível: v' . $ver,
                    'Uma nova atualização acaba de ser liberada. Clique para ver as novidades!',
                    'system_update'
                ]);
                error_log("[SGIM-OTA] [CLIENT DB] Inserindo versão $ver | Sucesso: " . ($res ? 'Sim' : 'Não'));
            }
        } else {
            // LOG DE DECISÃO (PROVA 3)
            error_log("[SGIM-OTA] [CLIENT] DECISION: has_update=false");
            unset($_SESSION['ota_available']);
        }
    $_SESSION['last_ota_check'] = time();
    }
    
    // Validação de Consistência UI (Evita banner "colado" após atualização)
    if (isset($_SESSION['ota_available'])) {
        $ver_ota = $_SESSION['ota_available']['latest_version'] ?? $_SESSION['ota_available']['latest'] ?? '0.0.0';
        if (version_compare($ver_ota, $currentVersion, '<=')) {
            unset($_SESSION['ota_available']);
        }
    }
    
    $otaInfo = $_SESSION['ota_available'] ?? null;

    // 2. Buscar Notificações do Banco e Limpar Alertas de Versão já instalada
    $pdo->prepare("UPDATE sistema_novidades SET visto = 1 WHERE visto = 0 AND titulo LIKE ?")->execute(['%v' . $currentVersion . '%']);
    
    $stmtNotif = $pdo->query("SELECT * FROM sistema_novidades ORDER BY id DESC LIMIT 10");
    $notificacoes = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM sistema_novidades WHERE visto = 0");
    $unreadCount = $stmtCount->fetchColumn();

} catch (Exception $e) {
    // Silencer para não quebrar o layout
}
$notificacoes_json = json_encode($notificacoes);
?>
<!DOCTYPE html>
<html :class="darkMode ? 'dark' : ''" lang="pt-br" x-data="themeManager(<?= htmlspecialchars($theme_json) ?>)" x-init="initTheme()">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?? 'SGIM - Dashboard Cliente' ?></title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    
    <script>
        // Alpine Store and Logic for Theming
        let isDarkMode = localStorage.getItem('sgim_theme') ? localStorage.getItem('sgim_theme') === 'dark' : ('<?= $theme['modo_padrao'] ?>' === 'dark');
        
        // Define initial CSS variable overrides directly on HTML to prevent flicker
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

        // Tailwind Config JS Builder
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '<?= $theme['cor_brand'] ?>',
                            dark: '<?= $theme['cor_brand_dark'] ?>',
                            light: '<?= $theme['cor_brand_light'] ?>'
                        },
                        // We use CSS variables for background colors to allow instant light/dark toggle 
                        // without needing to refresh or rewrite the Tailwind compiler tree.
                        darkbg: 'var(--c-bg)',
                        darkcard: 'var(--c-card)',
                        darkborder: 'var(--c-border)'
                    },
                    borderRadius: {
                        'twelve': '12px',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
        
        document.addEventListener('alpine:init', () => {
            Alpine.data('themeManager', (serverTheme) => ({
                darkMode: localStorage.getItem('sgim_theme') ? localStorage.getItem('sgim_theme') === 'dark' : (serverTheme.modo_padrao === 'dark'),
                theme: serverTheme,
                initTheme() {
                    this.applyTheme();
                },
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
                // Atributos de Notificação
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
        /* Base Text Colors mapped dynamically to prevent hardcoding white/gray text */
        body { color: var(--c-text); background-color: var(--c-bg); }
        .text-white { color: var(--c-text) !important; }
        .text-gray-300 { color: var(--c-text) !important; }
        .text-gray-400 { color: var(--c-text-muted) !important; }
        .text-gray-500 { color: var(--c-text-muted) !important; }
        
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--c-bg); }
        ::-webkit-scrollbar-thumb { background: var(--c-border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: <?= $theme['cor_brand'] ?>; }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="<?= $theme['cor_brand'] ?>">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js');
        }
    </script>
    <?= $extra_head ?? '' ?>
</head>
<body class="bg-darkbg text-gray-100 font-sans antialiased min-h-screen flex transition-colors duration-300">
    <!-- Sidebar -->
    <aside class="w-64 fixed inset-y-0 left-0 bg-darkcard border-r border-darkborder flex flex-col z-50">
        <div class="p-6 flex flex-col border-b border-darkborder">
            <div class="flex items-center gap-3">
                <?php if (!empty($theme['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($theme['logo_url']) ?>" alt="Ministry Logo" class="h-10 max-w-[180px] object-contain">
                <?php else: ?>
                    <div class="size-9 bg-brand rounded-lg flex items-center justify-center text-black">
                        <span class="material-symbols-outlined text-xl font-bold">church</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-brand tracking-tighter leading-none">SGIM</h1>
                        <span class="text-[10px] text-gray-500 font-medium uppercase tracking-widest">Management System</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-6">
            <div>
                <ul class="space-y-1">
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'dashboard') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> font-medium transition-all" href="dashboard.php">
                            <span class="material-symbols-outlined text-[20px]">dashboard</span>
                            <span class="text-sm">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center justify-between px-3 py-2.5 rounded-twelve <?= ($current_page == 'novidades') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="novidades.php">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px]">notifications_active</span>
                                <span class="text-sm">Novidades</span>
                            </div>
                            <?php 
                                $stmtUnread = $pdo->query("SELECT COUNT(*) FROM sistema_novidades WHERE visto = 0");
                                $unreadCount = $stmtUnread->fetchColumn();
                                if ($unreadCount > 0): 
                            ?>
                                <span class="flex size-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white animate-pulse"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'membros') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="membros.php">
                            <span class="material-symbols-outlined text-[20px]">group</span>
                            <span class="text-sm">Membros</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'carteirinha_digital') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="carteirinha_digital.php">
                            <span class="material-symbols-outlined text-[20px]">badge</span>
                            <span class="text-sm">Carteirinhas Digitais</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'congregacoes') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="congregacoes.php">
                            <span class="material-symbols-outlined text-[20px]">apartment</span>
                            <span class="text-sm">Congregações</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'departamentos') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="departamentos.php">
                            <span class="material-symbols-outlined text-[20px]">account_tree</span>
                            <span class="text-sm">Departamentos</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'financeiro') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="financeiro.php">
                            <span class="material-symbols-outlined text-[20px]">payments</span>
                            <span class="text-sm">Financeiro</span>
                        </a>
                    </li>
                     <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'eventos') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="eventos.php">
                            <span class="material-symbols-outlined text-[20px]">calendar_month</span>
                            <span class="text-sm">Eventos</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'comunicacao') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="comunicacao.php">
                            <span class="material-symbols-outlined text-[20px]">chat</span>
                            <span class="text-sm">Comunicação</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'whatsapp') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="whatsapp.php">
                            <span class="material-symbols-outlined text-[20px]">send_and_archive</span>
                            <span class="text-sm">WhatsApp</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'crescimento') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="crescimento.php">
                            <span class="material-symbols-outlined text-[20px]">trending_up</span>
                            <span class="text-sm">Crescimento</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'relatorios') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="relatorios.php">
                            <span class="material-symbols-outlined text-[20px]">analytics</span>
                            <span class="text-sm">Relatórios Gerais</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="pt-4 border-t border-darkborder">
                <h3 class="px-3 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Configurações</h3>
                <ul class="space-y-1">
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'configuracoes') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="configuracoes.php">
                            <span class="material-symbols-outlined text-[20px]">settings</span>
                            <span class="text-sm">Configurações Base</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'tema_personalizacao') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="tema_personalizacao.php">
                            <span class="material-symbols-outlined text-[20px]">palette</span>
                            <span class="text-sm">Aparência e Temas</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'backup') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="backup.php">
                            <span class="material-symbols-outlined text-[20px]">backup</span>
                            <span class="text-sm">Backups</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve <?= ($current_page == 'atualizacoes') ? 'bg-brand/10 text-brand' : 'text-gray-400 hover:text-brand hover:bg-white/5' ?> transition-all" href="atualizacoes.php">
                            <span class="material-symbols-outlined text-[20px]">system_update</span>
                            <span class="text-sm">Atualizações</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve text-emerald-500 hover:bg-emerald-500/10 transition-all" href="https://wa.me/seunumerousuporte" target="_blank">
                            <span class="material-symbols-outlined text-[20px]">support_agent</span>
                            <span class="text-sm">Suporte Prioritário</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 px-3 py-2.5 rounded-twelve text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition-all" href="logout.php">
                            <span class="material-symbols-outlined text-[20px]">logout</span>
                            <span class="text-sm">Sair</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>
    <!-- Main Content -->
    <main class="ml-64 flex-1 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="h-20 bg-darkbg border-b border-darkborder flex items-center justify-between px-8 sticky top-0 z-40">
            <div class="flex items-center gap-4 bg-darkcard px-4 py-2.5 rounded-twelve border border-darkborder w-96">
                <span class="material-symbols-outlined text-gray-500">search</span>
                <input class="bg-transparent border-none focus:ring-0 text-sm w-full placeholder:text-gray-500 text-gray-300" placeholder="Pesquisar..." type="text"/>
            </div>
            <div class="flex items-center gap-6">
                <!-- Theme Toggle Button -->
                <button @click="toggleTheme()" class="bg-darkcard border border-darkborder p-2 rounded-twelve text-gray-400 hover:text-brand hover:border-brand transition-all relative shadow-sm">
                    <span class="material-symbols-outlined" x-text="darkMode ? 'light_mode' : 'dark_mode'">light_mode</span>
                </button>
                
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="bg-darkcard border border-darkborder p-2 rounded-twelve text-gray-400 hover:text-brand hover:border-brand transition-all relative shadow-sm">
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="absolute -top-1 -right-1 size-4 bg-red-500 text-[9px] font-bold text-white flex items-center justify-center rounded-full border-2 border-darkbg animate-pulse">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Dropdown de Notificações -->
                    <div x-show="open" 
                         @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute right-0 mt-3 w-80 bg-darkcard border border-darkborder rounded-twelve shadow-2xl z-50 overflow-hidden">
                        
                        <div class="p-4 border-b border-darkborder flex justify-between items-center bg-white/[0.02]">
                            <h4 class="text-xs font-bold text-white uppercase tracking-widest">Notificações</h4>
                            <div class="flex gap-4">
                                <button @click="markAllRead()" class="text-[9px] text-brand hover:underline font-bold uppercase tracking-tighter">Lidas</button>
                                <button @click="deleteAll()" class="text-[9px] text-red-500 hover:underline font-bold uppercase tracking-tighter">Excluir</button>
                                <a href="novidades.php" class="text-[9px] text-gray-500 hover:underline font-bold uppercase tracking-tighter">Ver todas</a>
                            </div>
                        </div>

                        <div class="max-h-96 overflow-y-auto divide-y divide-darkborder">
                            <?php if (count($notificacoes) > 0): ?>
                                <?php foreach ($notificacoes as $n): ?>
                                    <div class="p-4 hover:bg-white/[0.02] transition-colors relative">
                                        <?php if (!$n['visto']): ?>
                                            <div class="absolute left-2 top-1/2 -translate-y-1/2 w-1 h-1 bg-brand rounded-full"></div>
                                        <?php endif; ?>
                                        <div class="flex gap-3">
                                            <div class="size-8 rounded-lg bg-brand/10 flex items-center justify-center text-brand flex-shrink-0">
                                                <span class="material-symbols-outlined text-[18px]"><?= htmlspecialchars($n['icone'] ?? 'info') ?></span>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-white leading-tight"><?= htmlspecialchars($n['titulo']) ?></p>
                                                <p class="text-[11px] text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($n['descricao']) ?></p>
                                                <span class="text-[9px] text-gray-600 mt-2 block uppercase font-medium"><?= date('d/m/Y', strtotime($n['data_lancamento'] ?? 'now')) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-gray-500 text-xs">
                                    Nenhuma notificação por aqui.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="h-8 w-px bg-darkborder"></div>
                <div class="flex items-center gap-3 cursor-pointer">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-white leading-none">Administrador</p>
                        <p class="text-xs text-gray-500 mt-1">SGIM</p>
                    </div>
                    <div class="size-10 rounded-full bg-darkborder overflow-hidden border-2 border-brand/20 flex items-center justify-center">
                        <span class="material-symbols-outlined">person</span>
                    </div>
                </div>
            </div>
        </header>
        <!-- Content Area -->
        <div class="p-8 space-y-8">
