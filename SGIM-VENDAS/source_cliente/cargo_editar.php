<?php
ob_start();
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM cargos WHERE id = ?");
$stmt->execute([$id]);
$cargo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cargo) {
    header('Location: departamentos.php');
    exit;
}

$mensagem = '';
$erro = false;

// Buscar departamentos para o select
$stmtDepts = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC");
$departamentos = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $nivel_acesso = $_POST['nivel_acesso'] ?? 'Leitura';
    $departamento_id = !empty($_POST['departamento_id']) ? $_POST['departamento_id'] : null;

    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome do cargo é obrigatório.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE cargos SET nome = ?, nivel_acesso = ?, departamento_id = ? WHERE id = ?");
            $stmt->execute([$nome, $nivel_acesso, $departamento_id, $id]);
            header('Location: departamentos.php?sucesso=1');
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao atualizar cargo: " . $e->getMessage();
        }
    }
}

$page_title = 'Editar Cargo';
require_once 'includes/header.php';
?>
<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-white tracking-tight">Editar Cargo</h2>
        <p class="text-sm text-gray-500 mt-1">Altere as informações do cargo selecionado.</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 flex items-center gap-3">
            <span class="material-symbols-outlined">error</span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-darkcard border border-darkborder rounded-2xl p-8 space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Nome do Cargo</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($cargo['nome']) ?>" required class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none transition-all">
            </div>
            <div class="space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Nível de Acesso</label>
                <input type="text" name="nivel_acesso" value="<?= htmlspecialchars($cargo['nivel_acesso'] ?? 'Leitura') ?>" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none transition-all">
            </div>
            <div class="space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase tracking-widest">Departamento</label>
                <select name="departamento_id" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none appearance-none transition-all cursor-pointer font-bold">
                    <option value="" class="bg-darkcard text-white" style="background-color: #121212; color: #fff;">Sem departamento específico</option>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $cargo['departamento_id'] == $d['id'] ? 'selected' : '' ?> class="bg-darkcard text-white" style="background-color: #121212; color: #fff;">
                            <?= htmlspecialchars($d['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-end gap-4 pt-8 border-t border-darkborder">
            <a href="departamentos.php" class="px-8 py-3 text-gray-400 font-semibold hover:text-white transition-colors text-center">Cancelar</a>
            <button type="submit" class="bg-brand text-black font-bold px-12 py-3 rounded-xl hover:bg-brand-dark transition-all shadow-lg shadow-brand/10">Salvar Alterações</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>
