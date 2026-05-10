<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $nivel_acesso = $_POST['nivel_acesso'] ?? 'Leitura';
    $departamento_id = !empty($_POST['departamento_id']) ? $_POST['departamento_id'] : null;
    
    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome do cargo é obrigatório.";
    } else {
        try {
            // Sincronização de Schema: Garante que as colunas existam
            $checkCols = $pdo->query("SHOW COLUMNS FROM cargos");
            $cols = $checkCols->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('nivel_acesso', $cols)) {
                $pdo->exec("ALTER TABLE cargos ADD COLUMN nivel_acesso VARCHAR(50) DEFAULT 'Leitura' AFTER nome");
            }
            if (!in_array('departamento_id', $cols)) {
                $pdo->exec("ALTER TABLE cargos ADD COLUMN departamento_id INT AFTER nivel_acesso");
            }

            $stmt = $pdo->prepare("INSERT INTO cargos (nome, nivel_acesso, departamento_id) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $nivel_acesso, $departamento_id]);
            
            header("Location: departamentos.php?sucesso=1");
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao criar cargo: " . $e->getMessage();
        }
    }
}

// Buscar departamentos para o select
$stmt = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC");
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'SGIM - Novo Cargo';
$current_page = 'departamentos';

require_once 'includes/header.php';
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Novo Cargo</h2>
            <p class="text-sm text-gray-500 mt-1">Adicione um novo cargo à sua estrutura.</p>
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
            <h2 class="text-lg font-bold text-white">Informações do Cargo</h2>
            <p class="text-sm text-gray-500">Defina o nome e nível de acesso do cargo.</p>
        </div>
        <form method="POST" class="p-8 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome do Cargo</label>
                    <input name="nome" required class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="Ex: Líder de Jovens" type="text"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nível de Acesso (Dica)</label>
                    <input name="nivel_acesso" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Ex: Gestão" type="text" value="Leitura"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Departamento (Opcional)</label>
                    <select name="departamento_id" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                        <option value="">Sem departamento específico</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-end gap-4 border-t border-darkborder">
                <a href="departamentos.php" class="w-full text-center sm:w-auto px-8 py-3 rounded-twelve border border-darkborder text-gray-400 font-semibold hover:bg-white/5 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="w-full sm:w-auto px-12 py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all">
                    Criar Cargo
                </button>
            </div>
        </form>
    </div>

<?php
require_once 'includes/footer.php';
?>
