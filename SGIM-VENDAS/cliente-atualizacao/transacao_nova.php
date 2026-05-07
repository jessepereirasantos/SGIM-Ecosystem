require_once 'includes/header.php';

// Verificação de Autenticação e Conexão de Banco (Bootstrap já faz o session_start)
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

$membros = [];
try {
    $membros = $pdo->query("SELECT id, nome FROM membros WHERE status = 'Ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$mensagem = '';
$erro = false;

$tipo_url = isset($_GET['tipo']) && $_GET['tipo'] == 'saida' ? 'saida' : 'entrada';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? 'entrada';
    $categoria = $_POST['categoria'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $valor_str = str_replace(['R$', '.', ' '], '', $_POST['valor'] ?? '');
    $valor_str = str_replace(',', '.', $valor_str);
    $valor = (float)$valor_str;
    $data_transacao = $_POST['data_transacao'] ?? date('Y-m-d');
    $membro_id = !empty($_POST['membro_id']) ? intval($_POST['membro_id']) : null;
    $nome_identificado = $_POST['nome_identificado'] ?? null;
    
    if (empty($categoria) || $valor <= 0) {
        $erro = true;
        $mensagem = "Categoria e Valor (maior que 0) são obrigatórios.";
    } else {
        try {
            // Sincronização de Schema: Garante compatibilidade via Migração Defensiva
            ensureColumnExists($pdo, 'financeiro_transacoes', 'data_transacao', "DATE AFTER valor");
            ensureColumnExists($pdo, 'financeiro_transacoes', 'membro_id', "INT NULL AFTER data_transacao");
            ensureColumnExists($pdo, 'financeiro_transacoes', 'nome_identificado', "VARCHAR(255) NULL AFTER membro_id");

            $stmt = $pdo->prepare("INSERT INTO financeiro_transacoes (tipo, categoria, descricao, valor, data_transacao, membro_id, nome_identificado) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tipo, $categoria, $descricao, $valor, $data_transacao, $membro_id, $nome_identificado]);
            
            header("Location: financeiro.php?sucesso=1");
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao cadastrar transação: " . $e->getMessage();
        }
    }
}

// Conteúdo da página...
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Nova Transação</h2>
            <p class="text-sm text-gray-500 mt-1">Adicione uma nova entrada ou saída no caixa da igreja.</p>
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
            <h2 class="text-lg font-bold text-white">Dados Gerais</h2>
            <p class="text-sm text-gray-500">Informe os detalhes da transação financeira.</p>
        </div>
        <form method="POST" class="p-8 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Tipo de Transação</label>
                    <select name="tipo" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                        <option value="entrada" <?= $tipo_url == 'entrada' ? 'selected' : '' ?>>Entrada (Receita)</option>
                        <option value="saida" <?= $tipo_url == 'saida' ? 'selected' : '' ?>>Saída (Despesa)</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Categoria</label>
                    <input name="categoria" required class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="Ex: Dízimo, Energia Elétrica..." type="text"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Valor (R$)</label>
                    <input name="valor" required step="0.01" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Ex: 150,00" type="text"/>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Data da Transação</label>
                    <input name="data_transacao" required class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none [color-scheme:dark]" type="date" value="<?= date('Y-m-d') ?>"/>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Membro Vinculado <span class="text-[10px] text-gray-500 lowercase">(opcional)</span></label>
                    <select name="membro_id" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                        <option value="">Nenhum Membro</option>
                        <?php foreach ($membros as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome Avulso <span class="text-[10px] text-gray-500 lowercase">(se não for membro)</span></label>
                    <input name="nome_identificado" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Ex: Fornecedor XYZ, Visitante..." type="text"/>
                </div>
                <div class="md:col-span-2 space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Descrição Adicional</label>
                    <input name="descricao" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Detalhes adicionais ou observações" type="text"/>
                </div>
            </div>

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-end gap-4 border-t border-darkborder">
                <a href="financeiro.php" class="w-full text-center sm:w-auto px-8 py-3 rounded-twelve border border-darkborder text-gray-400 font-semibold hover:bg-white/5 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="w-full sm:w-auto px-12 py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all">
                    Registrar Transação
                </button>
            </div>
        </form>
    </div>

<?php
require_once 'includes/footer.php';
?>
