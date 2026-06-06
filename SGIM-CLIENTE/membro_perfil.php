<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

// 🛡️ Inicializa o AccessManager para proteção de rota antecipada
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de leitura
if ($access && !$access->can('membros', 'visualizar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para visualizar membros.'); window.location.href='dashboard.php';</script>";
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT m.*, c.nome as cargo_nome, con.nome as congregacao_nome 
                       FROM membros m 
                       LEFT JOIN cargos c ON m.cargo_id = c.id 
                       LEFT JOIN congregacoes con ON m.congregacao_id = con.id 
                       WHERE m.id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    die("Membro não encontrado.");
}

// Verificar se já existe um usuário do painel com o e-mail deste membro
$usuario_existe = false;
$usuario_ativo = false;
if (!empty($m['email'])) {
    $stmtU = $pdo->prepare("SELECT id, ativo FROM usuarios WHERE email = ?");
    $stmtU->execute([$m['email']]);
    $uData = $stmtU->fetch(PDO::FETCH_ASSOC);
    if ($uData) {
        $usuario_existe = true;
        $usuario_ativo = $uData['ativo'];
    }
}

$mensagem_promocao = '';
$erro_promocao = false;

// Processamento da Geração de Acesso ao Painel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_promover'])) {
    if ($access && !$access->can('usuarios', 'gerenciar')) {
        $erro_promocao = true;
        $mensagem_promocao = "Acesso Negado: Você não tem permissão para gerenciar usuários.";
    } elseif (empty($m['email'])) {
        $erro_promocao = true;
        $mensagem_promocao = "Erro: Este membro não possui um e-mail cadastrado. Edite o perfil dele e adicione um e-mail primeiro.";
    } else {
        $senha_painel = $_POST['senha_painel'] ?? '';
        if (strlen($senha_painel) < 6) {
            $erro_promocao = true;
            $mensagem_promocao = "Erro: A senha deve conter pelo menos 6 caracteres.";
        } else {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmtCheck->execute([$m['email']]);
                if ($stmtCheck->fetch()) {
                    $erro_promocao = true;
                    $mensagem_promocao = "Erro: Já existe um usuário cadastrado com este e-mail.";
                } else {
                    $senha_hash = password_hash($senha_painel, PASSWORD_DEFAULT);
                    $stmtIns = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo_id, congregacao_id, ativo) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmtIns->execute([$m['nome'], $m['email'], $senha_hash, $m['cargo_id'], $m['congregacao_id']]);
                    
                    $usuario_existe = true;
                    $usuario_ativo = true;
                    $mensagem_promocao = "Acesso ao painel gerado com sucesso para o e-mail: " . htmlspecialchars($m['email']);
                }
            } catch (Exception $e) {
                $erro_promocao = true;
                $mensagem_promocao = "Erro ao criar usuário: " . $e->getMessage();
            }
        }
    }
}

// Se for escopo LOCAL, valida se o membro pertence à mesma congregação
if ($access && !$access->isGlobal() && $m['congregacao_id'] != $access->getCongregacaoId()) {
    echo "<script>alert('Acesso Negado: Este membro pertence a outra congregação.'); window.location.href='membros.php';</script>";
    exit;
}

$page_title = 'SGIM - Perfil do Membro';
$current_page = 'membros';

require_once 'includes/header.php';
?>

