<?php
/**
 * SGIM CLIENT - GLOBAL HEADER v1.1.73 (GOLDEN IMAGE)
 */
session_start();

// 1. FALLBACK DE TEMA (Garante que o design nunca quebre)
$theme = [
    'cor_brand' => '#ffc880',
    'cor_brand_dark' => '#d4a35d',
    'cor_brand_light' => '#ffd9a8',
    'darkbg' => '#050505',
    'darkcard' => '#121212',
    'darkborder' => '#1e1e1e',
    'modo_padrao' => 'dark'
];

$systemVersion = '1.1.62';

// 2. REUTILIZAR CONEXÃO JÁ CARREGADA (Evita fatal error de redeclaração)
// O database.php já foi incluído pela página principal (dashboard.php, membros.php, etc.)
// Apenas buscamos a versão se $pdo já estiver disponível.
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $sVer = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $vVer = $sVer ? $sVer->fetchColumn() : false;
        if ($vVer) $systemVersion = $vVer;

        // Autoload de módulos (Seguro)
        $autoPath = __DIR__ . '/../src/autoload.php';
        if (file_exists($autoPath)) {
            include_once $autoPath;

            // Motor OTA (Updater)
            if (class_exists('App\\Updater\\UpdaterCore')) {
                $updater = new \App\Updater\UpdaterCore($pdo, __DIR__ . '/../');
            }
        }
    } elseif (!isset($pdo)) {
        // Fallback: se nenhuma página carregou o banco ainda, carregamos aqui
        $dbPath = __DIR__ . '/../config/database.php';
        if (file_exists($dbPath) && !function_exists('ensureColumnExists')) {
            include_once $dbPath;
        }
    }
} catch (Throwable $e) {}

if (!defined('SYSTEM_VERSION')) define('SYSTEM_VERSION', $systemVersion);
$theme_json = json_encode($theme);

