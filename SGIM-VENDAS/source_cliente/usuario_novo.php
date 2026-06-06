<?php
// AUTO-PONTE: Se existir uma versão mais nova ativa pelo OTA, desvia para ela
$bridge = __DIR__ . '/releases/current/' . basename(__FILE__);
if (file_exists($bridge) && strpos(__DIR__, 'releases') === false) {
    require_once $bridge;
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'src/autoload.php';

// 🛡️ Inicializa o AccessManager para proteção de rota antecipada
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de gravação (módulo: usuarios, acao: gerenciar)
if ($access && !$access->can('usuarios', 'gerenciar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para cadastrar novos usuários.'); window.location.href='usuarios.php';</script>";
    exit;
}

$mensagem = '';
$erro = false;

$nome = '';
$email = '';
$cargo_id = '';
$congregacao_id = '';
$ativo = 1;

// PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha_confirm = $_POST['senha_confirm'] ?? '';
    $cargo_id = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = true;
        $mensagem = "Revisão necessária: Nome, E-mail e Senha são campos obrigatórios.";
    } elseif ($senha !== $senha_confirm) {
        $erro = true;
        $mensagem = "Erro: A senha e a confirmação de senha não coincidem.";
    } elseif (strlen($senha) < 6) {
        $erro = true;
        $mensagem = "Erro: A senha de acesso deve ter pelo menos 6 caracteres.";
    } else {
        try {
            // Verificar se o e-mail já existe
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $erro = true;
                $mensagem = "Erro: Este e-mail já está sendo utilizado por outro usuário.";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo_id, congregacao_id, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senha_hash, $cargo_id, $congregacao_id, $ativo]);
                
                header("Location: usuarios.php?sucesso=1");
                exit;
            }
        } catch (Exception $e) {
            $erro = true;
            $mensagem = "Erro ao cadastrar usuário: " . $e->getMessage();
        }
    }
}

// BUSCA CARGOS E CONGREGAÇÕES PARA OS SELECTS
$cargos = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$congregacoes = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'SGIM - Novo Usuário do Painel';
$current_page = 'usuarios';

require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-white tracking-tight">Novo Usuário Administrativo</h2>
        <p class="text-sm text-gray-500 mt-1">Cadastre as credenciais e defina os limites de acesso no sistema.</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="p-4 rounded-xl <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3 shadow-lg">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Card 1: Informações Gerais -->
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl space-y-6">
            <h3 class="text-lg font-bold text-white border-b border-darkborder pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">badge</span>
                Dados Cadastrais
            </h3>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Nome Completo</label>
                <input name="nome" value="<?= htmlspecialchars($nome) ?>" required 
                       class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none transition-all" 
                       placeholder="Ex: Pastor João Silva" type="text"/>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">E-mail de Login</label>
                <input name="email" value="<?= htmlspecialchars($email) ?>" required 
                       class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none transition-all" 
                       placeholder="Ex: pastorjoao@igreja.com" type="email"/>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Senha de Acesso</label>
                    <input name="senha" required 
                           class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none transition-all" 
                           placeholder="Mín. 6 chars" type="password"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Confirmar Senha</label>
                    <input name="senha_confirm" required 
                           class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none transition-all" 
                           placeholder="Mín. 6 chars" type="password"/>
                </div>
            </div>
        </div>

        <!-- Card 2: Permissões e Vínculos -->
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl space-y-6">
            <h3 class="text-lg font-bold text-white border-b border-darkborder pb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">rule</span>
                Vínculo e Atribuição
            </h3>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cargo / Função</label>
                <select name="cargo_id" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                    <option value="">Nenhum cargo atribuído</option>
                    <?php foreach ($cargos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($cargo_id == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['escopo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Congregação Vinculada</label>
                <select name="congregacao_id" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                    <option value="">Sede / Ministério Global</option>
                    <?php foreach ($congregacoes as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= ($congregacao_id == $co['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-gray-500 italic">Obrigatório selecionar a congregação correta se o cargo tiver escopo LOCAL.</p>
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
                <button type="submit" class="w-full py-4 rounded-xl bg-brand hover:bg-brand-dark text-black font-black shadow-xl shadow-brand/10 transition-all uppercase tracking-widest text-xs">
                    Cadastrar Usuário
                </button>
                <a href="usuarios.php" class="w-full py-4 rounded-xl border border-darkborder text-gray-400 font-bold text-center hover:bg-white/5 transition-all uppercase tracking-widest text-xs">
                    Cancelar
                </a>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
