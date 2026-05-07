<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_exception_handler(function($e) {
    file_put_contents(__DIR__ . '/../error_debug.txt', date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n", FILE_APPEND);
    echo "<h1>Erro Crítico</h1><p>" . $e->getMessage() . "</p>";
});

session_start();
require_once '../config/database.php';

// Verificar Autenticação
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_nivel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// 1. Buscar Cliente vinculado ao Usuário (Padronizado via ultra_fix_v3)
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Sincronização forçada ao entrar na dashboard
require_once '../sync_all.php';

// Fallback preventivo (por e-mail) se usuario_id falhar
if (!$cliente && isset($_SESSION['usuario_email'])) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$_SESSION['usuario_email']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$cliente) {
    echo "
    <!DOCTYPE html>
    <html class='dark' lang='pt-BR'>
    <head>
        <meta charset='utf-8'/>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
        <title>Acesso Restrito - SGIM Master</title>
        <script src='https://cdn.tailwindcss.com'></script>
        <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap' rel='stylesheet'/>
        <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'/>
        <style>
            body { font-family: 'Outfit', sans-serif; background-color: #050505; color: #f8fafc; }
            .bg-gold-gradient { background: linear-gradient(135deg, #FFC107 0%, #e6af06 100%); }
        </style>
    </head>
    <body class='min-h-screen flex items-center justify-center p-6'>
        <div class='max-w-md w-full bg-[#0A0A0A] border border-white/5 p-10 rounded-[2.5rem] shadow-2xl text-center relative overflow-hidden'>
            <div class='absolute top-0 left-0 w-full h-1 bg-gold-gradient'></div>
            
            <div class='mb-8 inline-flex items-center justify-center w-20 h-20 bg-yellow-500/10 rounded-3xl border border-yellow-500/20'>
                <i class='fas fa-user-slash text-3xl text-[#FFC107] animate-pulse'></i>
            </div>
            
            <h1 class='text-2xl font-black text-white mb-4 uppercase tracking-tighter'>Perfil não localizado</h1>
            <p class='text-slate-400 text-sm leading-relaxed mb-8'>
                Não conseguimos encontrar os dados do seu perfil de cliente. Isso pode ocorrer se sua conta foi removida ou se houve um erro de sincronização.
            </p>
            
            <div class='flex flex-col gap-3'>
                <a href='../logout.php' class='bg-gold-gradient text-black font-black py-4 rounded-2xl text-xs uppercase tracking-widest hover:scale-[1.02] transition-all shadow-lg shadow-yellow-500/10'>
                    <i class='fas fa-sign-out-alt mr-2'></i> Fazer Logout e Tentar Novamente
                </a>
                <a href='mailto:suporte@exemplo.com' class='text-slate-500 hover:text-white text-[10px] font-bold uppercase tracking-widest transition-colors py-2'>
                    Contatar Suporte Técnico
                </a>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

$cliente_id = $cliente['id'];

// 2. Buscar Licenças de forma Resiliente (Vinculando por Cliente ID, Pedido ID ou E-mail do Usuário Logado)
$sqlLic = "SELECT l.* FROM licencas l 
           WHERE l.cliente_id = ? 
           OR l.pedido_id IN (SELECT id FROM pedidos WHERE cliente_id = ?)
           OR l.cliente_id IN (SELECT id FROM clientes WHERE email = ?)
           ORDER BY l.id DESC";
$stmt = $pdo->prepare($sqlLic);
$stmt->execute([$cliente_id, $cliente_id, $cliente['email']]);
$licencas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Normalizar Licenças (Garantir que os campos existam para o HTML)
foreach($licencas as &$l) {
    if(!isset($l['created_at'])) $l['created_at'] = $l['data_criacao'] ?? $l['criado_em'] ?? date('Y-m-d H:i:s');
}
unset($l);

// 3. Buscar Histórico de Pedidos
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY id DESC");
$stmt->execute([$cliente_id]);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Buscar Bônus de Indicação (Resiliência)
$total_indicacoes = 0;
$bonus_acumulado = 0;
$referral_code = $cliente['referral_code'] ?? '';

try {
    $hasIndicacoesTable = $pdo->query("SHOW TABLES LIKE 'indicacoes'")->fetch();
    if ($hasIndicacoesTable) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM indicacoes WHERE referente_id = ? AND status = 'convertido'");
        $stmt->execute([$cliente_id]);
        $total_indicacoes = $stmt->fetchColumn();
    }
    $bonus_acumulado = (float)($cliente['bonus_acumulado'] ?? 0);
} catch (Exception $e) { /* Ignora se falhar */ }

$hasReferralCode = !empty($referral_code);
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM Master - Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "accent-gold": "#FFC107",
                        "background-dark": "#050505",
                        "surface-dark": "#0A0A0A",
                        "border-dark": "#1A1A1A",
                    },
                    fontFamily: { sans: ["Outfit", "sans-serif"] },
                },
            },
        }
    </script>
    <style>
        .glass-card { background: rgba(10, 10, 10, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 193, 7, 0.08); border-radius: 1.5rem; }
        .gold-glow:hover { box-shadow: 0 0 30px rgba(255, 193, 7, 0.1); border-color: rgba(255, 193, 7, 0.4); }
        .btn-premium { background: linear-gradient(135deg, #FFC107 0%, #e6af06 100%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(255, 193, 7, 0.2); }
        body { background-color: #050505; color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #1A1A1A; border-radius: 10px; }
    </style>
</head>
<body class="font-sans antialiased">
    <!-- Top Header -->
    <header class="border-b border-border-dark bg-background-dark/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="bg-accent-gold p-1.5 rounded-lg">
                    <i class="fas fa-shield-halved text-black text-sm"></i>
                </div>
                <span class="text-lg font-black tracking-tighter text-white">SGIM <span class="text-accent-gold uppercase">Master</span></span>
            </div>
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex flex-col text-right">
                    <span class="text-xs font-bold text-white"><?= explode(' ', $cliente['nome'])[0] ?></span>
                    <span class="text-[9px] text-slate-500 uppercase font-black tracking-widest">Painel Cliente</span>
                </div>
                <a href="../logout.php" class="bg-surface-dark border border-border-dark p-2 rounded-xl text-slate-400 hover:text-white transition-all">
                    <i class="fas fa-power-off text-sm"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <?php if (isset($_GET['success']) || !empty($licencas)): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-500 p-4 rounded-2xl mb-8 flex items-center">
                <i class="fas fa-check-circle mr-4 text-xl"></i>
                <div>
                    <h4 class="font-bold text-sm text-white">Sua Licença está Liberada!</h4>
                    <p class="text-[10px] opacity-80">Parabéns! O seu acesso vitalício ao SGIM Master foi ativado.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-12 gap-6">
            <!-- Left Column: Licenses (Main) -->
            <div class="lg:col-span-8 space-y-6">
                <div class="flex items-center justify-between mb-2 px-2">
                    <h2 class="text-xl font-black flex items-center gap-3"><i class="fas fa-key text-accent-gold"></i> Minhas Licenças</h2>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?= count($licencas) ?> Ativas</span>
                </div>

                <?php if (empty($licencas)): ?>
                    <div class="glass-card py-16 text-center">
                        <i class="fas fa-key text-3xl text-slate-800 mb-4"></i>
                        <h3 class="text-lg font-bold mb-1 uppercase text-white">Sem licenças</h3>
                        <p class="text-slate-500 text-xs mb-6">Você ainda não possui licenças ativas vinculadas.</p>
                        <a href="../venda.php" class="btn-premium text-black px-8 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest">Adquirir Agora</a>
                    </div>
                <?php else: ?>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <?php foreach ($licencas as $lic): ?>
                        <div class="glass-card p-6 gold-glow transition-all group overflow-hidden relative">
                            <div class="absolute top-0 right-0 p-4 opacity-10">
                                <i class="fas fa-shield text-5xl"></i>
                            </div>
                            <div class="flex justify-between items-center mb-6">
                                <span class="bg-green-500/10 text-green-500 px-2 py-0.5 rounded-full text-[9px] font-black tracking-widest uppercase border border-green-500/20"><?= $lic['status'] ?></span>
                                <span class="text-[9px] text-slate-500 font-bold uppercase"><?= date('d/m/Y', strtotime($lic['created_at'])) ?></span>
                            </div>
                            <div class="mb-6 text-white">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-2">Chave de Ativação</span>
                                <div class="bg-background-dark/80 p-4 rounded-xl border border-border-dark font-mono text-xs text-accent-gold break-all">
                                    <?php 
                                        $displayKey = $lic['chave_licenca'];
                                        if (strpos($displayKey, 'SGIM-') !== 0) $displayKey = 'SGIM-' . $displayKey;
                                        echo $displayKey;
                                    ?>
                                </div>
                            </div>
                            
                            <!-- BOTÃO DE INSTALAÇÃO DO SISTEMA (Setup Direto v2) -->
                            <div class="mt-4 border-t border-border-dark pt-4 space-y-3">
                                <div class="flex gap-2">
                                    <button data-copy="<?php echo (strpos($lic['chave_licenca'], 'SGIM-') === 0) ? $lic['chave_licenca'] : 'SGIM-' . $lic['chave_licenca']; ?>" class="btn-copy-license flex-1 bg-surface-dark hover:bg-border-dark border border-border-dark text-white py-3 rounded-xl font-bold text-[9px] uppercase tracking-widest transition-all gap-2 flex items-center justify-center">
                                        <i class="fas fa-copy"></i> Copiar Código
                                    </button>
                                    
                                    <?php if($lic['dominio'] === 'venda_automática' || empty($lic['dominio'])): ?>
                                        <button onclick="openInstallModal(<?= $lic['pedido_id'] ?>, '<?= $lic['chave_licenca'] ?>')" class="flex-1 btn-premium text-black py-3 rounded-xl font-black text-[9px] uppercase tracking-widest flex items-center justify-center gap-2">
                                            <i class="fas fa-rocket"></i> Instalar Agora
                                        </button>
                                    <?php else: ?>
                                        <?php 
                                            $clean_dom = preg_replace('/^https?:\/\//', '', $lic['dominio']);
                                            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
                                        ?>
                                            <a href="<?= $protocol . $clean_dom ?>/login.php" target="_blank" class="flex-1 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-blue-500 py-3 rounded-xl font-bold text-[9px] uppercase tracking-widest flex items-center justify-center gap-2 transition-all text-center">
                                                <i class="fas fa-sign-in-alt"></i> Login
                                            </a>
                                            <a href="<?= $protocol . $clean_dom ?>/setup.php" target="_blank" class="flex-1 bg-accent-gold/10 hover:bg-accent-gold/20 border border-accent-gold/30 text-accent-gold py-3 rounded-xl font-bold text-[9px] uppercase tracking-widest flex items-center justify-center gap-2 transition-all text-center">
                                                <i class="fas fa-tools"></i> Setup
                                            </a>
                                    <?php endif; ?>
                                </div>
                                <p class="text-center text-[9px] text-slate-500">
                                    <?php if($lic['dominio'] === 'venda_automática' || empty($lic['dominio'])): ?>
                                        Clique em <b>Instalar Agora</b> para configurar seu sistema.
                                    <?php else: ?>
                                        Vinculada ao domínio: <b class="text-slate-300"><?= $lic['dominio'] ?></b>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Histórico (Compacto) -->
                <div class="glass-card mt-8">
                    <div class="p-5 border-b border-border-dark">
                        <h2 class="font-bold text-sm flex items-center gap-3 uppercase text-white"><i class="fas fa-history text-accent-gold text-xs"></i> Histórico de Compras</h2>
                    </div>
                    <?php if (empty($vendas)): ?>
                        <div class="p-10 text-center text-slate-700 text-[10px] font-bold uppercase">Nenhuma compra registrada</div>
                    <?php else: ?>
                        <div class="overflow-x-auto text-white">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-border-dark font-black uppercase text-[10px] tracking-widest text-slate-500">
                                    <tr>
                                        <th class="p-4">ID</th>
                                        <th class="p-4">Data</th>
                                        <th class="p-4">Valor</th>
                                        <th class="p-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-dark">
                                    <?php foreach ($vendas as $v): ?>
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="p-4 font-bold">#<?= $v['id'] ?></td>
                                        <td class="p-4 text-slate-400"><?= @date('d/m/Y', strtotime($v['data_venda'] ?? $v['created_at'] ?? 'now')) ?></td>
                                        <td class="p-4 font-black">R$ <?= number_format($v['valor'], 2, ',', '.') ?></td>
                                        <td class="p-4">
                                            <span class="text-[9px] font-black uppercase tracking-widest <?= in_array(strtoupper($v['status']), ['APPROVED', 'APROVADO', 'PAGO']) ? 'text-green-500' : 'text-accent-gold' ?>">
                                                <?= $v['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Downloads Card -->
                <div class="glass-card p-6 bg-gradient-to-br from-surface-dark to-background-dark border-accent-gold/20">
                    <h3 class="font-black text-sm mb-2 flex items-center gap-3 uppercase text-white"><i class="fas fa-cloud-download-alt text-accent-gold"></i> Download Center</h3>
                    <p class="text-[10px] text-slate-400 mb-6 font-medium italic">Siga as instruções do manual para realizar a instalação no seu domínio.</p>
                    
                    <div class="space-y-3">
                        <a href="download.php?file=system" class="w-full flex items-center justify-between p-4 bg-background-dark border border-border-dark hover:border-accent-gold/40 rounded-2xl transition-all group">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-accent-gold/10 rounded-xl flex items-center justify-center text-accent-gold">
                                    <i class="fas fa-file-zipper"></i>
                                </div>
                                <div class="flex flex-col text-white">
                                    <span class="text-xs font-bold font-white">Sistema SGIM</span>
                                    <span class="text-[9px] text-slate-500 uppercase">Versão Oficial .ZIP</span>
                                </div>
                            </div>
                            <i class="fas fa-download text-[10px] text-slate-700 group-hover:text-accent-gold transition-all"></i>
                        </a>
                        <a href="download.php?file=manual" class="w-full flex items-center justify-between p-4 bg-background-dark border border-border-dark hover:border-accent-gold/40 rounded-2xl transition-all group">
                            <div class="flex items-center gap-3 text-white">
                                <div class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-500">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold">Manual Completo</span>
                                    <span class="text-[9px] text-slate-500 uppercase">Instruções em PDF</span>
                                </div>
                            </div>
                            <i class="fas fa-download text-[10px] text-slate-700 group-hover:text-blue-500 transition-all"></i>
                        </a>
                    </div>
                </div>

                <!-- Affiliate Card (Sidebar) -->
                <?php if ($hasReferralCode): ?>
                <div class="glass-card p-6 border-accent-gold/10">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-black text-sm uppercase flex items-center gap-2"><i class="fas fa-users text-accent-gold"></i> Afiliado</h3>
                        <span class="text-[9px] font-black text-accent-gold animate-pulse">NOVO</span>
                    </div>
                    <div class="p-4 bg-background-dark/80 rounded-2xl border border-border-dark mb-4">
                        <span class="text-[9px] font-black text-slate-500 uppercase block mb-2">Seu Link de Convite</span>
                        <div class="flex items-center justify-between gap-2 overflow-hidden">
                            <span class="text-[10px] font-mono text-accent-gold truncate w-full" id="refText"><?= rtrim(SITE_URL, '/') ?>/?ref=<?= $referral_code ?></span>
                            <button id="copyRef" class="text-slate-500 hover:text-white"><i class="fas fa-copy text-xs"></i></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="bg-background-dark/50 p-3 rounded-xl">
                            <span class="text-[8px] font-black text-slate-600 uppercase block">Vendas</span>
                            <span class="font-black text-sm text-white"><?= $total_indicacoes ?></span>
                        </div>
                        <div class="bg-background-dark/50 p-3 rounded-xl">
                            <span class="text-[8px] font-black text-slate-600 uppercase block">Ganhos</span>
                            <span class="font-black text-sm text-accent-gold">R$ <?= number_format($bonus_acumulado, 2, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Support Card -->
                <div class="bg-accent-gold p-8 rounded-[1.5rem] text-background-dark shadow-xl relative overflow-hidden group">
                    <div class="absolute -bottom-4 -right-4 opacity-10 group-hover:scale-110 transition-transform">
                        <i class="fas fa-headset text-8xl"></i>
                    </div>
                    <h3 class="text-xl font-black tracking-tight mb-2">Suporte <span class="opacity-50">VIP</span></h3>
                    <p class="text-[10px] font-bold leading-relaxed mb-6">Precisa de ajuda com a ativação? Nossa equipe está pronta no WhatsApp.</p>
                    <a href="https://wa.me/55000000000" target="_blank" class="bg-background-dark text-white w-full py-3.5 rounded-xl font-black text-[10px] uppercase tracking-widest text-center block hover:scale-[1.02] transition-all">Acessar Suporte</a>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentPedidoId = null;
        let currentKey = null;

        function openInstallModal(pedidoId, key) {
            currentPedidoId = pedidoId;
            currentKey = key;
            document.getElementById('display-key').innerText = key;
            document.getElementById('modal-install').style.display = 'flex';
        }

        async function bindAndDownload() {
            const dominio = document.getElementById('input-dominio').value.trim();
            if (!dominio) {
                Swal.fire({ icon: 'error', title: 'Domínio Obrigatório', text: 'Informe o domínio de instalação.', background: '#0A0A0A', color: '#fff' });
                return;
            }

            const btn = document.getElementById('btn-bind');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Vinculando...';

            try {
                // 1. Vincular Domínio via API
                const res = await fetch('../api/fix_domain.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pedido_id: currentPedidoId, dominio: dominio })
                });
                const data = await res.json();

                if (data.success) {
                    // 2. Iniciar Download
                    window.location.href = 'download.php?file=system';

                    // 3. Atualizar Modal para Etapa de Upload (Evita 404 precoce)
                    document.getElementById('setup-link').href = data.setup_url;
                    document.getElementById('step-1-form').style.display = 'none';
                    document.getElementById('step-2-upload').style.display = 'block';
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Vínculo Realizado!',
                        text: 'O download do sistema iniciou. Agora siga as instruções para o upload.',
                        background: '#0A0A0A',
                        color: '#fff',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Erro', text: data.message, background: '#0A0A0A', color: '#fff' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-link"></i> Vincular e Baixar Sistema';
                }
            } catch (e) {
                console.error(e);
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha na conexão com o servidor.', background: '#0A0A0A', color: '#fff' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-link"></i> Vincular e Baixar Sistema';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Cópia de Licença
            document.querySelectorAll('.btn-copy-license').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const code = e.currentTarget.getAttribute('data-copy');
                    navigator.clipboard.writeText(code);
                    Swal.fire({
                        icon: 'success',
                        title: 'Copiado!',
                        text: 'Chave de licença copiada para a área de transferência.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        background: '#0A0A0A',
                        color: '#fff'
                    });
                });
            });

            // Cópia de Referência
            const copyRef = document.getElementById('copyRef');
            if(copyRef){
                copyRef.addEventListener('click', () => {
                    const text = document.getElementById('refText').innerText;
                    navigator.clipboard.writeText(text);
                    Swal.fire({
                        icon: 'success',
                        title: 'Link Copiado!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        background: '#0A0A0A',
                        color: '#fff'
                    });
                });
            }
        });
    </script>

