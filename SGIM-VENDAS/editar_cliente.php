<?php
require_once 'config/database.php';
$current_page = 'clientes';

$id = (int)($_GET['id'] ?? 0);
$msg = "";

// Processar Atualização
if (isset($_POST['salvar'])) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $dominio = $_POST['dominio'];
    $licenca = $_POST['licenca'];

    try {
        $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, dominio = ?, license_key = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $dominio, $licenca, $id]);
        $msg = "<div class='p-4 bg-secondary/10 text-secondary rounded-xl mb-6 text-xs font-bold border border-secondary/20'>✅ Dados atualizados com sucesso.</div>";
    } catch (Exception $e) {
        $msg = "<div class='p-4 bg-error/10 text-error rounded-xl mb-6 text-xs font-bold border border-error/20'>🛑 Erro ao atualizar: " . $e->getMessage() . "</div>";
    }
}

// Buscar dados atuais
$cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$cliente->execute([$id]);
$c = $cliente->fetch(PDO::FETCH_ASSOC);

if (!$c) { die("Cliente não encontrado."); }

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-2">
                <a href="clientes.php" class="text-on-surface-variant hover:text-primary transition-all">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Edição de Parceiro</span>
            </div>
        </header>

        <div class="p-10 max-w-2xl mx-auto">
            <div class="mb-10 text-center">
                <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Editar <span class="text-primary">Cliente</span></h2>
                <p class="text-on-surface-variant font-body-md opacity-60">Modifique os dados de acesso e licenciamento do domínio.</p>
            </div>

            <?= $msg ?>

            <div class="glass-card p-10 rounded-3xl">
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest mb-2 block">Nome Completo / Empresa</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($c['nome']) ?>" class="w-full bg-surface-container border border-outline-variant/20 rounded-xl px-5 py-4 text-on-surface focus:outline-none focus:border-primary/50 transition-all text-sm font-bold">
                    </div>

                    <div>
                        <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest mb-2 block">E-mail de Contato</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($c['email']) ?>" class="w-full bg-surface-container border border-outline-variant/20 rounded-xl px-5 py-4 text-on-surface focus:outline-none focus:border-primary/50 transition-all text-sm font-bold">
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest mb-2 block">Domínio Autorizado</label>
                            <input type="text" name="dominio" value="<?= htmlspecialchars($c['dominio']) ?>" class="w-full bg-surface-container border border-outline-variant/20 rounded-xl px-5 py-4 text-on-surface font-mono focus:outline-none focus:border-primary/50 transition-all text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest mb-2 block">Chave de Licença</label>
                            <input type="text" name="licenca" value="<?= htmlspecialchars($c['license_key']) ?>" class="w-full bg-surface-container border border-outline-variant/20 rounded-xl px-5 py-4 text-primary font-mono focus:outline-none focus:border-primary/50 transition-all text-sm">
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" name="salvar" class="w-full py-5 bg-primary text-on-primary font-black rounded-2xl shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all tracking-widest uppercase text-xs">
                            SALVAR ALTERAÇÕES
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