// 3. LOGICA DE ACESSO (RBAC)
$access = null;
$user_context = ['nome' => 'Usuário', 'cargo' => 'Nível Total', 'avatar' => 'person'];
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        // Usa autoloader (já registrado acima) em vez de require_once para evitar redeclaração
        if (!class_exists('SGIM\\Auth\\AccessManager')) {
            $amPath = __DIR__ . '/../src/Auth/AccessManager.php';
            if (file_exists($amPath)) require_once $amPath;
        }
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
<html :class="darkMode ? 'dark' : ''" lang="pt-br" x-data="themeManager(<?= htmlspecialchars($theme_json) ?>)"
    x-init="init()">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?= $page_title ?? 'SGIM - Dashboard' ?></title>
    
    <!-- CSS ESTÁTICO (Compilado para alta performance) -->
    <link href="assets/css/app.css?v=<?= SYSTEM_VERSION ?>" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <script>
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
                        // Polling direto e robusto
                        let res = await fetch('ota.php');
                        if (!res.ok) return;
                        let data = await res.json();
                        if (data.status === 'success' && data.has_update) {
                            this.otaAvailable = true;
                            this.otaVersion = data.latest_version;
                            this.otaNotes = data.notes;
                        }
                    } catch (e) {
                        console.log("OTA_CHECK_OFFLINE");
                    }
                }
            }));

            Alpine.data('otaManager', () => ({
                atualizando: false,
                progresso: 0,
                etapaAtual: 'Preparando ambiente...',
                otaVersion: '',
                otaNotes: '',

                async init() {
                    let res = await fetch('ota.php');
                    let data = await res.json();
                    if (data.status === 'success') {
                        this.otaVersion = data.latest_version;
                        this.otaNotes = data.notes;
                    }
                },

                async iniciarAtualizacao() {
                    if (!confirm('Deseja iniciar a atualização para v' + this.otaVersion + ' agora?')) return;
                    this.atualizando = true;
                    this.progresso = 10;
                    try {
                        this.etapaAtual = 'Baixando pacote v' + this.otaVersion + '...';
                        let resDown = await fetch('api/ota_download.php');
                        let dataDown = await resDown.json();
                        if (dataDown.status !== 'success') throw new Error(dataDown.message);
                        this.progresso = 40;
                        this.etapaAtual = 'Extraindo arquivos e validando integridade...';
                        let resExt = await fetch('api/ota_extract.php');
                        let dataExt = await resExt.json();
                        if (dataExt.status !== 'success') throw new Error(dataExt.message);
                        this.progresso = 70;
                        this.etapaAtual = 'Aplicando mudanças na raiz operacional...';
                        let resInst = await fetch('api/ota_install.php');
                        let dataInst = await resInst.json();
                        if (dataInst.status !== 'success') throw new Error(dataInst.message);
                        this.progresso = 100;
                        this.etapaAtual = 'Finalizado! Reiniciando sistema...';
                        setTimeout(() => { location.href = 'dashboard.php?updated=1'; }, 2000);
                    } catch (e) {
                        this.atualizando = false;
                        alert('FALHA NA ATUALIZAÇÃO: ' + e.message);
                    }
                }
            }));
        });
    </script>
    <style>
        :root {
            --brand-primary:
                <?= $theme['cor_brand'] ?>
            ;
            --bg-main:
                <?= $theme['darkbg'] ?>
            ;
        }

        body {
            background-color: var(--bg-main);
            color: #e5e7eb;
            font-family: 'Inter', sans-serif;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
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
    </style>
</head>

<body class="bg-darkbg text-gray-100 antialiased min-h-screen flex">
    <aside class="w-72 fixed inset-y-0 left-0 bg-darkbg border-r border-darkborder flex flex-col z-50">
        <div class="p-8 mb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-brand rounded-xl flex items-center justify-center shadow-lg"><span
                        class="material-symbols-outlined text-black font-bold text-2xl">church</span></div>
                <div>
                    <h1 class="text-xl font-bold text-white tracking-tight leading-none">SGIM</h1>
                    <p class="text-[10px] text-brand uppercase tracking-widest font-bold mt-1">SaaS Edition</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-6 overflow-y-auto py-4">
            <!-- GRUPO: PAINEL PRINCIPAL -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Painel</p>
                <div class="space-y-1">
                    <a href="dashboard.php" class="sidebar-item <?= ($current_page == 'dashboard') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span>Início</span>
                    </a>
                    <a href="novidades.php" class="sidebar-item <?= ($current_page == 'novidades') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">campaign</span>
                        <span>Comunicados</span>
                    </a>
                </div>
            </div>

            <!-- GRUPO: GESTÃO MINISTERIAL -->
            <?php if (!$access || $access->can('membros', 'visualizar') || $access->can('congregacoes', 'visualizar') || $access->can('departamentos', 'visualizar') || $access->can('eventos', 'visualizar')): ?>
                <div>
                    <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Ministerial</p>
                    <div class="space-y-1">
                        <?php if (!$access || $access->can('membros', 'visualizar')): ?><a href="membros.php"
                                class="sidebar-item <?= ($current_page == 'membros') ? 'active' : '' ?>"><span
                                    class="material-symbols-outlined">group</span><span>Membros</span></a><?php endif; ?>
                        <?php if (!$access || $access->can('congregacoes', 'visualizar')): ?><a href="congregacoes.php"
                                class="sidebar-item <?= ($current_page == 'congregacoes') ? 'active' : '' ?>"><span
                                    class="material-symbols-outlined">church</span><span>Congregações</span></a><?php endif; ?>
                        <?php if (!$access || $access->can('departamentos', 'visualizar')): ?><a href="departamentos.php"
                                class="sidebar-item <?= ($current_page == 'departamentos') ? 'active' : '' ?>"><span
                                    class="material-symbols-outlined">corporate_fare</span><span>Departamentos</span></a><?php endif; ?>
                        <?php if (!$access || $access->can('eventos', 'visualizar')): ?><a href="eventos.php"
                                class="sidebar-item <?= ($current_page == 'eventos') ? 'active' : '' ?>"><span
                                    class="material-symbols-outlined">calendar_month</span><span>Eventos</span></a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- GRUPO: SECRETARIA -->
            <?php if (!$access || $access->can('comunicacao', 'visualizar')): ?>
                <div>
                    <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Secretaria</p>
                    <div class="space-y-1">
                        <a href="carteirinha_digital.php"
                            class="sidebar-item <?= ($current_page == 'carteirinhas') ? 'active' : '' ?>"><span
                                class="material-symbols-outlined">badge</span><span>Carteirinhas</span></a>
                        <a href="whatsapp.php"
                            class="sidebar-item <?= ($current_page == 'whatsapp') ? 'active' : '' ?>"><span
                                class="material-symbols-outlined">chat_bubble</span><span>WhatsApp</span></a>
                        <a href="#"
                            class="sidebar-item <?= ($current_page == 'teste') ? 'active' : '' ?>"><span
                                class="material-symbols-outlined">science</span><span>Teste</span></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- GRUPO: FINANCEIRO -->
            <?php if (!$access || $access->can('financeiro', 'visualizar')): ?>
                <div>
                    <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Tesouraria</p>
                    <div class="space-y-1">
                        <a href="financeiro.php"
                            class="sidebar-item <?= ($current_page == 'financeiro') ? 'active' : '' ?>"><span
                                class="material-symbols-outlined">payments</span><span>Tesouraria</span></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- GRUPO: SISTEMA -->
            <div>
                <p class="px-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-3">Administração</p>
                <div class="space-y-1">
                    <?php if (!$access || $access->can('configuracoes', 'visualizar')): ?><a href="configuracoes.php"
                            class="sidebar-item <?= ($current_page == 'configuracoes') ? 'active' : '' ?>"><span
                                class="material-symbols-outlined">settings</span><span>Configurações</span></a><?php endif; ?>
                    <a href="atualizacoes.php"
                        class="sidebar-item <?= ($current_page == 'atualizacoes') ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">system_update</span>
                        <div class="flex justify-between items-center w-full"><span>Atualizações</span><template
                                x-if="otaAvailable"><span
                                    class="bg-brand text-black text-[8px] font-black px-1.5 py-0.5 rounded-full">NOVO</span></template>
                        </div>
                    </a>
                    <a href="logout.php" class="sidebar-item hover:text-red-400"><span
                            class="material-symbols-outlined">logout</span><span>Sair</span></a>
                </div>
            </div>
        </nav>
    </aside>

    <main class="ml-72 flex-1 flex flex-col min-h-screen relative" x-data="otaManager">
        <header
            class="h-24 flex items-center justify-between px-10 sticky top-0 z-40 bg-darkbg/80 backdrop-blur-md border-b border-darkborder">
            <div>
                <h2 class="text-2xl font-bold text-white tracking-tight"><?= $page_title ?? 'Dashboard' ?></h2>
                <p class="text-xs text-gray-500">Bem-vindo ao SGIM.</p>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-4 text-right">
                    <div>
                        <p class="text-sm font-bold text-white"><?= htmlspecialchars($user_context['nome']) ?></p>
                        <p class="text-[10px] text-brand font-bold uppercase">
                            <?= htmlspecialchars($user_context['cargo']) ?></p>
                    </div>
                    <div
                        class="size-12 rounded-2xl bg-darkcard border border-darkborder flex items-center justify-center text-brand">
                        <span class="material-symbols-outlined text-2xl">person</span></div>
                </div>
            </div>
        </header>
        <div class="p-10">