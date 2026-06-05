<?php
/**
 * SGIM VENDAS - SISTEMA DE CUPONS E CAMPANHAS
 */
require_once 'config/database.php';

$mensagem = '';
$erro = '';

// ── 1. EXCLUSÃO DE CUPOM ──
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: cupons.php?msg=Cupom excluído com sucesso!");
        exit;
    } catch (Exception $e) {
        $erro = "Erro ao excluir cupom: " . $e->getMessage();
    }
}

// ── 2. SALVAMENTO DE CUPOM (CRIAR E EDITAR) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_coupon') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $valor = (float)($_POST['valor'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'porcentagem';
    $limite_usos = isset($_POST['limite_usos']) ? (int)$_POST['limite_usos'] : 0;
    $validade = !empty($_POST['validade']) ? $_POST['validade'] : null;

    if (empty($codigo) || $valor <= 0) {
        $erro = "Revisão necessária: Por favor, informe o código e o valor do desconto.";
    } else {
        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE cupons SET codigo = ?, valor = ?, tipo = ?, limite_usos = ?, validade = ? WHERE id = ?");
                $stmt->execute([$codigo, $valor, $tipo, $limite_usos, $validade, $id]);
                header("Location: cupons.php?msg=Cupom atualizado com sucesso!");
                exit;
            } else {
                // INSERT (Verificar duplicidade)
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM cupons WHERE codigo = ?");
                $stmtCheck->execute([$codigo]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $erro = "Já existe um cupom ativo com o código '$codigo'.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cupons (codigo, valor, tipo, limite_usos, validade) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo, $valor, $tipo, $limite_usos, $validade]);
                    header("Location: cupons.php?msg=Cupom criado com sucesso!");
                    exit;
                }
            }
        } catch (Exception $e) {
            $erro = "Erro ao salvar cupom: " . $e->getMessage();
        }
    }
}

if (isset($_GET['msg'])) {
    $mensagem = $_GET['msg'];
}

// ── 3. LISTAGEM DE CUPONS ──
$stmt = $pdo->query("SELECT * FROM cupons ORDER BY id DESC");
$cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'cupons';
include 'templates/header.php';
?>

