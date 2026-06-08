<?php
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
    echo "<script>alert('Acesso Negado: Você não tem permissão para gerenciar usuários.'); window.location.href='usuarios.php';</script>";
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: usuarios.php');
    exit;
}

$mensagem = '';
$erro = false;

// 1. BUSCA DADOS DO USUÁRIO
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: usuarios.php');
    exit;
}

// Se for escopo LOCAL, valida se o usuário editado pertence à mesma congregação
if ($access && !$access->isGlobal() && $usuario['congregacao_id'] != $access->getCongregacaoId()) {
    echo "<script>alert('Acesso Negado: Este usuário pertence a outra congregação.'); window.location.href='usuarios.php';</script>";
    exit;
}

// 2. PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cargo_id = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    if ($access && !$access->isGlobal()) {
        $congregacao_id = (int)$access->getCongregacaoId();
        if ($cargo_id) {
            $stmtCgCheck = $pdo->prepare("SELECT escopo FROM cargos WHERE id = ?");
            $stmtCgCheck->execute([$cargo_id]);
            $cgEscopo = $stmtCgCheck->fetchColumn();
            if ($cgEscopo !== 'local') {
                $erro = true;
                $mensagem = "Erro: Usuários com escopo local não podem atribuir cargos globais.";
            }
        }
    } else {
        $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
    }
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (!$erro) {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET cargo_id = ?, congregacao_id = ?, ativo = ? WHERE id = ?");
            $stmt->execute([$cargo_id, $congregacao_id, $ativo, $id]);
            
            header("Location: usuarios.php?sucesso=1");
            exit;
        } catch (Exception $e) {
            $erro = true;
            $mensagem = "Erro ao atualizar usuário: " . $e->getMessage();
        }
    }
}

// 3. BUSCA CARGOS E CONGREGAÇÕES PARA OS SELECTS
if ($access && $access->isGlobal()) {
    $cargos = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $congregacoes = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cargos = $pdo->query("SELECT id, nome, escopo FROM cargos WHERE status = 'Ativo' AND escopo = 'local' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $congregacoes = $pdo->query("SELECT id, nome FROM congregacoes WHERE id = " . (int)$access->getCongregacaoId() . " ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'SGIM - Editar Vínculo de Usuário';
$current_page = 'usuarios';

require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-white tracking-tight">Vínculo Ministerial</h2>
        <p class="text-sm text-gray-500 mt-1">Defina o papel de <strong><?= htmlspecialchars($usuario['nome']) ?></strong> no sistema.</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl space-y-6">
            <h3 class="text-lg font-bold text-white border-b border-darkborder pb-4 mb-6">Atribuições</h3>
            
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cargo / Função</label>
                <select name="cargo_id" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                    <option value="">Nenhum cargo atribuído</option>
                    <?php foreach ($cargos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($usuario['cargo_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?> (<?= strtoupper($c['escopo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Congregação Vinculada</label>
                <select name="congregacao_id" <?= !$access->isGlobal() ? 'disabled' : '' ?> class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none <?= !$access->isGlobal() ? 'opacity-60 cursor-not-allowed' : '' ?>">
                    <?php if ($access->isGlobal()): ?>
                        <option value="">Sede / Ministério Global</option>
                    <?php endif; ?>
                    <?php foreach ($congregacoes as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= ($usuario['congregacao_id'] == $co['id'] || (!$access->isGlobal() && $access->getCongregacaoId() == $co['id'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$access->isGlobal()): ?>
                    <input type="hidden" name="congregacao_id" value="<?= $access->getCongregacaoId() ?>"/>
                <?php endif; ?>
                <p class="text-[10px] text-gray-500 italic">
                    <?= $access->isGlobal() ? 'Para cargos com escopo LOCAL, este campo é obrigatório para filtrar os dados corretamente.' : 'Você só pode gerenciar usuários vinculados à sua própria congregação.' ?>
                </p>
            </div>
        </div>

        <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl space-y-6">
            <h3 class="text-lg font-bold text-white border-b border-darkborder pb-4 mb-6">Status e Segurança</h3>
            
            <div class="flex items-center justify-between p-4 rounded-xl bg-darkbg border border-darkborder">
                <div>
                    <p class="text-sm font-bold text-white">Usuário Ativo</p>
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider">Permitir login no sistema</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="ativo" <?= $usuario['ativo'] ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                </label>
            </div>

            <div class="pt-10 flex flex-col gap-4">
                <button type="submit" class="w-full py-4 rounded-xl bg-brand hover:bg-brand-dark text-black font-black shadow-xl shadow-brand/10 transition-all">
                    SALVAR ALTERAÇÕES
                </button>
                <a href="usuarios.php" class="w-full py-4 rounded-xl border border-darkborder text-gray-400 font-bold text-center hover:bg-white/5 transition-all">
                    Voltar para Lista
                </a>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
