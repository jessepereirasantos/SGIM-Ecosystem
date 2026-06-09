<?php
/**
 * SGIM ERP - GESTÃO DE USUÁRIOS E ACESSOS
 * Reconstruído do zero para garantir funcionamento estável, visual premium e integração OTA segura.
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

// Validação estrita de leitura
if ($access && !$access->can('usuarios', 'visualizar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para visualizar a aba de usuários.'); window.location.href='dashboard.php';</script>";
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROCESSAMENTO DO FORMULÁRIO DE NOVO USUÁRIO (MODAL INLINE)
// ─────────────────────────────────────────────────────────────────────────────
$modal_mensagem = '';
$modal_erro     = false;
$modal_aberto   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_acao']) && $_POST['_acao'] === 'novo_usuario') {
    $modal_aberto = true;

    if ($access && !$access->can('usuarios', 'gerenciar')) {
        $modal_erro     = true;
        $modal_mensagem = 'Acesso Negado: Você não tem permissão para cadastrar novos usuários.';
    } else {
        $nome          = trim($_POST['nome'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $senha         = $_POST['senha'] ?? '';
        $senha_confirm = $_POST['senha_confirm'] ?? '';
        $cargo_id      = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
        $ativo         = isset($_POST['ativo']) ? 1 : 0;

        // Se o usuário não for global, força a congregação dele e impede atribuir cargo global
        if (!$access->isGlobal()) {
            $congregacao_id = $access->getCongregacaoId();
            if ($cargo_id) {
                $stmtEsc = $pdo->prepare("SELECT escopo FROM cargos WHERE id = ?");
                $stmtEsc->execute([$cargo_id]);
                $escopoC = $stmtEsc->fetchColumn();
                if ($escopoC !== 'local') {
                    $modal_erro     = true;
                    $modal_mensagem = 'Usuários de congregações locais só podem atribuir cargos locais.';
                }
            }
        } else {
            // Se for global, pode definir a congregação do form ou deixar nulo (Sede/Global)
            $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
        }

        // Validações de campos obrigatórios e senha
        if (!$modal_erro) {
            if (empty($nome) || empty($email) || empty($senha)) {
                $modal_erro     = true;
                $modal_mensagem = 'Todos os campos com asterisco (*) são obrigatórios.';
            } elseif ($senha !== $senha_confirm) {
                $modal_erro     = true;
                $modal_mensagem = 'A senha e a confirmação de senha não coincidem.';
            } elseif (strlen($senha) < 6) {
                $modal_erro     = true;
                $modal_mensagem = 'A senha de login deve conter no mínimo 6 caracteres.';
            } else {
                try {
                    // Evita e-mails duplicados no banco
                    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmtCheck->execute([$email]);
                    if ($stmtCheck->fetch()) {
                        $modal_erro     = true;
                        $modal_mensagem = 'Este endereço de e-mail já está cadastrado em outra conta.';
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo_id, congregacao_id, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $email, $senha_hash, $cargo_id, $congregacao_id, $ativo]);
                        
                        // Redireciona pós-sucesso para limpar buffer de requisição e evitar resubmit
                        header("Location: usuarios.php?sucesso=1");
                        exit;
                    }
                } catch (Exception $e) {
                    $modal_erro     = true;
                    $modal_mensagem = 'Erro no servidor ao processar o cadastro: ' . $e->getMessage();
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CARREGAMENTO DOS DADOS (MODAL & TABELA)
// ─────────────────────────────────────────────────────────────────────────────
// Busca cargos e congregações conforme escopo de quem está operando
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

// Carrega listagem de usuários filtrada pelo escopo (AccessManager getScopeFilter)
$scopeFilter = $access ? $access->getScopeFilter('u') : '';
$sql = "SELECT u.*, c.nome as cargo_nome, co.nome as congregacao_nome 
        FROM usuarios u
        LEFT JOIN cargos c  ON u.cargo_id = c.id
        LEFT JOIN congregacoes co ON u.congregacao_id = co.id
        WHERE 1=1 $scopeFilter
        ORDER BY u.nome ASC";
$usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$page_title   = 'SGIM - Gestão de Usuários';
$current_page = 'usuarios';

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- CORDÃO SUPERIOR E TÍTULO                                   -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h2 class="text-3xl font-extrabold text-white tracking-tight">Usuários e Permissões</h2>
        <p class="text-sm text-gray-500 mt-1">Gerencie os acessos administrativos da igreja e configure os cargos.</p>
    </div>
    <?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
    <button onclick="document.getElementById('modalNovoUsuario').classList.remove('hidden')"
            id="btn-novo-usuario"
            class="px-6 py-3.5 rounded-xl bg-brand text-black font-black flex items-center gap-2 hover:bg-brand/90 transition-all duration-200 shadow-lg shadow-brand/10 transform active:scale-95">
        <span class="material-symbols-outlined font-bold text-lg">person_add</span>
        <span>Novo Usuário</span>
    </button>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- NOTIFICAÇÃO DE SUCESSO                                      -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if (isset($_GET['sucesso'])): ?>
<div class="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
    <span class="material-symbols-outlined text-green-400">check_circle</span>
    <p class="text-sm font-semibold">Usuário administrativo registrado com sucesso!</p>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- LISTAGEM DE USUÁRIOS (TABELA PREMIUM)                      -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="bg-darkcard rounded-2xl border border-darkborder overflow-hidden shadow-2xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/[0.02] border-b border-darkborder">
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Colaborador / Login</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Nível de Acesso</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Congregação Associada</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Situação</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-darkborder">
                <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm italic">
                        Nenhum colaborador registrado. Clique em "Novo Usuário" no canto superior para começar.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr class="hover:bg-white/[0.01] transition-colors duration-150 group">
                        <!-- Nome e E-mail -->
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-3.5">
                                <div class="size-10 rounded-full bg-gradient-to-br from-darkbg to-darkcard border border-darkborder flex items-center justify-center text-brand font-black text-base shadow-sm">
                                    <?= strtoupper(substr(trim($u['nome']), 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white group-hover:text-brand transition-colors duration-200"><?= htmlspecialchars($u['nome']) ?></p>
                                    <p class="text-xs text-gray-500 font-medium"><?= htmlspecialchars($u['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <!-- Cargo -->
                        <td class="px-6 py-5">
                            <?php if ($u['cargo_nome']): ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand/10 text-brand border border-brand/20">
                                    <?= htmlspecialchars($u['cargo_nome']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] text-gray-600 font-semibold italic">Sem Nível Atribuído</span>
                            <?php endif; ?>
                        </td>
                        <!-- Congregação -->
                        <td class="px-6 py-5 text-sm font-semibold text-gray-400">
                            <?= $u['congregacao_nome'] ? htmlspecialchars($u['congregacao_nome']) : '<span class="text-gray-600 font-medium italic">Sede Geral / Todos</span>' ?>
                        </td>
                        <!-- Status -->
                        <td class="px-6 py-5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold <?= $u['ativo'] ? 'text-green-500' : 'text-red-500' ?>">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <!-- Ações -->
                        <td class="px-6 py-5">
                            <?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
                            <a href="usuario_editar.php?id=<?= $u['id'] ?>" 
                               class="p-2 rounded-lg bg-white/5 border border-darkborder text-gray-400 hover:text-brand hover:border-brand/30 transition-all inline-flex shadow-sm" 
                               title="Editar Vínculos de Acesso">
                                <span class="material-symbols-outlined text-sm font-bold">edit</span>
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-gray-600 font-medium italic">Sem Permissão</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- MODAL DE NOVO USUÁRIO (INLINE - EVITA REDIRECIONAMENTOS)     -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
<div id="modalNovoUsuario"
     class="<?= $modal_aberto ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm transition-all duration-200">

    <div class="bg-[#0c0c0c] border border-white/10 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform scale-100 transition-all duration-200">

        <!-- Cabeçalho do Modal -->
        <div class="flex items-center justify-between px-8 py-6 border-b border-white/10 bg-white/[0.01]">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand">
                    <span class="material-symbols-outlined text-xl font-bold">person_add</span>
                </div>
                <div>
                    <h3 class="text-lg font-black text-white">Criar Novo Usuário</h3>
                    <p class="text-xs text-gray-500 font-semibold">Credencie um novo administrador no sistema.</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalNovoUsuario').classList.add('hidden')"
                    class="p-2 rounded-lg text-gray-500 hover:text-white hover:bg-white/5 transition-all duration-150">
                <span class="material-symbols-outlined font-bold">close</span>
            </button>
        </div>

        <!-- Alerta de Erro no Modal -->
        <?php if ($modal_aberto && $modal_mensagem): ?>
        <div class="mx-8 mt-6 p-4 rounded-xl <?= $modal_erro ? 'bg-red-500/10 border-red-500/20 text-red-400' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined text-base font-bold"><?= $modal_erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-bold"><?= htmlspecialchars($modal_mensagem) ?></p>
        </div>
        <?php endif; ?>

        <!-- Formulário de Cadastro -->
        <form method="POST" action="usuarios.php" class="p-8 space-y-6">
            <input type="hidden" name="_acao" value="novo_usuario">

            <!-- Informações Básicas -->
            <div class="space-y-4">
                <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-base font-bold">badge</span>
                    Informações Pessoais
                </h4>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Nome Completo *</label>
                    <input name="nome" type="text" required
                           value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                           placeholder="Ex: João da Silva Santos"
                           class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all placeholder-gray-700 text-sm font-semibold"/>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">E-mail de Acesso *</label>
                    <input name="email" type="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Ex: joao.silva@igreja.com"
                           class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all placeholder-gray-700 text-sm font-semibold"/>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Senha de Acesso *</label>
                        <input name="senha" type="password" required placeholder="Min. 6 dígitos"
                               class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all placeholder-gray-700 text-sm font-semibold"/>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Confirmar Senha *</label>
                        <input name="senha_confirm" type="password" required placeholder="Repita a senha"
                               class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all placeholder-gray-700 text-sm font-semibold"/>
                    </div>
                </div>
            </div>

            <!-- Divisor -->
            <div class="border-t border-white/5"></div>

            <!-- Nível e Vínculo -->
            <div class="space-y-4">
                <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-base font-bold">rule</span>
                    Atribuição de Cargo
                </h4>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1.5">Cargo / Perfil de Acesso</label>
                    <div class="relative">
                        <select name="cargo_id"
                                class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none appearance-none text-sm font-semibold">
                            <option value="">Sem cargo (Somente Leitura Geral)</option>
                            <?php foreach ($modal_cargos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($_POST['cargo_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
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
                                class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none appearance-none text-sm font-semibold">
                            <option value="">Sede / Ministério Global</option>
                            <?php foreach ($modal_congs as $co): ?>
                            <option value="<?= $co['id'] ?>" <?= (($_POST['congregacao_id'] ?? '') == $co['id']) ? 'selected' : '' ?>>
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
                        <p class="text-sm font-bold text-white">Conta Habilitada</p>
                        <p class="text-[10px] text-gray-500 uppercase font-semibold">Liberar acesso do usuário ao sistema após criação</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="ativo" value="1" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                    </label>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex gap-4 pt-4">
                <button type="submit"
                        class="flex-1 py-3.5 rounded-xl bg-brand hover:bg-brand/90 text-black font-black shadow-lg shadow-brand/10 transition-all duration-200 uppercase tracking-widest text-xs">
                    Confirmar Cadastro
                </button>
                <button type="button"
                        onclick="document.getElementById('modalNovoUsuario').classList.add('hidden')"
                        class="flex-1 py-3.5 rounded-xl border border-white/10 text-gray-400 font-bold hover:bg-white/5 transition-all duration-200 uppercase tracking-widest text-xs">
                    Desistir
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Fecha o modal clicando fora dele para melhor UX
document.getElementById('modalNovoUsuario').addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.add('hidden');
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
