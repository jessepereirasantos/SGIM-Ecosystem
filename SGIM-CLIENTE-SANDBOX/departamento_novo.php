<?php
ob_start();
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

// Verificação de Autenticação e Conexão de Banco
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
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
        $mensagem = "O nome do departamento é obrigatório.";
    } else {
        try {
            // Sincronização de Schema: Garante compatibilidade com o banco real
            $checkCols = $pdo->query("SHOW COLUMNS FROM departamentos");
            $cols = $checkCols->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('descricao', $cols)) {
                $pdo->exec("ALTER TABLE departamentos ADD COLUMN descricao TEXT AFTER nome");
            }
            if (!in_array('icone', $cols)) {
                $pdo->exec("ALTER TABLE departamentos ADD COLUMN icone VARCHAR(50) DEFAULT 'group' AFTER descricao");
            }
            if (!in_array('status', $cols)) {
                $pdo->exec("ALTER TABLE departamentos ADD COLUMN status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo' AFTER icone");
            }

            $stmt = $pdo->prepare("INSERT INTO departamentos (nome, descricao, icone, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $descricao, $icone, $status]);
            
            header("Location: departamentos.php?sucesso=1");
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao criar departamento: " . $e->getMessage();
        }
    }
}

$page_title = 'SGIM - Novo Departamento';
$current_page = 'departamentos';

require_once 'includes/header.php';
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Novo Departamento</h2>
            <p class="text-sm text-gray-500 mt-1">Crie uma nova estrutura organizacional na sua igreja.</p>
        </div>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden">
        <div class="p-6 border-b border-darkborder bg-white/[0.02]">
            <h2 class="text-lg font-bold text-white">Informações do Departamento</h2>
            <p class="text-sm text-gray-500">Preencha os dados abaixo.</p>
        </div>
        <form method="POST" class="p-8 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome do Departamento</label>
                    <input name="nome" required class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="Ex: Louvor e Adoração" type="text"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Ícone (Material Symbols)</label>
                    <input name="icone" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Ex: music_note" type="text" value="group"/>
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Descrição</label>
                    <textarea name="descricao" rows="3" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none resize-none" placeholder="Breve descrição das atividades deste departamento..."></textarea>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Status</label>
                    <select name="status" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                        <option value="Ativo">Ativo</option>
                        <option value="Inativo">Inativo</option>
                    </select>
                </div>
            </div>

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-end gap-4 border-t border-darkborder">
                <a href="departamentos.php" class="w-full text-center sm:w-auto px-8 py-3 rounded-twelve border border-darkborder text-gray-400 font-semibold hover:bg-white/5 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="w-full sm:w-auto px-12 py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all">
                    Criar Departamento
                </button>
            </div>
        </form>
    </div>

<?php
require_once 'includes/footer.php';
?>
