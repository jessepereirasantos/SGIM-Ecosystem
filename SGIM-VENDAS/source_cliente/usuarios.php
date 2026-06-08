<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

// 🛡️ Inicializa o AccessManager
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de leitura
if ($access && !$access->can('usuarios', 'visualizar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para ver Usuários.'); window.location.href='dashboard.php';</script>";
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROCESSAMENTO DO FORMULÁRIO DE NOVO USUÁRIO (INLINE — sem usuario_novo.php)
// ─────────────────────────────────────────────────────────────────────────────
$modal_mensagem = '';
$modal_erro     = false;
$modal_aberto   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_acao']) && $_POST['_acao'] === 'novo_usuario') {
    $modal_aberto = true;

    if ($access && !$access->can('usuarios', 'gerenciar')) {
        $modal_erro      = true;
        $modal_mensagem  = 'Acesso negado: você não tem permissão para cadastrar usuários.';
    } else {
        $nome          = trim($_POST['nome']  ?? '');
        $email         = trim($_POST['email'] ?? '');
        $senha         = $_POST['senha']         ?? '';
        $senha_confirm = $_POST['senha_confirm'] ?? '';
        $cargo_id      = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
        $ativo         = isset($_POST['ativo']) ? 1 : 0;

        // Escopo local: congrega forced; global: congrega do form
        if (!$access->isGlobal()) {
            $congregacao_id = $access->getCongregacaoId();
            // Impede cargo global
            if ($cargo_id) {
                $stmtEsc = $pdo->prepare("SELECT escopo FROM cargos WHERE id = ?");
                $stmtEsc->execute([$cargo_id]);
                $escopoC = $stmtEsc->fetchColumn();
                if ($escopoC !== 'local') {
                    $modal_erro     = true;
                    $modal_mensagem = 'Usuários com escopo local não podem atribuir cargos globais.';
                }
            }
        } else {
            $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
        }

        if (!$modal_erro) {
            if (empty($nome) || empty($email) || empty($senha)) {
                $modal_erro     = true;
                $modal_mensagem = 'Nome, E-mail e Senha são obrigatórios.';
            } elseif ($senha !== $senha_confirm) {
                $modal_erro     = true;
                $modal_mensagem = 'A senha e a confirmação não coincidem.';
            } elseif (strlen($senha) < 6) {
                $modal_erro     = true;
                $modal_mensagem = 'A senha deve ter pelo menos 6 caracteres.';
            } else {
                try {
                    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmtCheck->execute([$email]);
                    if ($stmtCheck->fetch()) {
                        $modal_erro     = true;
                        $modal_mensagem = 'Este e-mail já está em uso por outro usuário.';
                    } else {
                        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cargo_id, congregacao_id, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nome, $email, $senha_hash, $cargo_id, $congregacao_id, $ativo]);
                        // Redireciona pós-sucesso para evitar resubmit
                        header("Location: usuarios.php?sucesso=1");
                        exit;
                    }
                } catch (Exception $e) {
                    $modal_erro     = true;
                    $modal_mensagem = 'Erro interno: ' . $e->getMessage();
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// BUSCA DADOS PARA O MODAL E PARA A TABELA
// ─────────────────────────────────────────────────────────────────────────────
// Cargos e congregações para o modal de criação
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

// Lista de usuários para a tabela
$scopeFilter = $access ? $access->getScopeFilter('u') : '';
$sql = "SELECT u.*, c.nome as cargo_nome, co.nome as congregacao_nome 
        FROM usuarios u
        LEFT JOIN cargos c  ON u.cargo_id       = c.id
        LEFT JOIN congregacoes co ON u.congregacao_id = co.id
        WHERE 1=1 $scopeFilter
        ORDER BY u.nome ASC";
$usuarios = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$page_title   = 'SGIM - Gestão de Usuários';
$current_page = 'usuarios';

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- CABEÇALHO DA PÁGINA                                        -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Usuários e Acessos</h2>
        <p class="text-sm text-gray-500 mt-1">Gerencie quem pode acessar o sistema e quais são seus limites.</p>
    </div>
    <?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
    <button onclick="document.getElementById('modalNovoUsuario').classList.remove('hidden')"
            id="btn-novo-usuario"
            class="px-6 py-3 rounded-xl bg-brand text-black font-bold flex items-center gap-2 hover:bg-brand-dark transition-all shadow-lg shadow-brand/10">
        <span class="material-symbols-outlined">person_add</span>
        Novo Usuário
    </button>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- ALERTA DE SUCESSO                                           -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if (isset($_GET['sucesso'])): ?>
<div class="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
    <span class="material-symbols-outlined">check_circle</span>
    <p class="text-sm font-semibold">Usuário cadastrado com sucesso!</p>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- TABELA DE USUÁRIOS                                          -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="bg-darkcard rounded-2xl border border-darkborder overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/[0.02] border-b border-darkborder">
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Usuário</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cargo / Nível</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Congregação</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Status</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-darkborder">
                <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 text-sm italic">
                        Nenhum usuário cadastrado ainda. Clique em "Novo Usuário" para começar.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr class="hover:bg-white/[0.01] transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-gradient-to-br from-darkbg to-darkcard border border-darkborder flex items-center justify-center text-brand font-bold">
                                    <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($u['nome']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($u['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($u['cargo_nome']): ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-brand/10 text-brand border border-brand/20">
                                    <?= htmlspecialchars($u['cargo_nome']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] text-gray-600 italic">Sem cargo definido</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-400">
                            <?= $u['congregacao_nome'] ? htmlspecialchars($u['congregacao_nome']) : '<span class="text-gray-600 italic">Sede / Global</span>' ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="flex items-center gap-1.5 text-xs <?= $u['ativo'] ? 'text-green-500' : 'text-red-500' ?>">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
                            <a href="usuario_editar.php?id=<?= $u['id'] ?>" class="p-2 rounded-lg bg-white/5 border border-darkborder text-gray-400 hover:text-brand hover:border-brand/30 transition-all inline-flex" title="Editar Vínculos">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-gray-600">Sem Permissão</span>
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
<!-- MODAL DE NOVO USUÁRIO (INLINE — sem arquivo separado)       -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if ($access && $access->can('usuarios', 'gerenciar')): ?>
<div id="modalNovoUsuario"
     class="<?= $modal_aberto ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background: rgba(0,0,0,0.75); backdrop-filter: blur(6px);">

    <div class="bg-[#0f0f0f] border border-white/10 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

        <!-- Cabeçalho do Modal -->
        <div class="flex items-center justify-between px-8 py-6 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand">
                    <span class="material-symbols-outlined">person_add</span>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Novo Usuário Administrativo</h3>
                    <p class="text-xs text-gray-500">Cadastre credenciais e defina os acessos</p>
                </div>
            </div>
            <button onclick="document.getElementById('modalNovoUsuario').classList.add('hidden')"
                    class="p-2 rounded-lg text-gray-500 hover:text-white hover:bg-white/5 transition-all">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Alerta de Erro dentro do Modal -->
        <?php if ($modal_aberto && $modal_mensagem): ?>
        <div class="mx-8 mt-6 p-4 rounded-xl <?= $modal_erro ? 'bg-red-500/10 border-red-500/20 text-red-400' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $modal_erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($modal_mensagem) ?></p>
        </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form method="POST" action="usuarios.php" class="p-8 space-y-6">
            <input type="hidden" name="_acao" value="novo_usuario">

            <!-- Dados Cadastrais -->
            <div class="space-y-4">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-base">badge</span>
                    Dados Cadastrais
                </h4>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Nome Completo *</label>
                    <input name="nome" type="text" required
                           value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                           placeholder="Ex: Pastor João Silva"
                           class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand outline-none transition-all placeholder-gray-600"/>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">E-mail de Login *</label>
                    <input name="email" type="email" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Ex: pastorjoao@igreja.com"
                           class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand outline-none transition-all placeholder-gray-600"/>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Senha *</label>
                        <input name="senha" type="password" required placeholder="Mín. 6 caracteres"
                               class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand outline-none transition-all placeholder-gray-600"/>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Confirmar Senha *</label>
                        <input name="senha_confirm" type="password" required placeholder="Repita a senha"
                               class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand outline-none transition-all placeholder-gray-600"/>
                    </div>
                </div>
            </div>

            <!-- Separador -->
            <div class="border-t border-white/5"></div>

            <!-- Vínculo e Atribuição -->
            <div class="space-y-4">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-base">rule</span>
                    Vínculo e Atribuição
                </h4>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Cargo / Função</label>
                    <select name="cargo_id"
                            class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                        <option value="">Nenhum cargo atribuído</option>
                        <?php foreach ($modal_cargos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (($_POST['cargo_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['escopo']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Congregação Vinculada</label>
                    <?php if ($access->isGlobal()): ?>
                    <select name="congregacao_id"
                            class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                        <option value="">Sede / Ministério Global</option>
                        <?php foreach ($modal_congs as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= (($_POST['congregacao_id'] ?? '') == $co['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="congregacao_id" value="<?= $access->getCongregacaoId() ?>"/>
                    <div class="w-full px-4 py-3 rounded-xl border border-white/10 bg-white/5 text-gray-400 text-sm">
                        <?= htmlspecialchars($modal_congs[0]['nome'] ?? 'Sua Congregação') ?>
                        <span class="text-xs text-gray-600 ml-2">(fixo pelo escopo local)</span>
                    </div>
                    <?php endif; ?>
                    <p class="text-[10px] text-gray-600 italic mt-1">
                        <?= $access->isGlobal() ? 'Selecione a congregação do novo usuário ou deixe em branco para acesso global.' : 'Você só pode criar usuários vinculados à sua congregação.' ?>
                    </p>
                </div>

                <!-- Toggle Ativo -->
                <div class="flex items-center justify-between p-4 rounded-xl bg-white/[0.03] border border-white/5">
                    <div>
                        <p class="text-sm font-bold text-white">Usuário Ativo</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Permitir login imediato no sistema</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="ativo" value="1" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                    </label>
                </div>
            </div>

            <!-- Botões de ação -->
            <div class="flex gap-4 pt-2">
                <button type="submit"
                        class="flex-1 py-3 rounded-xl bg-brand hover:bg-brand-dark text-black font-black shadow-xl shadow-brand/10 transition-all uppercase tracking-widest text-xs">
                    Cadastrar Usuário
                </button>
                <button type="button"
                        onclick="document.getElementById('modalNovoUsuario').classList.add('hidden')"
                        class="flex-1 py-3 rounded-xl border border-white/10 text-gray-400 font-bold hover:bg-white/5 transition-all uppercase tracking-widest text-xs">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Fecha o modal clicando fora dele
document.getElementById('modalNovoUsuario').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
