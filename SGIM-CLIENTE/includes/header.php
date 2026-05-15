<?php
/**
 * SGIM CLIENT - GLOBAL HEADER v1.1.54 (FULL MENU RESTORED)
 */
session_start();

// 1. TEMA E VERSÃO (DINÂMICOS)
$theme = [
    'logo_url' => '',
    'cor_brand' => '#ffc880',
    'cor_brand_dark' => '#d4a35d',
    'cor_brand_light' => '#ffd9a8',
    'modo_padrao' => 'dark',
    'darkbg' => '#050505',
    'darkcard' => '#121212',
    'darkborder' => '#1e1e1e',
    'lightbg' => '#F3F4F6',
    'lightcard' => '#FFFFFF',
    'lightborder' => '#E5E7EB'
];

$systemVersion = '1.1.54';

try {
    require_once __DIR__ . '/../src/autoload.php';
    if (isset($pdo) && $pdo) {
        $themeModel = new ThemeModel($pdo);
        $dbTheme = $themeModel->getTheme();
        if ($dbTheme) $theme = array_merge($theme, $dbTheme);

        $sVer = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $vVer = $sVer ? $sVer->fetchColumn() : false;
        if ($vVer) $systemVersion = $vVer;
    }
} catch (Throwable $e) {}

if (!defined('SYSTEM_VERSION')) define('SYSTEM_VERSION', $systemVersion);
$theme_json = json_encode($theme);

// 2. LOGICA DE ACESSO (RBAC)
$access = null;
$user_context = ['nome' => 'Usuário', 'cargo' => 'Nível Total', 'avatar' => 'person'];

if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../src/Auth/AccessManager.php';
        $access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);
        
        $stmtUser = $pdo->prepare("SELECT u.nome, c.nome as cargo_nome FROM usuarios u LEFT JOIN cargos c ON u.cargo_id = c.id WHERE u.id = ?");
        $stmtUser->execute([$_SESSION['user_id']]);
        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($uData) {
            $user_context['nome'] = $uData['nome'];
            $user_context['cargo'] = $uData['cargo_nome'] ?? 'Administrador Total';
        }
    } catch (Throwable $e) {}
}

