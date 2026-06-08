<?php
/**
 * SGIM - Cadastro Rápido de Usuário
 * Arquivo standalone - funciona SEM dependência de OTA ou bridges
 * Crie este arquivo DIRETAMENTE na pasta sgim-iade/ via cPanel File Manager
 */

// ── Configuração de Erros ────────────────────────────────────────────────────
error_reporting(0);
ini_set('display_errors', 0);

// ── Sessão ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Autenticação ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

// ── Conexão ao Banco ─────────────────────────────────────────────────────────
$pdo = null;
$dbError = '';

// Tenta caminhos possíveis do banco (resolve o problema de bridges/releases)
$dbPaths = [
    __DIR__ . '/config/database.php',
    __DIR__ . '/config/db.php',
    __DIR__ . '/config/db_config.php',
    dirname(__DIR__) . '/config/database.php',
];

foreach ($dbPaths as $p) {
    if (file_exists($p)) {
        try {
            require_once $p;
            // Testa a conexão
            if (isset($pdo) && $pdo instanceof PDO) {
                $pdo->query("SELECT 1");
                break;
            }
        } catch (Throwable $e) {
            $pdo = null;
        }
    }
}

// Se não achou via require, tenta conexão direta lendo db_config
if (!$pdo) {
    $cfgPaths = [
        __DIR__ . '/config/db_config.php',
        dirname(__DIR__) . '/config/db_config.php',
    ];
    foreach ($cfgPaths as $cfgPath) {
        if (file_exists($cfgPath)) {
            $cfg = file_get_contents($cfgPath);
            // Extrai credenciais por regex
            preg_match("/DB_HOST.*?['\"]([^'\"]+)['\"]/", $cfg, $mh);
            preg_match("/DB_NAME.*?['\"]([^'\"]+)['\"]/", $cfg, $mn);
            preg_match("/DB_USER.*?['\"]([^'\"]+)['\"]/", $cfg, $mu);
            preg_match("/DB_PASS.*?['\"]([^'\"]+)['\"]/", $cfg, $mp);
            if (!empty($mh[1]) && !empty($mn[1])) {
                try {
                    $pdo = new PDO("mysql:host={$mh[1]};dbname={$mn[1]};charset=utf8mb4", $mu[1] ?? '', $mp[1] ?? '');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    break;
                } catch (Throwable $e) {
                    $pdo = null;
                }
            }
        }
    }
}

if (!$pdo) {
    $dbError = 'Não foi possível conectar ao banco de dados. Verifique as configurações.';
}

// ── Dados do usuário logado (para controle de escopo) ────────────────────────
$adminData = null;
$isGlobal = true;
$adminCongId = null;

