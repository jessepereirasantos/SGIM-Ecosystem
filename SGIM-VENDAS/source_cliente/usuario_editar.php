<?php
/**
 * SGIM ERP - EDIÇÃO DE USUÁRIO E VÍNCULOS
 * Reconstruído do zero sob as diretrizes da skill gt-cursos-blueprint.
 * Visual Obsidian Gold premium, inputs seguros e validações de escopo.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

// 🛡️ Inicializa o AccessManager para controle de escopo e RBAC
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) {
        require_once $amPath;
    }
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação de acesso para escrita/gerenciamento
if ($access && !$access->can('usuarios', 'gerenciar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para gerenciar usuários.'); window.location.href='usuarios.php';</script>";
    exit;
}

$target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$target_id) {
    header('Location: usuarios.php');
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// CARREGA DADOS DO USUÁRIO ALVO
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$target_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "<script>alert('Erro: Usuário não encontrado.'); window.location.href='usuarios.php';</script>";
    exit;
}

// Validação de escopo: administradores locais não podem editar usuários de outras congregações
if (!$access->isGlobal() && $usuario['congregacao_id'] !== $access->getCongregacaoId()) {
    echo "<script>alert('Acesso Negado: Este usuário pertence a outra congregação.'); window.location.href='usuarios.php';</script>";
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROCESSAMENTO DO POST (SALVAR ALTERAÇÕES)
// ─────────────────────────────────────────────────────────────────────────────
$mensagem_sucesso = '';
$mensagem_erro    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $cargo_id = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    $ativo    = isset($_POST['ativo']) ? 1 : 0;

    // Se o usuário não for global, força a congregação dele e impede cargo global
    if (!$access->isGlobal()) {
        $congregacao_id = $access->getCongregacaoId();
        if ($cargo_id) {
            $stmtEsc = $pdo->prepare("SELECT escopo FROM cargos WHERE id = ?");
            $stmtEsc->execute([$cargo_id]);
            $escopoC = $stmtEsc->fetchColumn();
            if ($escopoC !== 'local') {
                $mensagem_erro = 'Usuários de congregações locais só podem atribuir cargos locais.';
            }
        }
    } else {
        $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
    }

    if (empty($nome) || empty($email) || !$cargo_id) {
        $mensagem_erro = 'Os campos Nome, E-mail e Cargo são obrigatórios.';
    }

    if (empty($mensagem_erro)) {
        try {
            // Evita e-mail repetido (ignorando o próprio ID)
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $target_id]);
            if ($stmtCheck->fetch()) {
                $mensagem_erro = 'Este endereço de e-mail já está cadastrado em outra conta.';
            } else {
                // Monta a query
                if (!empty($senha)) {
                    if (strlen($senha) < 6) {
                        $mensagem_erro = 'A nova senha deve conter no mínimo 6 caracteres.';
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmtUp = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, cargo_id = ?, congregacao_id = ?, ativo = ? WHERE id = ?");
                        $stmtUp->execute([$nome, $email, $senha_hash, $cargo_id, $congregacao_id, $ativo, $target_id]);
                        $mensagem_sucesso = 'Usuário atualizado com sucesso (incluindo nova senha)!';
                    }
                } else {
                    $stmtUp = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, cargo_id = ?, congregacao_id = ?, ativo = ? WHERE id = ?");
                    $stmtUp->execute([$nome, $email, $cargo_id, $congregacao_id, $ativo, $target_id]);
                    $mensagem_sucesso = 'Usuário atualizado com sucesso!';
                }
                
                // Recarrega os dados atualizados
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$target_id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $mensagem_erro = 'Erro ao atualizar dados: ' . $e->getMessage();
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CARREGAMENTO DOS DADOS PARA O FORMULÁRIO
// ─────────────────────────────────────────────────────────────────────────────
if ($access->isGlobal()) {
    $modal_cargos = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $modal_congs  = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmtCg = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' AND escopo = 'local' ORDER BY nome ASC");
    $modal_cargos = $stmtCg->fetchAll(PDO::FETCH_ASSOC);
    $stmtCo = $pdo->prepare("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' AND id = ?");
    $stmtCo->execute([$access->getCongregacaoId()]);
    $modal_congs = $stmtCo->fetchAll(PDO::FETCH_ASSOC);
}

$page_title   = 'SGIM - Editar Usuário';
$current_page = 'usuarios';

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Combate ao Autofill nos inputs */
.gold-input:-webkit-autofill,
.gold-input:-webkit-autofill:hover, 
.gold-input:-webkit-autofill:focus, 
.gold-input:-webkit-autofill:active {
    -webkit-text-fill-color: #ffffff !important;
    -webkit-box-shadow: 0 0 0 1000px #0a0a0a inset !important;
    box-shadow: 0 0 0 1000px #0a0a0a inset !important;
    transition: background-color 5000s ease-in-out 0s !important;
    background-color: #0a0a0a !important;
    color: #ffffff !important;
}
.gold-input {
    background-color: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #ffffff;
    outline: none;
    transition: all 0.2s ease-in-out;
}
.gold-input:focus {
    border-color: #f2c94c !important;
    background-color: #0d0d0d !important;
    box-shadow: 0 0 12px rgba(242, 201, 76, 0.15) !important;
}
</style>

