<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; font-weight:bold;'>Acesso Negado: Sessão de administrador necessária. Realize o login no painel primeiro.</div>");
}

require_once 'config/database.php';
require_once 'src/autoload.php';

// 🛡️ Inicializa o AccessManager para proteção de rota
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada
if ($access && !$access->can('configuracoes', 'visualizar')) {
    die("<div style='color:red; font-family:sans-serif; padding:20px; font-weight:bold;'>Acesso Negado: Você não tem permissão para gerenciar configurações do sistema.</div>");
}

$output = '';
$action_msg = '';

// Ação de Forçar Reset e Pull via PHP no cPanel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_force_update'])) {
    try {
        $cmd_reset = shell_exec('git reset --hard origin/main 2>&1');
        $cmd_pull = shell_exec('git pull origin main 2>&1');
        
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        $action_msg = "<div style='background:rgba(46,204,113,0.1); border:1px solid #2ecc71; color:#2ecc71; padding:15px; rounded:10px; margin-bottom:20px; font-size:14px; font-family:sans-serif;'>
            <b>✅ COMANDO DE ATUALIZAÇÃO FORÇADA EXECUTADO!</b><br>
            <br>
            <b>Status do Reset:</b><br><pre style='font-family:monospace; font-size:12px; color:#eaeaea; background:#000; padding:10px; border-radius:5px;'>$cmd_reset</pre>
            <b>Status do Pull:</b><br><pre style='font-family:monospace; font-size:12px; color:#eaeaea; background:#000; padding:10px; border-radius:5px;'>$cmd_pull</pre>
            <b>OPCache:</b> Limpeza de cache de compilador RAM executada com sucesso!
        </div>";
    } catch (Exception $e) {
        $action_msg = "<div style='background:rgba(231,76,60,0.1); border:1px solid #e74c3c; color:#e74c3c; padding:15px; rounded:10px; margin-bottom:20px; font-size:14px; font-family:sans-serif;'>
            ❌ Erro ao tentar atualizar via terminal PHP: " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }
}

// Limpeza de cache OPcache (sempre roda ao acessar)
$opcache_status = 'Não disponível';
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        $opcache_status = '✅ Resetado com sucesso!';
    } else {
        $opcache_status = '⚠️ OPCache ativo, mas falhou ao resetar';
    }
}

// Captura de status do Git
$git_status = shell_exec('git status 2>&1') ?: 'Comando git status indisponível ou desabilitado via PHP (shell_exec).';
$git_log = shell_exec('git log -n 3 --oneline 2>&1') ?: 'Comando git log indisponível.';
$git_version = shell_exec('git --version 2>&1') ?: 'Git não instalado.';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM - Diagnóstico de Sincronização Git</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700&family=Outfit:wght@400;600;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #050505; color: #eaeaea; }
        h1, h2, h3 { font-family: 'Space Grotesk', sans-serif; }
        .terminal { background: #0c0c0c; border: 1px solid #1a1a1a; font-family: monospace; }
    </style>
</head>
<body class="min-h-screen py-10 px-4 flex justify-center">
    <div class="w-full max-w-4xl space-y-8">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-white/10 pb-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-yellow-500/10 rounded-2xl border border-yellow-500/20 text-yellow-500">
                    <span class="material-symbols-outlined text-3xl">terminal</span>
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white uppercase">Diagnóstico Git & Cache cPanel</h1>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Status de Produção e Sincronização em Tempo Real</p>
                </div>
            </div>
            <a href="dashboard.php" class="px-5 py-2.5 bg-white/5 border border-white/10 hover:bg-white/10 rounded-xl text-xs font-bold uppercase tracking-widest text-gray-300 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                Painel
            </a>
        </div>

        <?= $action_msg ?>

        <!-- Grid de Status Rápido -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#0c0c0c] border border-white/5 p-6 rounded-2xl space-y-3">
                <h3 class="text-xs font-black text-yellow-500 uppercase tracking-widest">Compilador PHP (RAM)</h3>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">OPCache State:</span>
                    <span class="text-sm font-bold text-white"><?= $opcache_status ?></span>
                </div>
            </div>
            <div class="bg-[#0c0c0c] border border-white/5 p-6 rounded-2xl space-y-3">
                <h3 class="text-xs font-black text-yellow-500 uppercase tracking-widest">Motor Git</h3>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">Versão Instalada:</span>
                    <span class="text-sm font-bold text-white font-mono"><?= trim($git_version) ?></span>
                </div>
            </div>
        </div>

        <!-- Terminal Output: Git Status -->
        <div class="space-y-2">
            <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest">Saída do Comando: git status</h3>
            <pre class="terminal p-6 rounded-2xl text-xs text-green-400 leading-relaxed overflow-x-auto select-all"><?= htmlspecialchars($git_status) ?></pre>
        </div>

        <!-- Terminal Output: Git History -->
        <div class="space-y-2">
            <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest">Últimos 3 Commits em Produção</h3>
            <pre class="terminal p-6 rounded-2xl text-xs text-yellow-300 leading-relaxed overflow-x-auto"><?= htmlspecialchars($git_log) ?></pre>
        </div>

        <!-- Forçar Atualização -->
        <div class="bg-yellow-500/5 border border-yellow-500/10 p-8 rounded-3xl space-y-6">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-yellow-500">warning</span>
                    Forçar Sincronização e Limpeza
                </h2>
                <p class="text-sm text-gray-400 mt-2 leading-relaxed">
                    Se o Git do cPanel estiver travado por arquivos modificados localmente (conflito de merge), clique no botão abaixo para rodar um <code>git reset --hard</code> e <code>git pull</code> diretamente no servidor. Isso forçará a limpeza das travas locais e atualizará os arquivos para a versão mais recente em disco.
                </p>
            </div>
            
            <form method="POST" onsubmit="return confirm('ATENÇÃO: Esta ação substituirá quaisquer edições diretas feitas nos arquivos do cPanel para aplicar o código do repositório remoto. Deseja prosseguir?')">
                <input type="hidden" name="action_force_update" value="1"/>
                <button type="submit" class="px-8 py-4 bg-yellow-500 hover:bg-yellow-600 text-black font-black text-xs uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-yellow-500/10 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm font-bold">sync</span>
                    Limpar Conflitos e Atualizar Servidor
                </button>
            </form>
        </div>

    </div>
</body>
</html>
