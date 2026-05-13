<?php
/**
 * SGIM - Cadastro de Congregação v5.1 (OTA SOURCE FIXED)
 */
ob_start();
require_once 'includes/header.php';

// Verificação de Autenticação e Conexão de Banco (Já garantido pelo header/bootstrap)
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pastor = $_POST['pastor'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $sigla = $_POST['sigla'] ?? '';
    $nome = $_POST['nome'] ?? '';
    
    if (empty($nome) || empty($sigla)) {
        $erro = true;
        $mensagem = "Nome e Sigla são obrigatórios.";
    } else {
        try {
            // Sincronização de Schema: Garante compatibilidade via Migração Defensiva
            ensureColumnExists($pdo, 'congregacoes', 'pastor', "VARCHAR(255) AFTER nome");
            ensureColumnExists($pdo, 'congregacoes', 'telefone', "VARCHAR(20) AFTER pastor");
            ensureColumnExists($pdo, 'congregacoes', 'endereco', "VARCHAR(255) AFTER telefone");

            $stmt = $pdo->prepare("INSERT INTO congregacoes (sigla, nome, pastor, telefone, endereco) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sigla, $nome, $pastor, $telefone, $endereco]);
            
            header("Location: congregacoes.php?sucesso=1");
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao cadastrar congregação: " . $e->getMessage();
        }
    }
}

$page_title = 'SGIM - Nova Congregação';
$current_page = 'congregacoes';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Cadastro de Congregação</h2>
        <p class="text-sm text-gray-500 mt-1">Adicione uma nova igreja ou congregação ao sistema.</p>
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
        <h2 class="text-lg font-bold text-white">Dados Cadastrais</h2>
        <p class="text-sm text-gray-500">Preencha as informações básicas do campo.</p>
    </div>
    <form method="POST" class="p-8 space-y-10">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome da Congregação</label>
                <input name="nome" required class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="Ex: Congregação Vila Real" type="text"/>
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Sigla (Até 10 letras)</label>
                <input name="sigla" required maxlength="10" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Ex: VR" type="text"/>
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Pastor Responsável</label>
                <input name="pastor" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Nome do pastor dirigente" type="text"/>
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Telefone da Congregação</label>
                <input name="telefone" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="(00) 00000-0000" type="tel"/>
            </div>
            <div class="md:col-span-2 space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Endereço Completo</label>
                <input name="endereco" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Rua, Número - Bairro, Cidade" type="text"/>
            </div>
        </div>

        <div class="pt-6 flex flex-col sm:flex-row items-center justify-end gap-4 border-t border-darkborder">
            <a href="congregacoes.php" class="w-full text-center sm:w-auto px-8 py-3 rounded-twelve border border-darkborder text-gray-400 font-semibold hover:bg-white/5 transition-colors">
                Cancelar
            </a>
            <button type="submit" class="w-full sm:w-auto px-12 py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all">
                Cadastrar Congregação
            </button>
        </div>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