<div class="flex flex-col gap-8">
    <?php if ($mensagem_promocao): ?>
        <div class="p-4 rounded-xl <?= $erro_promocao ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3 shadow-lg">
            <span class="material-symbols-outlined"><?= $erro_promocao ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem_promocao) ?></p>
        </div>
    <?php endif; ?>

    <!-- Header Perfil -->
    <div class="flex flex-col md:flex-row items-center gap-8 bg-darkcard p-8 rounded-twelve border border-darkborder shadow-xl">
        <div class="size-48 rounded-full border-4 border-brand/20 p-1">
            <div class="w-full h-full rounded-full bg-darkbg overflow-hidden flex items-center justify-center border-2 border-brand">
                <?php if ($m['foto']): ?>
                    <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="material-symbols-outlined text-gray-700 text-7xl font-light">person</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex-1 text-center md:text-left">
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 mb-2">
                <h2 class="text-4xl font-black text-white tracking-tighter"><?= htmlspecialchars($m['nome']) ?></h2>
                <span class="px-3 py-1 bg-brand text-black text-[10px] font-black uppercase rounded-full">
                    <?= htmlspecialchars($m['cargo_nome'] ?: 'Membro') ?>
                </span>
            </div>
            <p class="text-gray-500 font-medium tracking-wide">
                <?= htmlspecialchars($m['congregacao_nome'] ?: 'Sede Central') ?> • Membro desde <?= $m['data_conversao'] ? date('d/m/Y', strtotime($m['data_conversao'])) : 'N/A' ?>
            </p>
            
            <div class="flex flex-wrap gap-4 mt-8">
                <a href="membro_novo.php?id=<?= $m['id'] ?>" class="flex items-center gap-2 px-6 py-2.5 bg-darkbg border border-darkborder hover:border-brand rounded-twelve text-sm font-bold text-gray-300 transition-all">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Editar Perfil
                </a>
                <a href="carteirinha_gerar.php?id=<?= $m['id'] ?>" class="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-dark text-black rounded-twelve text-sm font-bold shadow-lg shadow-brand/20 transition-all">
                    <span class="material-symbols-outlined text-[18px]">badge</span>
                    Gerar Carteirinha
                </a>

                <?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
                    <?php if ($usuario_existe): ?>
                        <div class="flex items-center gap-2 px-6 py-2.5 bg-green-500/10 border border-green-500/20 text-green-400 rounded-twelve text-sm font-bold">
                            <span class="material-symbols-outlined text-[18px]">verified_user</span>
                            Acesso Ativo
                        </div>
                    <?php else: ?>
                        <button onclick="document.getElementById('modal-promocao').classList.remove('hidden')" class="flex items-center gap-2 px-6 py-2.5 bg-white/5 border border-darkborder hover:border-brand/40 text-gray-300 rounded-twelve text-sm font-bold transition-all">
                            <span class="material-symbols-outlined text-[18px]">vpn_key</span>
                            Gerar Acesso ao Painel
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Grid de Informações -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Dados Pessoais -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="flex items-center gap-2 text-brand text-sm font-black uppercase tracking-widest mb-6">
                <span class="material-symbols-outlined text-lg">person</span>
                Dados Pessoais
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Nome Completo</p>
                    <p class="text-white font-medium"><?= htmlspecialchars($m['nome']) ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Data de Nascimento</p>
                    <p class="text-white font-medium"><?= $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Endereço Residencial</p>
                    <p class="text-white font-medium leading-relaxed"><?= htmlspecialchars($m['endereco'] ?: 'N/A') ?></p>
                </div>
            </div>
        </div>

        <!-- Vida Ministerial -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="flex items-center gap-2 text-brand text-sm font-black uppercase tracking-widest mb-6">
                <span class="material-symbols-outlined text-lg">church</span>
                Vida Ministerial
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Data de Batismo</p>
                    <p class="text-white font-medium"><?= $m['data_batismo'] ? date('d/m/Y', strtotime($m['data_batismo'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Cargo Atual</p>
                    <p class="text-brand font-black"><?= htmlspecialchars($m['cargo_nome'] ?: 'Membro') ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Congregação</p>
                    <p class="text-white font-medium"><?= htmlspecialchars($m['congregacao_nome'] ?: 'Sede Central') ?></p>
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="flex items-center gap-2 text-brand text-sm font-black uppercase tracking-widest mb-6">
                <span class="material-symbols-outlined text-lg">contact_support</span>
                Contato
            </h3>
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-darkbg rounded-lg border border-darkborder">
                    <span class="material-symbols-outlined text-brand text-sm">mail</span>
                    <p class="text-xs text-gray-300"><?= htmlspecialchars($m['email'] ?: 'N/A') ?></p>
                </div>
                <div class="flex items-center gap-3 p-3 bg-darkbg rounded-lg border border-darkborder">
                    <span class="material-symbols-outlined text-green-500 text-sm">chat</span>
                    <p class="text-xs text-gray-300"><?= htmlspecialchars($m['telefone'] ?: 'N/A') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Modal de Geração de Acesso (RBAC) -->
    <div id="modal-promocao" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-promocao').classList.add('hidden')"></div>
        <div class="relative z-10 w-full max-w-md bg-darkcard border border-darkborder rounded-2xl p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-darkborder pb-4">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand">vpn_key</span>
                    Gerar Acesso ao Painel
                </h3>
                <button onclick="document.getElementById('modal-promocao').classList.add('hidden')" class="text-gray-500 hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action_promover" value="1"/>
                
                <div class="space-y-1">
                    <p class="text-[10px] text-gray-500 font-bold uppercase">Usuário (E-mail de Login)</p>
                    <p class="text-sm font-semibold text-white"><?= htmlspecialchars($m['email'] ?: '') ?></p>
                    <?php if (empty($m['email'])): ?>
                        <p class="text-xs text-red-500 italic mt-1">⚠️ Este membro não possui e-mail cadastrado na ficha. Adicione um e-mail antes de criar o acesso.</p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($m['email'])): ?>
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Senha Inicial de Acesso</label>
                    <input name="senha_painel" required type="password" minlength="6"
                           class="w-full px-4 py-3 bg-darkbg border border-darkborder rounded-xl text-white focus:ring-2 focus:ring-brand outline-none transition-all" 
                           placeholder="Mínimo de 6 caracteres"/>
                </div>
                <?php endif; ?>

                <div class="pt-4 flex gap-3">
                    <?php if (!empty($m['email'])): ?>
                    <button type="submit" class="flex-1 py-3 bg-brand hover:bg-brand-dark text-black font-black text-xs uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-brand/10">
                        Confirmar e Criar
                    </button>
                    <?php endif; ?>
                    <button type="button" onclick="document.getElementById('modal-promocao').classList.add('hidden')" class="px-5 py-3 border border-darkborder text-gray-400 hover:bg-white/5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