<div class="flex" x-data="{ 
    showModal: false, 
    isEdit: false,
    couponId: '',
    couponCode: '',
    couponVal: '',
    couponType: 'porcentagem',
    couponLimit: 0,
    couponValidade: '',
    openCreate() {
        this.isEdit = false;
        this.couponId = '';
        this.couponCode = '';
        this.couponVal = '';
        this.couponType = 'porcentagem';
        this.couponLimit = 0;
        this.couponValidade = '';
        this.showModal = true;
    },
    openEdit(c) {
        this.isEdit = true;
        this.couponId = c.id;
        this.couponCode = c.codigo;
        this.couponVal = c.valor;
        this.couponType = c.tipo;
        this.couponLimit = c.limite_usos;
        this.couponValidade = c.validade || '';
        this.showModal = true;
    }
}">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Top Navigation -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-2 text-on-surface-variant font-bold text-xs uppercase tracking-widest">
                <span class="material-symbols-outlined text-primary">confirmation_number</span>
                Campaign Management
            </div>
        </header>

        <div class="p-10 max-w-[1600px] mx-auto space-y-6">
            <div class="flex justify-between items-end">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Cupons & <span class="text-primary">Descontos</span></h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Gestão de códigos promocionais e estratégias de conversão de vendas.</p>
                </div>
                <button @click="openCreate()" class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-bold hover:opacity-90 transition-all flex items-center gap-2 text-sm shadow-xl shadow-primary/20">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    CRIAR NOVO CUPOM
                </button>
            </div>

            <!-- Alertas e Mensagens -->
            <?php if ($mensagem): ?>
                <div class="p-4 rounded-lg bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
                    <span class="material-symbols-outlined">check_circle</span>
                    <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    <p class="text-sm font-semibold"><?= htmlspecialchars($erro) ?></p>
                </div>
            <?php endif; ?>

            <!-- Grid de Cupons -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php if (count($cupons) > 0): ?>
                    <?php foreach ($cupons as $c): ?>
                    <div class="glass-card p-6 rounded-xl hover:border-primary/30 transition-all group relative overflow-hidden flex flex-col justify-between h-56">
                        <div class="absolute -right-10 -top-10 size-24 bg-primary/5 rounded-full blur-2xl"></div>
                        
                        <div class="relative z-10">
                            <div class="flex justify-between items-start mb-4">
                                <span class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-black rounded-lg uppercase tracking-widest border border-primary/20"><?= htmlspecialchars($c['codigo']) ?></span>
                                <span class="text-secondary text-base font-black">
                                    <?= htmlspecialchars($c['valor']) ?><?= $c['tipo'] === 'porcentagem' ? '% OFF' : ' R$ OFF' ?>
                                </span>
                            </div>
                            
                            <div class="space-y-1.5 mt-4">
                                <div class="flex items-center gap-2">
                                    <div class="size-1.5 rounded-full <?= (empty($c['validade']) || strtotime($c['validade']) >= strtotime('today')) ? 'bg-secondary' : 'bg-red-500' ?>"></div>
                                    <span class="text-[10px] text-on-surface font-bold">
                                        <?= (empty($c['validade']) || strtotime($c['validade']) >= strtotime('today')) ? 'Ativo' : 'Expirado' ?>
                                    </span>
                                </div>
                                <?php if ($c['validade']): ?>
                                    <p class="text-[10px] text-on-surface-variant opacity-60">Validade: <?= date('d/m/Y', strtotime($c['validade'])) ?></p>
                                <?php endif; ?>
                                <p class="text-[10px] text-on-surface-variant opacity-60">Limite: <?= $c['limite_usos'] > 0 ? $c['limite_usos'] . ' usos' : 'Ilimitado' ?></p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-outline-variant/10 flex justify-between items-center relative z-10">
                            <span class="text-[9px] text-on-surface-variant uppercase font-bold">Usos: <?= $c['usos_atuais'] ?? 0 ?></span>
                            <div class="flex items-center gap-2">
                                <button @click="openEdit(<?= htmlspecialchars(json_encode($c)) ?>)" class="text-on-surface-variant hover:text-primary transition-all p-1">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                <a href="cupons.php?action=delete&id=<?= $c['id'] ?>" onclick="return confirm('Deseja realmente excluir o cupom <?= htmlspecialchars($c['codigo']) ?>?');" class="text-on-surface-variant hover:text-error transition-all p-1">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-20 glass-card rounded-xl text-center opacity-40">
                        <span class="material-symbols-outlined text-5xl mb-4 text-primary">local_offer</span>
                        <p class="text-sm font-bold italic tracking-tighter">Nenhum cupom de desconto cadastrado.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── MODAL: CRIAR OU EDITAR CUPOM ── -->
        <div 
            x-show="showModal" 
            x-transition
            style="display: none;" 
            class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-black/80 backdrop-blur-sm"
        >
            <div @click.away="showModal = false" class="bg-surface border border-outline-variant/20 max-w-md w-full rounded-2xl p-8 space-y-6 shadow-2xl relative">
                <div class="flex items-center justify-between border-b border-outline-variant/10 pb-4">
                    <h3 class="text-title-sm text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary" x-text="isEdit ? 'edit_document' : 'add_circle'"></span>
                        <span x-text="isEdit ? 'Editar Cupom' : 'Criar Novo Cupom'"></span>
                    </h3>
                    <button @click="showModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_coupon">
                    <input type="hidden" name="id" x-model="couponId">

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block">Código do Cupom</label>
                        <input 
                            type="text" 
                            name="codigo" 
                            x-model="couponCode" 
                            required 
                            placeholder="EX: PASCOA10" 
                            class="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg px-4 py-3 text-sm text-white focus:border-primary outline-none transition-all font-bold uppercase"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block">Tipo</label>
                            <select 
                                name="tipo" 
                                x-model="couponType" 
                                class="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg px-4 py-3 text-sm text-white focus:border-primary outline-none cursor-pointer"
                                style="color-scheme: dark;"
                            >
                                <option value="porcentagem">Porcentagem (%)</option>
                                <option value="fixo">Fixo (R$)</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block">Valor Desconto</label>
                            <input 
                                type="number" 
                                name="valor" 
                                x-model="couponVal" 
                                step="0.01" 
                                required 
                                placeholder="10.00" 
                                class="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg px-4 py-3 text-sm text-white focus:border-primary outline-none transition-all"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block">Limite de Usos</label>
                            <input 
                                type="number" 
                                name="limite_usos" 
                                x-model="couponLimit" 
                                placeholder="0 para ilimitado" 
                                class="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg px-4 py-3 text-sm text-white focus:border-primary outline-none transition-all"
                            >
                        </div>

                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block">Data Validade</label>
                            <input 
                                type="date" 
                                name="validade" 
                                x-model="couponValidade" 
                                class="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg px-4 py-3 text-sm text-white focus:border-primary outline-none transition-all"
                                style="color-scheme: dark;"
                            >
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-outline-variant/10">
                        <button type="button" @click="showModal = false" class="px-6 py-2.5 rounded-lg bg-surface-container-high hover:bg-surface-bright text-on-surface font-bold transition-all text-sm">
                            Cancelar
                        </button>
                        <button type="submit" class="px-8 py-2.5 rounded-lg bg-primary text-on-primary font-black hover:opacity-90 transition-all text-sm shadow-xl shadow-primary/10">
                            Salvar Cupom
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