<!-- Cabeçalho -->
<div class="flex items-center gap-4 mb-8">
    <a href="usuarios.php" class="p-3 rounded-xl border border-darkborder bg-white/5 text-gray-400 hover:text-white hover:border-white/20 transition-all">
        <span class="material-symbols-outlined text-base font-bold">arrow_back</span>
    </a>
    <div>
        <h2 class="text-3xl font-extrabold text-white tracking-tight">Editar Usuário</h2>
        <p class="text-sm text-gray-500 mt-1">Altere as credenciais e nível de acesso do colaborador selecionado.</p>
    </div>
</div>

<!-- Notificações -->
<?php if ($mensagem_sucesso): ?>
<div class="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
    <span class="material-symbols-outlined">check_circle</span>
    <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem_sucesso) ?></p>
</div>
<?php endif; ?>

<?php if ($mensagem_erro): ?>
<div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center gap-3">
    <span class="material-symbols-outlined">error</span>
    <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem_erro) ?></p>
</div>
<?php endif; ?>

<!-- Card de Formulário -->
<div class="max-w-3xl bg-darkcard rounded-2xl border border-darkborder overflow-hidden shadow-2xl p-8">
    <form method="POST" class="space-y-6">
        
        <!-- Dados Cadastrais -->
        <div class="space-y-4">
            <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-[#f2c94c] text-base font-bold">badge</span>
                Dados Cadastrais
            </h4>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Nome Completo *</label>
                <input name="nome" type="text" required
                       value="<?= htmlspecialchars($usuario['nome']) ?>"
                       class="w-full px-4 py-3 rounded-xl gold-input text-white text-sm font-semibold"/>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">E-mail de Login *</label>
                <input name="email" type="email" required
                       value="<?= htmlspecialchars($usuario['email']) ?>"
                       class="w-full px-4 py-3 rounded-xl gold-input text-white text-sm font-semibold"/>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Nova Senha (deixe em branco para manter a atual)</label>
                <input name="senha" type="password" placeholder="Digite apenas se quiser alterar"
                       class="w-full px-4 py-3 rounded-xl gold-input text-white text-sm font-semibold"/>
            </div>
        </div>

        <!-- Separador -->
        <div class="border-t border-white/5"></div>

        <!-- Cargo e Escopo -->
        <div class="space-y-4">
            <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-[#f2c94c] text-base font-bold">rule</span>
                Vínculo e Atribuição
            </h4>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Cargo / Perfil de Acesso *</label>
                <div class="relative">
                    <select name="cargo_id" required
                            class="w-full px-4 py-3 rounded-xl gold-input text-white text-sm font-semibold appearance-none">
                        <option value="">-- Selecione o Cargo --</option>
                        <?php foreach ($modal_cargos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($usuario['cargo_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['escopo']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Congregação Vinculada</label>
                <?php if ($access->isGlobal()): ?>
                <div class="relative">
                    <select name="congregacao_id"
                            class="w-full px-4 py-3 rounded-xl gold-input text-white text-sm font-semibold appearance-none">
                        <option value="">Sede / Ministério Global</option>
                        <?php foreach ($modal_congs as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= ($usuario['congregacao_id'] == $co['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="congregacao_id" value="<?= $access->getCongregacaoId() ?>"/>
                <div class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-gray-400 text-sm font-semibold">
                    <?= htmlspecialchars($modal_congs[0]['nome'] ?? 'Congregação Local') ?>
                    <span class="text-xs text-gray-600 ml-2">(Fixo pelo escopo da sua conta)</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status Ativo/Inativo -->
            <div class="flex items-center justify-between p-4 rounded-xl bg-white/[0.02] border border-white/5">
                <div>
                    <p class="text-sm font-bold text-white">Usuário Ativo</p>
                    <p class="text-[10px] text-gray-500 uppercase font-semibold">Permitir que este usuário acesse o painel administrativo</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" <?= $usuario['ativo'] ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#f2c94c]"></div>
                </label>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="flex gap-4 pt-4">
            <button type="submit"
                    class="flex-1 py-3.5 rounded-xl bg-[#f2c94c] hover:bg-[#d4af37] text-black font-black shadow-lg shadow-[#f2c94c]/10 transition-all duration-200 uppercase tracking-widest text-xs">
                Salvar Alterações
            </button>
            <a href="usuarios.php"
               class="flex-1 py-3.5 rounded-xl border border-white/10 text-gray-400 font-bold hover:bg-white/5 transition-all duration-200 uppercase tracking-widest text-xs text-center leading-normal">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