<!-- MODAL DE INSTALAÇÃO (v2 Interativo) -->
<div id="modal-install" style="display:none;" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass-card max-w-lg w-full p-8 rounded-2xl relative shadow-2xl border border-border-dark">
        <button onclick="document.getElementById('modal-install').style.display='none'" class="absolute top-4 right-4 text-slate-500 hover:text-white transition-all">
            <i class="fas fa-times text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-6">
            <div class="p-3 bg-accent-gold/10 rounded-xl">
                <i class="fas fa-rocket text-accent-gold text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-white">Instalar o SGIM Master</h3>
                <p class="text-[10px] text-slate-500 uppercase tracking-widest">Vincule seu domínio e comece agora</p>
            </div>
        </div>

        <div id="step-1-form" class="space-y-6">
            <!-- Chave de Licença -->
            <div class="bg-background-dark/80 p-4 rounded-xl border border-border-dark">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Sua Chave de Licença</span>
                <div class="flex items-center justify-between">
                    <span id="display-key" class="font-mono text-sm text-accent-gold font-bold">---</span>
                    <button onclick="navigator.clipboard.writeText(currentKey); Swal.fire({toast:true, position:'top-end', icon:'success', title:'Copiado!', showConfirmButton:false, timer:1500, background:'#0A0A0A', color:'#fff'})" class="text-slate-500 hover:text-white">
                        <i class="fas fa-copy text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Entrada de Domínio -->
            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Domínio de Instalação (Ex: igreja.com.br)</label>
                <input type="text" id="input-dominio" placeholder="ex: meusite.com.br" class="w-full bg-background-dark/80 border border-border-dark focus:border-accent-gold rounded-xl px-4 py-3 text-white outline-none transition-all font-medium text-sm">
                <p class="text-[9px] text-slate-500 mt-2 font-medium italic">O download do sistema iniciará após o vínculo.</p>
            </div>

            <!-- Botão de Ação -->
            <button id="btn-bind" onclick="bindAndDownload()" class="w-full btn-premium text-black font-black py-4 rounded-2xl uppercase tracking-widest hover:scale-[1.02] transition-all shadow-lg shadow-accent-gold/10 flex items-center justify-center gap-2 text-xs">
                <i class="fas fa-link"></i> Vincular e Baixar Sistema
            </button>
        </div>

        <!-- ETAPA 2: Upload Manual (Previne 404) -->
        <div id="step-2-upload" style="display:none;" class="space-y-6 animate-in fade-in duration-500">
            <div class="bg-yellow-500/10 border border-yellow-500/20 p-5 rounded-2xl">
                <h4 class="text-white font-bold text-sm mb-2 flex items-center gap-2">
                    <i class="fas fa-cloud-upload-alt text-accent-gold"></i> Quase Pronto!
                </h4>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                    O arquivo <b>sgim_master.zip</b> foi baixado. Siga estes passos finais:
                </p>
            </div>

            <ol class="space-y-4">
                <li class="flex gap-4 items-start">
                    <span class="w-6 h-6 rounded-full bg-accent-gold text-black font-black text-[10px] flex items-center justify-center flex-shrink-0">1</span>
                    <p class="text-slate-400 text-[11px]">Suba o arquivo para a pasta principal do seu domínio via FTP ou cPanel.</p>
                </li>
                <li class="flex gap-4 items-start">
                    <span class="w-6 h-6 rounded-full bg-accent-gold text-black font-black text-[10px] flex items-center justify-center flex-shrink-0">2</span>
                    <p class="text-slate-400 text-[11px]">Extraia o conteúdo do ZIP na raiz do servidor.</p>
                </li>
            </ol>

            <div class="pt-4 flex flex-col gap-3">
                <a id="setup-link" href="#" target="_blank" class="w-full bg-accent-gold hover:bg-yellow-500 text-black py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                    <i class="fas fa-external-link-alt"></i> Já subi os arquivos, Abrir Setup
                </a>
                <button onclick="location.reload()" class="text-slate-500 hover:text-white text-[10px] uppercase font-bold tracking-widest py-2">
                    Fechar e Voltar ao Painel
                </button>
            </div>
            
            <p class="text-[9px] text-slate-500 text-center italic border-t border-border-dark pt-4">
                <i class="fas fa-exclamation-triangle mr-1 text-yellow-500/50"></i> Se você abrir o setup antes de subir os arquivos, verá um erro 404 da HostGator.
            </p>
        </div>
    </div>
</div>

</body>
</html>
