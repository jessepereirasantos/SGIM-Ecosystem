<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$mensagem = '';
$erro = false;

// 1. BUSCA CATÁLOGO DE PERMISSÕES
$permissoes_disponiveis = [];
try {
    $stmt = $pdo->query("SELECT * FROM permissoes ORDER BY modulo ASC");
    $permissoes_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensagem = "Aviso: Estrutura de permissões não detectada. Execute a atualização via OTA.";
}

// 2. PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $escopo = $_POST['escopo'] ?? 'local';
    $perms_selecionadas = $_POST['perms'] ?? [];
    $departamento_id = !empty($_POST['departamento_id']) ? $_POST['departamento_id'] : null;

    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome do cargo é obrigatório.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO cargos (nome, escopo, departamento_id, status) VALUES (?, ?, ?, 'Ativo')");
            $stmt->execute([$nome, $escopo, $departamento_id]);
            $cargo_id = $pdo->lastInsertId();

            if (!empty($perms_selecionadas)) {
                $stmtPerm = $pdo->prepare("INSERT INTO cargo_permissoes (cargo_id, permissao_id) VALUES (?, ?)");
                foreach ($perms_selecionadas as $pid) {
                    $stmtPerm->execute([$cargo_id, $pid]);
                }
            }

            $pdo->commit();
            header("Location: departamentos.php?sucesso=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = true;
            $mensagem = "Erro ao criar cargo: " . $e->getMessage();
        }
    }
}

$page_title = 'SGIM - Gestão de Cargos e Permissões';
$current_page = 'departamentos';

require_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-brand text-4xl">verified_user</span>
                Novo Cargo Ministerial
            </h2>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">Sincronização de Autoridade e Níveis de Acesso</p>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl bg-brand/10 border border-brand/20 text-brand flex items-center gap-3">
            <span class="material-symbols-outlined">info</span>
            <p class="text-sm font-bold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Coluna 1: Dados do Cargo -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-6 shadow-xl">
                    <h3 class="text-[10px] font-black text-brand uppercase tracking-widest mb-6 border-b border-white/5 pb-4">Identificação</h3>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Nome do Cargo</label>
                            <input name="nome" required class="w-full px-4 py-3 rounded-xl border border-darkborder bg-black text-white focus:ring-2 focus:ring-brand outline-none transition-all font-bold" placeholder="Ex: Pastor Local" type="text"/>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Escopo de Visão</label>
                            <select name="escopo" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-black text-white focus:ring-2 focus:ring-brand outline-none cursor-pointer font-bold">
                                <option value="local">LOCAL (Apenas Congregação)</option>
                                <option value="global">GLOBAL (Todo o Ministério)</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-brand uppercase tracking-widest mb-2">Preset Inteligente</label>
                            <select id="preset_nivel" onchange="applyPreset(this.value)" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-black text-white font-bold focus:ring-2 focus:ring-brand outline-none cursor-pointer">
                                <option value="" style="background: #000; color: #fff;">Personalizado</option>
                                <option value="admin_total" style="background: #000; color: #fff;">Admin Total</option>
                                <option value="pastor_local" style="background: #000; color: #fff;">Pastor Local</option>
                                <option value="secretario_local" style="background: #000; color: #fff;">Secretário Local</option>
                                <option value="tesoureiro_local" style="background: #000; color: #fff;">Tesoureiro Local</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-6 rounded-2xl bg-gradient-to-br from-brand/10 to-transparent border border-brand/20 shadow-lg">
                    <h4 class="text-brand font-bold text-sm mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">tips_and_updates</span>
                        Diretriz Ministerial
                    </h4>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        Ao selecionar um **Preset**, o sistema marcará automaticamente as permissões recomendadas para o cargo.
                    </p>
                </div>
            </div>

            <!-- Coluna 2: Matriz de Autoridade -->
            <div class="lg:col-span-8">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-2xl">
                    <div class="flex items-center justify-between mb-8 border-b border-white/5 pb-6">
                        <div>
                            <h3 class="text-xl font-bold text-white tracking-tight">Matriz de Permissões</h3>
                            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">Habilite os poderes de acesso deste cargo</p>
                        </div>
                        <button type="button" onclick="toggleAll()" class="px-4 py-2 rounded-lg bg-white/5 border border-darkborder text-[10px] font-bold text-brand hover:bg-brand/10 transition-all uppercase">Marcar/Desmarcar Todos</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php 
                        $modulos = [];
                        foreach ($permissoes_disponiveis as $p) {
                            $modulos[$p['modulo']][] = $p;
                        }
                        
                        foreach ($modulos as $modulo => $acoes): 
                        ?>
                            <div class="p-5 rounded-xl bg-black border border-darkborder group hover:border-brand/40 transition-all">
                                <div class="flex items-center gap-3 mb-5">
                                    <div class="size-8 rounded-lg bg-brand/10 flex items-center justify-center text-brand">
                                        <span class="material-symbols-outlined text-sm">
                                            <?= ($modulo == 'financeiro') ? 'payments' : (($modulo == 'membros') ? 'group' : 'shield_person') ?>
                                        </span>
                                    </div>
                                    <h4 class="font-black text-white uppercase tracking-widest text-[10px]"><?= $modulo ?></h4>
                                </div>
                                
                                <div class="space-y-4">
                                    <?php foreach ($acoes as $acao): ?>
                                        <label class="flex items-center gap-4 cursor-pointer group/item">
                                            <div class="relative flex items-center justify-center">
                                                <input type="checkbox" name="perms[]" value="<?= $acao['id'] ?>" 
                                                       data-modulo="<?= $modulo ?>" data-acao="<?= $acao['acao'] ?>"
                                                       class="peer appearance-none size-5 rounded-md border-2 border-darkborder bg-darkcard checked:bg-brand checked:border-brand transition-all cursor-pointer">
                                                <span class="material-symbols-outlined absolute text-[14px] text-black font-black opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none">check</span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-white group-hover/item:text-brand transition-colors">
                                                    <?= ucfirst($acao['acao']) ?>
                                                </span>
                                                <span class="text-[10px] text-gray-100 font-bold leading-none mt-1 opacity-90">
                                                    <?= $acao['descricao'] ?>
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-10 flex items-center justify-end gap-6 pt-6 border-t border-white/5">
                        <a href="departamentos.php" class="text-xs font-bold text-gray-500 hover:text-white transition-colors uppercase tracking-widest">Cancelar</a>
                        <button type="submit" class="px-10 py-4 rounded-xl bg-brand hover:bg-brand-dark text-black font-black shadow-lg shadow-brand/20 transition-all uppercase text-xs tracking-widest">
                            Salvar Autoridade
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleAll() {
        const checks = document.querySelectorAll('input[type="checkbox"]');
        const firstValue = checks[0].checked;
        checks.forEach(c => c.checked = !firstValue);
    }

    function applyPreset(level) {
        const checks = document.querySelectorAll('input[type="checkbox"]');
        const escopo = document.querySelector('select[name="escopo"]');
        checks.forEach(c => c.checked = false);
        
        switch(level) {
            case 'admin_total':
                checks.forEach(c => c.checked = true);
                escopo.value = 'global';
                break;
            case 'pastor_local':
                checks.forEach(c => {
                    if (['membros', 'financeiro', 'eventos'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
            case 'secretario_local':
                checks.forEach(c => {
                    if (['membros', 'eventos'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
            case 'tesoureiro_local':
                checks.forEach(c => {
                    if (['financeiro'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
        }
    }
</script>

<?php require_once 'includes/header.php'; ?>