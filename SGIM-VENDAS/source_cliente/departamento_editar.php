<?php
ob_start();
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
$stmt->execute([$id]);
$dept = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dept) {
    header('Location: departamentos.php');
    exit;
}

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $icone = $_POST['icone'] ?? 'group';
    $status = $_POST['status'] ?? 'Ativo';

    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome é obrigatório.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE departamentos SET nome = ?, descricao = ?, icone = ?, status = ? WHERE id = ?");
            $stmt->execute([$nome, $descricao, $icone, $status, $id]);
            header('Location: departamentos.php?sucesso=1');
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao atualizar: " . $e->getMessage();
        }
    }
}

$page_title = 'Editar Departamento';
require_once 'includes/header.php';
?>
<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h2 class="text-3xl font-bold text-white">Editar Departamento</h2>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-darkcard border border-darkborder rounded-2xl p-8 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase">Nome</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($dept['nome']) ?>" required class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none">
            </div>
            <div class="space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase">Ícone</label>
                <input type="text" name="icone" value="<?= htmlspecialchars($dept['icone']) ?>" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none">
            </div>
            <div class="md:col-span-2 space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase">Descrição</label>
                <textarea name="descricao" rows="3" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none resize-none"><?= htmlspecialchars($dept['descricao']) ?></textarea>
            </div>
            <div class="space-y-2">
                <label class="text-xs font-bold text-gray-500 uppercase">Status</label>
                <select name="status" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-white focus:border-brand outline-none appearance-none">
                    <option value="Ativo" <?= $dept['status'] == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="Inativo" <?= $dept['status'] == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-4 pt-6 border-t border-darkborder">
            <a href="departamentos.php" class="px-8 py-3 text-gray-400 hover:text-white transition-colors">Cancelar</a>
            <button type="submit" class="bg-brand text-black font-bold px-12 py-3 rounded-xl hover:bg-brand-dark transition-all">Salvar Alterações</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>
