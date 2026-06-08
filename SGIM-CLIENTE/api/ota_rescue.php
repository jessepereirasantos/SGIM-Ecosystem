<?php
/**
 * SGIM RESCUE - Cria usuario_novo.php no release atual
 * Acesse: /sgim-iade/api/ota_rescue.php
 * Remove este arquivo após uso bem-sucedido.
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

// Segurança: apenas admin logado
if (!isset($_SESSION['user_id'])) {
    die('<h2 style="color:red">Acesso negado. Faça login primeiro.</h2>');
}

// Detecta o diretório raiz atual (releases/current ou raiz direta)
$baseDir = realpath(__DIR__ . '/../');
if (!$baseDir) {
    die('<h2 style="color:red">Erro ao detectar diretório base.</h2>');
}

// Conteúdo completo e autocontido do usuario_novo.php
$conteudo = <<<'PHPFILE'
<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

if ($access && !$access->can('usuarios', 'gerenciar')) {
    echo "<script>alert('Acesso Negado.'); window.location.href='usuarios.php';</script>"; exit;
}

$mensagem = ''; $erro = false;
$nome = $email = $cargo_id = $congregacao_id = ''; $ativo = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha_confirm = $_POST['senha_confirm'] ?? '';
    $cargo_id = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (!$access->isGlobal()) {
        $congregacao_id = $access->getCongregacaoId();
        if ($cargo_id) {
            $st = $pdo->prepare("SELECT escopo FROM cargos WHERE id = ?");
            $st->execute([$cargo_id]);
            $esc = $st->fetchColumn();
            if ($esc !== 'local') { $erro = true; $mensagem = 'Cargo global não permitido para escopo local.'; }
        }
    } else {
        $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
    }

    if (!$erro) {
        if (empty($nome) || empty($email) || empty($senha)) {
            $erro = true; $mensagem = 'Nome, E-mail e Senha são obrigatórios.';
        } elseif ($senha !== $senha_confirm) {
            $erro = true; $mensagem = 'As senhas não coincidem.';
        } elseif (strlen($senha) < 6) {
            $erro = true; $mensagem = 'Senha deve ter pelo menos 6 caracteres.';
        } else {
            try {
                $chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    $erro = true; $mensagem = 'E-mail já cadastrado.';
                } else {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo_id, congregacao_id, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins->execute([$nome, $email, $hash, $cargo_id, $congregacao_id, $ativo]);
                    header("Location: usuarios.php?sucesso=1"); exit;
                }
            } catch (Exception $e) {
                $erro = true; $mensagem = 'Erro: ' . $e->getMessage();
            }
        }
    }
}

if ($access->isGlobal()) {
    $cargos = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $congregacoes = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $st2 = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' AND escopo = 'local' ORDER BY nome ASC");
    $cargos = $st2->fetchAll(PDO::FETCH_ASSOC);
    $st3 = $pdo->prepare("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' AND id = ?");
    $st3->execute([$access->getCongregacaoId()]);
    $congregacoes = $st3->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'SGIM - Novo Usuário'; $current_page = 'usuarios';
require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-white tracking-tight">Novo Usuário Administrativo</h2>
        <p class="text-sm text-gray-500 mt-1">Cadastre as credenciais e defina os limites de acesso.</p>
    </div>
    <?php if ($mensagem): ?>
    <div class="p-4 rounded-xl <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
        <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
        <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
    </div>
    <?php endif; ?>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl space-y-6">
            <h3 class="text-lg font-bold text-white border-b border-darkborder pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">badge</span>Dados Cadastrais
            </h3>
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Nome Completo</label>
                <input name="nome" value="<?= htmlspecialchars($nome) ?>" required
                       class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none"
                       placeholder="Ex: Pastor João Silva" type="text"/>
            </div>
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">E-mail de Login</label>
                <input name="email" value="<?= htmlspecialchars($email) ?>" required
                       class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none"
                       placeholder="Ex: pastorjoao@igreja.com" type="email"/>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Senha</label>
                    <input name="senha" required class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none" placeholder="Mín. 6 chars" type="password"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Confirmar Senha</label>
                    <input name="senha_confirm" required class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none" placeholder="Mín. 6 chars" type="password"/>
                </div>
            </div>
        </div>
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl space-y-6">
            <h3 class="text-lg font-bold text-white border-b border-darkborder pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">rule</span>Vínculo e Atribuição
            </h3>
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cargo / Função</label>
                <select name="cargo_id" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                    <option value="">Nenhum cargo atribuído</option>
                    <?php foreach ($cargos as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($cargo_id == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['escopo']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Congregação Vinculada</label>
                <?php if ($access->isGlobal()): ?>
                <select name="congregacao_id" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                    <option value="">Sede / Global</option>
                    <?php foreach ($congregacoes as $co): ?>
                    <option value="<?= $co['id'] ?>" <?= ($congregacao_id == $co['id']) ? 'selected' : '' ?>><?= htmlspecialchars($co['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="hidden" name="congregacao_id" value="<?= $access->getCongregacaoId() ?>"/>
                <div class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-gray-400 text-sm"><?= htmlspecialchars($congregacoes[0]['nome'] ?? 'Sua Congregação') ?> (fixo)</div>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between p-4 rounded-xl bg-darkbg border border-darkborder">
                <div>
                    <p class="text-sm font-bold text-white">Usuário Ativo</p>
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider">Permitir login no sistema</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" <?= $ativo ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                </label>
            </div>
            <div class="pt-4 flex flex-col gap-4">
                <button type="submit" class="w-full py-4 rounded-xl bg-brand hover:bg-brand-dark text-black font-black shadow-xl shadow-brand/10 transition-all uppercase tracking-widest text-xs">Cadastrar Usuário</button>
                <a href="usuarios.php" class="w-full py-4 rounded-xl border border-darkborder text-gray-400 font-bold text-center hover:bg-white/5 transition-all uppercase tracking-widest text-xs">Cancelar</a>
            </div>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
PHPFILE;

// Caminhos alvos onde o arquivo precisa existir
$alvos = [
    $baseDir . '/usuario_novo.php',
];

// Tenta também criar no releases/current se existir como symlink real
$releasesPath = realpath($baseDir . '/releases/current');
if ($releasesPath && is_dir($releasesPath)) {
    $alvos[] = $releasesPath . '/usuario_novo.php';
}

$resultados = [];
foreach ($alvos as $alvo) {
    $ok = file_put_contents($alvo, $conteudo);
    $resultados[] = [
        'arquivo' => $alvo,
        'resultado' => $ok !== false ? '✅ CRIADO (' . $ok . ' bytes)' : '❌ FALHOU (sem permissão?)'
    ];
}

// Verifica se conseguiu criar ao menos um
$algumOk = false;
foreach ($resultados as $r) {
    if (strpos($r['resultado'], '✅') !== false) $algumOk = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>SGIM Rescue - Criar usuario_novo.php</title>
<style>
    body { font-family: Arial, sans-serif; background: #0a0a0a; color: #eee; padding: 40px; }
    h1 { color: #FFC107; } h2 { color: #eee; }
    .ok { color: #22c55e; } .fail { color: #ef4444; }
    .box { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 20px; margin: 20px 0; }
    a { color: #FFC107; font-weight: bold; text-decoration: none; padding: 10px 20px; background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); border-radius: 6px; display: inline-block; margin-top: 10px; }
    a:hover { background: rgba(255,193,7,0.2); }
</style>
</head>
<body>
<h1>🔧 SGIM Rescue - Criação de usuario_novo.php</h1>

<div class="box">
    <h2>Resultado:</h2>
    <?php foreach ($resultados as $r): ?>
    <p class="<?= strpos($r['resultado'], '✅') !== false ? 'ok' : 'fail' ?>">
        <?= $r['resultado'] ?><br>
        <small style="color:#888"><?= htmlspecialchars($r['arquivo']) ?></small>
    </p>
    <?php endforeach; ?>
</div>

<?php if ($algumOk): ?>
<div class="box">
    <p class="ok">✅ <strong>Arquivo criado com sucesso!</strong> Agora você pode:</p>
    <a href="../usuario_novo.php">Testar → Novo Usuário</a>
    <a href="../usuarios.php">Voltar à lista de usuários</a>
</div>
<?php else: ?>
<div class="box">
    <p class="fail">❌ <strong>Falha ao criar o arquivo.</strong> O servidor não tem permissão de escrita.</p>
    <p>Diretório base detectado: <code><?= htmlspecialchars($baseDir) ?></code></p>
    <p>Entre em contato com o suporte para criar o arquivo manualmente via cPanel.</p>
</div>
<?php endif; ?>

<div class="box" style="margin-top: 30px;">
    <p style="color:#888; font-size: 12px;">⚠️ <strong>Apague este arquivo após uso:</strong><br>
    <code><?= htmlspecialchars(__FILE__) ?></code></p>
</div>
</body>
</html>