$unreadCount = 0;
?>
<!DOCTYPE html>
<html :class="darkMode ? 'dark' : ''" lang="pt-br" x-data="themeManager(<?= htmlspecialchars($theme_json) ?>)" x-init="init()">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?= $page_title ?? 'SGIM - Dashboard' ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
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
                otaAvailable: false,
                otaVersion: '',
                otaNotes: '',
                unreadCount: 0,
                init() { this.applyTheme(); this.checkOTA(); },
                toggleTheme() { this.darkMode = !this.darkMode; localStorage.setItem('sgim_theme', this.darkMode ? 'dark' : 'light'); this.applyTheme(); },
                applyTheme() {
                    const doc = document.documentElement;
                    const t = this.theme;
                    if (!this.darkMode) {
                        doc.style.setProperty('--c-bg', t.lightbg); doc.style.setProperty('--c-card', t.lightcard); doc.style.setProperty('--c-border', t.lightborder);
                        doc.classList.remove('dark');
                    } else {
                        doc.style.setProperty('--c-bg', t.darkbg); doc.style.setProperty('--c-card', t.darkcard); doc.style.setProperty('--c-border', t.darkborder);
                        doc.classList.add('dark');
                    }
                },
                async checkOTA() {
                    try {
                        let res = await fetch('ota.php');
                        let data = await res.json();
                        if (data.status === 'success' && data.has_update) {
                            this.otaAvailable = true;
                            this.otaVersion = data.latest_version;
                        }
                    } catch (e) {}
                }
            }));
        });
    </script>
    <style>
        :root { --brand-primary: <?= $theme['cor_brand'] ?>; --bg-main: <?= $theme['darkbg'] ?>; }
        body { background-color: var(--bg-main); color: #e5e7eb; font-family: 'Inter', sans-serif; }
        .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-radius: 12px; transition: all 0.2s; color: #9ca3af; font-size: 0.875rem; font-weight: 500; }
        .sidebar-item:hover { background: rgba(255, 200, 128, 0.05); color: var(--brand-primary); }
        .sidebar-item.active { background: linear-gradient(90deg, rgba(255, 200, 128, 0.1) 0%, rgba(255, 200, 128, 0) 100%); color: var(--brand-primary); border-left: 3px solid var(--brand-primary); border-radius: 4px 12px 12px 4px; }
    </style>
</head>
<body class="bg-darkbg text-gray-100 antialiased min-h-screen flex">
    <aside class="w-72 fixed inset-y-0 left-0 bg-darkbg border-r border-darkborder flex flex-col z-50">
        <div class="p-8 mb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-brand rounded-xl flex items-center justify-center shadow-lg"><span class="material-symbols-outlined text-black font-bold text-2xl">church</span></div>
                <div><h1 class="text-xl font-bold text-white tracking-tight leading-none">SGIM</h1><p class="text-[10px] text-brand uppercase tracking-widest font-bold mt-1">SaaS Edition</p></div>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-6 overflow-y-auto py-4">
            <!-- GRUPO: PRINCIPAL -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Painel</p>
                <div class="space-y-1">
                    <a href="dashboard.php" class="sidebar-item <?= ($current_page == 'dashboard') ? 'active' : '' ?>"><span class="material-symbols-outlined">dashboard</span><span>Início</span></a>
                    
                    <div class="px-4 py-2 mx-2 bg-brand/10 border border-brand/20 rounded-lg mb-2 text-center">
                        <p class="text-[10px] text-brand font-black uppercase tracking-widest">SGIM v<?= SYSTEM_VERSION ?></p>
                    </div>

                    <a href="novidades.php" class="sidebar-item <?= ($current_page == 'novidades') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">campaign</span>
                        <div class="flex justify-between items-center w-full"><span>Comunicados</span><?php if ($unreadCount > 0): ?><span class="bg-brand text-black text-[9px] font-black px-1.5 py-0.5 rounded-full"><?= $unreadCount ?></span><?php endif; ?></div>
                    </a>
                </div>
            </div>

            <!-- GRUPO: GESTÃO MINISTERIAL -->
            <?php if (!$access || $access->can('membros', 'visualizar') || $access->can('congregacoes', 'visualizar') || $access->can('departamentos', 'visualizar') || $access->can('eventos', 'visualizar')): ?>
                <div>
                    <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Ministerial</p>
                    <div class="space-y-1">
                        <?php if (!$access || $access->can('membros', 'visualizar')): ?><a href="membros.php" class="sidebar-item <?= ($current_page == 'membros') ? 'active' : '' ?>"><span class="material-symbols-outlined">group</span><span>Membros</span></a><?php endif; ?>
                        <?php if (!$access || $access->can('congregacoes', 'visualizar')): ?><a href="congregacoes.php" class="sidebar-item <?= ($current_page == 'congregacoes') ? 'active' : '' ?>"><span class="material-symbols-outlined">church</span><span>Congregações</span></a><?php endif; ?>
                        <?php if (!$access || $access->can('departamentos', 'visualizar')): ?><a href="departamentos.php" class="sidebar-item <?= ($current_page == 'departamentos') ? 'active' : '' ?>"><span class="material-symbols-outlined">corporate_fare</span><span>Departamentos</span></a><?php endif; ?>
                        <?php if (!$access || $access->can('eventos', 'visualizar')): ?><a href="eventos.php" class="sidebar-item <?= ($current_page == 'eventos') ? 'active' : '' ?>"><span class="material-symbols-outlined">calendar_month</span><span>Eventos</span></a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- GRUPO: SECRETARIA -->
            <?php if (!$access || $access->can('comunicacao', 'visualizar')): ?>
                <div>
                    <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Secretaria</p>
                    <div class="space-y-1">
                        <a href="carteirinha_digital.php" class="sidebar-item <?= ($current_page == 'carteirinhas') ? 'active' : '' ?>"><span class="material-symbols-outlined">badge</span><span>Carteirinhas</span></a>
                        <a href="whatsapp.php" class="sidebar-item <?= ($current_page == 'whatsapp') ? 'active' : '' ?>"><span class="material-symbols-outlined">chat_bubble</span><span>WhatsApp</span></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- GRUPO: FINANCEIRO -->
            <?php if (!$access || $access->can('financeiro', 'visualizar')): ?>
                <div>
                    <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Tesouraria</p>
                    <div class="space-y-1">
                        <a href="financeiro.php" class="sidebar-item <?= ($current_page == 'financeiro') ? 'active' : '' ?>"><span class="material-symbols-outlined">payments</span><span>Tesouraria</span></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- GRUPO: SISTEMA -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Administração</p>
                <div class="space-y-1">
                    <?php if (!$access || $access->can('configuracoes', 'visualizar')): ?><a href="configuracoes.php" class="sidebar-item <?= ($current_page == 'configuracoes') ? 'active' : '' ?>"><span class="material-symbols-outlined">settings</span><span>Configurações</span></a><?php endif; ?>
                    <a href="atualizacoes.php" class="sidebar-item <?= ($current_page == 'atualizacoes') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">system_update</span>
                        <div class="flex justify-between items-center w-full"><span>Atualizações</span><template x-if="otaAvailable"><span class="bg-brand text-black text-[8px] font-black px-1.5 py-0.5 rounded-full">NOVO</span></template></div>
                    </a>
                    <a href="logout.php" class="sidebar-item hover:text-red-400"><span class="material-symbols-outlined">logout</span><span>Sair</span></a>
                </div>
            </div>
        </nav>
    </aside>

    <main class="ml-72 flex-1 flex flex-col min-h-screen relative" x-data="otaManager">
        <header class="h-24 flex items-center justify-between px-10 sticky top-0 z-40 bg-darkbg/80 backdrop-blur-md border-b border-darkborder">
            <div><h2 class="text-2xl font-bold text-white tracking-tight"><?= $page_title ?? 'Dashboard' ?></h2><p class="text-xs text-gray-500">Bem-vindo ao SGIM.</p></div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-4 text-right">
                    <div><p class="text-sm font-bold text-white"><?= htmlspecialchars($user_context['nome']) ?></p><p class="text-[10px] text-brand font-bold uppercase"><?= htmlspecialchars($user_context['cargo']) ?></p></div>
                    <div class="size-12 rounded-2xl bg-darkcard border border-darkborder flex items-center justify-center text-brand"><span class="material-symbols-outlined text-2xl">person</span></div>
                </div>
            </div>
        </header>
        <div class="p-10">