if ($pdo) {
    try {
        $stA = $pdo->prepare("SELECT u.*, c.escopo as cargo_escopo, u.congregacao_id
                               FROM usuarios u
                               LEFT JOIN cargos c ON u.cargo_id = c.id
                               WHERE u.id = ?");
        $stA->execute([$_SESSION['user_id']]);
        $adminData = $stA->fetch(PDO::FETCH_ASSOC);
        if ($adminData) {
            $isGlobal = ($adminData['cargo_escopo'] !== 'local');
            $adminCongId = $adminData['congregacao_id'];
        }
    } catch (Throwable $e) {
        // Mantém isGlobal = true como fallback seguro
    }
}

// ── Processamento do POST ────────────────────────────────────────────────────
$msg = ''; $msgTipo = ''; $formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $nome          = trim($_POST['nome'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $senha         = $_POST['senha'] ?? '';
    $senhaConfirm  = $_POST['senha_confirm'] ?? '';
    $cargoId       = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    $ativo         = isset($_POST['ativo']) ? 1 : 0;
    $congregacaoId = $isGlobal
        ? (!empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null)
        : $adminCongId;

    // Validações
    if (empty($nome) || empty($email) || empty($senha)) {
        $msg = 'Nome, E-mail e Senha são obrigatórios.'; $msgTipo = 'erro';
    } elseif ($senha !== $senhaConfirm) {
        $msg = 'As senhas não coincidem.'; $msgTipo = 'erro';
    } elseif (strlen($senha) < 6) {
        $msg = 'A senha deve ter no mínimo 6 caracteres.'; $msgTipo = 'erro';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'E-mail inválido.'; $msgTipo = 'erro';
    } else {
        try {
            // Verifica duplicidade
            $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $msg = 'Este e-mail já está cadastrado no sistema.'; $msgTipo = 'erro';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo_id, congregacao_id, ativo, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([$nome, $email, $hash, $cargoId, $congregacaoId, $ativo]);
                $msg = "Usuário \"$nome\" cadastrado com sucesso!"; $msgTipo = 'ok';
            }
        } catch (Throwable $e) {
            $msg = 'Erro ao gravar: ' . $e->getMessage(); $msgTipo = 'erro';
        }
    }

    if ($msgTipo === 'erro') {
        $formData = ['nome' => $nome, 'email' => $email, 'cargo_id' => $cargoId, 'congregacao_id' => $congregacaoId];
    }
}

// ── Dados para os selects ────────────────────────────────────────────────────
$cargos = []; $congregacoes = [];
if ($pdo) {
    try {
        if ($isGlobal) {
            $cargos = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
            $congregacoes = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sc = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' AND escopo = 'local' ORDER BY nome");
            $cargos = $sc->fetchAll(PDO::FETCH_ASSOC);
            $sc2 = $pdo->prepare("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' AND id = ?");
            $sc2->execute([$adminCongId]);
            $congregacoes = $sc2->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {}
}

// ── Versão do sistema ────────────────────────────────────────────────────────
$versao = '1.4.9';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Novo Usuário — SGIM</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                brand: '#FFC107',
                'brand-dark': '#e6ac00',
                darkbg: '#050505',
                darkcard: '#121212',
                darkborder: '#1e1e1e',
            },
            fontFamily: { sans: ['Inter', 'sans-serif'] }
        }
    }
}
</script>
<style>
body { font-family: 'Inter', sans-serif; background: #050505; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
input, select { color-scheme: dark; }
</style>
</head>
<body class="min-h-screen bg-darkbg text-white">

<!-- Barra de navegação rápida -->
<div class="border-b border-white/5 bg-[#0a0a0a]">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-brand">church</span>
            <span class="font-bold text-white">SGIM</span>
            <span class="text-white/20">/</span>
            <span class="text-gray-400 text-sm">Novo Usuário</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="usuarios.php" class="flex items-center gap-1.5 text-sm text-gray-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-base">arrow_back</span>
                Voltar para Usuários
            </a>
        </div>
    </div>
</div>

<div class="max-w-5xl mx-auto px-6 py-10">

    <!-- Cabeçalho -->
    <div class="mb-10">
        <h1 class="text-3xl font-black text-white tracking-tight">Novo Usuário Administrativo</h1>
        <p class="text-sm text-gray-500 mt-1">Cadastre as credenciais e defina os limites de acesso no sistema.</p>
    </div>

    <?php if ($dbError): ?>
    <!-- Erro de conexão -->
    <div class="p-6 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-start gap-4 mb-8">
        <span class="material-symbols-outlined text-2xl mt-0.5">error</span>
        <div>
            <p class="font-bold">Erro de Conexão com o Banco de Dados</p>
            <p class="text-sm mt-1 opacity-80"><?= htmlspecialchars($dbError) ?></p>
            <p class="text-xs mt-3 opacity-60">Caminhos tentados: <?= implode(', ', array_map('htmlspecialchars', $dbPaths)) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($msg): ?>
    <!-- Feedback -->
    <div class="p-4 rounded-xl mb-8 flex items-center gap-3
        <?= $msgTipo === 'ok'
            ? 'bg-green-500/10 border border-green-500/20 text-green-400'
            : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
        <span class="material-symbols-outlined"><?= $msgTipo === 'ok' ? 'check_circle' : 'error' ?></span>
        <p class="font-semibold text-sm"><?= htmlspecialchars($msg) ?></p>
        <?php if ($msgTipo === 'ok'): ?>
        <a href="usuario_novo.php" class="ml-auto text-xs text-green-400 underline">Cadastrar outro</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$dbError && $msgTipo !== 'ok'): ?>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">

        <!-- CARD 1: Dados cadastrais -->
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 space-y-6">
            <h2 class="text-base font-bold text-white border-b border-darkborder pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand text-xl">badge</span>
                Dados Cadastrais
            </h2>

            <!-- Nome -->
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Nome Completo *</label>
                <input type="text" name="nome" required
                       value="<?= htmlspecialchars($formData['nome'] ?? '') ?>"
                       placeholder="Ex: Pastor João Silva"
                       class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-white placeholder-gray-700 focus:ring-2 focus:ring-brand outline-none transition-all text-sm"/>
            </div>

            <!-- E-mail -->
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">E-mail de Login *</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                       placeholder="Ex: pastor@igreja.com"
                       class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-white placeholder-gray-700 focus:ring-2 focus:ring-brand outline-none transition-all text-sm"/>
            </div>

            <!-- Senhas -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Senha *</label>
                    <input type="password" name="senha" required placeholder="Mín. 6 caracteres"
                           class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-white placeholder-gray-700 focus:ring-2 focus:ring-brand outline-none transition-all text-sm"/>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Confirmar Senha *</label>
                    <input type="password" name="senha_confirm" required placeholder="Repita a senha"
                           class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-white placeholder-gray-700 focus:ring-2 focus:ring-brand outline-none transition-all text-sm"/>
                </div>
            </div>
        </div>

        <!-- CARD 2: Vínculo e permissões -->
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 space-y-6">
            <h2 class="text-base font-bold text-white border-b border-darkborder pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand text-xl">rule</span>
                Vínculo e Permissões
            </h2>

            <!-- Cargo -->
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Cargo / Função</label>
                <?php if (empty($cargos)): ?>
                <div class="w-full px-4 py-3 rounded-xl border border-yellow-500/20 bg-yellow-500/5 text-yellow-500 text-sm">
                    ⚠️ Nenhum cargo ativo encontrado. Crie cargos primeiro em <a href="cargos.php" class="underline">Cargos</a>.
                </div>
                <input type="hidden" name="cargo_id" value=""/>
                <?php else: ?>
                <select name="cargo_id"
                        class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-white focus:ring-2 focus:ring-brand outline-none appearance-none text-sm">
                    <option value="">— Nenhum cargo (sem permissões específicas) —</option>
                    <?php foreach ($cargos as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (($formData['cargo_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome']) ?>
                        <span style="color:#aaa"> — <?= strtoupper($c['escopo'] ?? 'global') ?></span>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Congregação -->
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">
                    Congregação Vinculada
                    <?= !$isGlobal ? '<span class="text-yellow-500">(fixo pelo seu escopo)</span>' : '' ?>
                </label>
                <?php if ($isGlobal): ?>
                    <?php if (empty($congregacoes)): ?>
                    <div class="w-full px-4 py-3 rounded-xl border border-yellow-500/20 bg-yellow-500/5 text-yellow-500 text-sm">
                        ⚠️ Nenhuma congregação ativa. <a href="congregacoes.php" class="underline">Criar congregação</a>.
                    </div>
                    <input type="hidden" name="congregacao_id" value=""/>
                    <?php else: ?>
                    <select name="congregacao_id"
                            class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-white focus:ring-2 focus:ring-brand outline-none appearance-none text-sm">
                        <option value="">— Sede / Ministério (acesso global) —</option>
                        <?php foreach ($congregacoes as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= (($formData['congregacao_id'] ?? '') == $co['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                <?php else: ?>
                    <input type="hidden" name="congregacao_id" value="<?= htmlspecialchars($adminCongId) ?>"/>
                    <div class="w-full px-4 py-3 rounded-xl border border-darkborder bg-[#0a0a0a] text-gray-400 text-sm flex items-center justify-between">
                        <span><?= htmlspecialchars($congregacoes[0]['nome'] ?? 'Sua Congregação') ?></span>
                        <span class="text-[10px] text-gray-600 uppercase tracking-wider">Fixo</span>
                    </div>
                <?php endif; ?>
                <p class="text-[10px] text-gray-600 mt-1.5 italic">
                    <?= $isGlobal
                        ? 'Deixe em branco para acesso global (cargos sem restrição de sede).'
                        : 'Seu escopo local restringe o vínculo à sua congregação.' ?>
                </p>
            </div>

            <!-- Toggle Ativo -->
            <div class="flex items-center justify-between p-4 rounded-xl bg-[#0a0a0a] border border-darkborder">
                <div>
                    <p class="text-sm font-bold text-white">Usuário Ativo</p>
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider mt-0.5">Permitir login imediato</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" checked class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                </label>
            </div>

            <!-- Botões -->
            <div class="space-y-3 pt-2">
                <button type="submit"
                        class="w-full py-4 rounded-xl bg-brand hover:bg-brand-dark text-black font-black text-xs uppercase tracking-widest transition-all shadow-lg shadow-brand/10">
                    <span class="material-symbols-outlined text-sm align-middle mr-1">person_add</span>
                    Cadastrar Usuário
                </button>
                <a href="usuarios.php"
                   class="w-full py-3 rounded-xl border border-darkborder text-gray-400 font-bold text-xs uppercase tracking-widest text-center block hover:bg-white/5 transition-all">
                    Cancelar
                </a>
            </div>
        </div>

    </form>
    <?php endif; ?>

    <?php if ($msgTipo === 'ok'): ?>
    <!-- Sucesso completo -->
    <div class="text-center py-20">
        <div class="size-20 rounded-full bg-green-500/10 border border-green-500/20 flex items-center justify-center mx-auto mb-6">
            <span class="material-symbols-outlined text-green-400 text-4xl">check_circle</span>
        </div>
        <h2 class="text-2xl font-black text-white mb-2">Usuário Cadastrado!</h2>
        <p class="text-gray-500 mb-8"><?= htmlspecialchars($msg) ?></p>
        <div class="flex gap-4 justify-center">
            <a href="usuario_novo.php" class="px-6 py-3 rounded-xl bg-brand text-black font-bold text-sm flex items-center gap-2 hover:bg-brand-dark transition-all">
                <span class="material-symbols-outlined text-base">person_add</span>Cadastrar Outro
            </a>
            <a href="usuarios.php" class="px-6 py-3 rounded-xl border border-darkborder text-gray-300 font-bold text-sm flex items-center gap-2 hover:bg-white/5 transition-all">
                <span class="material-symbols-outlined text-base">list</span>Ver Todos os Usuários
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Footer mínimo -->
<div class="border-t border-white/5 mt-16 py-4 text-center text-xs text-gray-700">
    SGIM v<?= $versao ?> — Sistema de Gestão de Igrejas e Membros
</div>

</body>
</html>
