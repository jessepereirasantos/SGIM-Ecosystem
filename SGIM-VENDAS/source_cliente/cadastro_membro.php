<?php
/**
 * SGIM-CLIENTE: Portal Público de Auto-Cadastro de Membros
 * Permite que novos membros se cadastrem remotamente.
 */
session_start();
require_once 'config/database.php';

$mensagem = '';
$erro = false;

// Buscar Cargos e Congregações para o formulário
try {
    $stmtCargos = $pdo->query("SELECT id, nome FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC");
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

    $stmtCong = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC");
    $congregacoes = $stmtCong->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtIgreja = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'nome_igreja'");
    $nome_igreja = $stmtIgreja->fetchColumn() ?: 'SGIM Master';
} catch (Exception $e) {
    $nome_igreja = 'SGIM Master';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $congregacao_id = $_POST['congregacao_id'] ?? null;
    
    if (empty($nome) || empty($telefone)) {
        $erro = true;
        $mensagem = "Nome e Telefone são campos obrigatórios.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO membros (nome, email, telefone, cpf, congregacao_id, status, data_cadastro) VALUES (?, ?, ?, ?, ?, 'Inativo', NOW())");
            $stmt->execute([$nome, $email, $telefone, $cpf, $congregacao_id]);
            
            $sucesso_cadastro = true;
            $mensagem = "Cadastro enviado com sucesso! Aguarde a aprovação da administração.";
        } catch (Exception $e) {
            $erro = true;
            $mensagem = "Erro ao enviar cadastro: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Auto-Cadastro de Membro - <?= htmlspecialchars($nome_igreja) ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: #f8fafc; }
        .glass-card { background: rgba(18, 18, 18, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255, 193, 7, 0.1); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-2xl w-full glass-card p-10 rounded-[2.5rem] shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-yellow-500 to-yellow-600"></div>

        <?php if (isset($sucesso_cadastro)): ?>
            <div class="text-center py-10">
                <div class="size-20 bg-green-500/10 rounded-full flex items-center justify-center text-green-500 mx-auto mb-6">
                    <span class="material-symbols-outlined text-5xl">verified</span>
                </div>
                <h2 class="text-3xl font-black text-white mb-4">Solicitação Enviada!</h2>
                <p class="text-gray-400 mb-8"><?= $mensagem ?></p>
                
                <div class="flex flex-col gap-4 max-w-xs mx-auto">
                    <button id="installApp" style="display:none;" class="bg-white text-black font-black py-4 px-8 rounded-2xl uppercase tracking-widest hover:scale-[1.02] transition-all shadow-lg flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">download_for_offline</span>
                        Baixar o App
                    </button>
                    <button onclick="location.reload()" class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 font-black py-4 px-8 rounded-2xl uppercase tracking-widest hover:scale-[1.02] transition-all">Novo Cadastro</button>
                </div>
            </div>

            <script>
                let deferredPrompt;
                const installBtn = document.getElementById('installApp');

                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    deferredPrompt = e;
                    installBtn.style.display = 'flex';
                });

                installBtn.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        if (outcome === 'accepted') {
                            installBtn.style.display = 'none';
                        }
                        deferredPrompt = null;
                    }
                });
            </script>
        <?php else: ?>
            <div class="text-center mb-10">
                <div class="size-16 bg-yellow-500/10 rounded-2xl flex items-center justify-center text-yellow-500 mx-auto mb-4">
                    <span class="material-symbols-outlined text-3xl">person_add</span>
                </div>
                <h1 class="text-2xl font-black text-white"><?= htmlspecialchars($nome_igreja) ?></h1>
                <p class="text-sm text-gray-500 uppercase tracking-widest font-bold mt-1">Ficha de Cadastro de Membro</p>
            </div>

            <?php if ($erro): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold flex items-center gap-3">
                    <span class="material-symbols-outlined">error</span>
                    <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Nome Completo</label>
                        <input type="text" name="nome" required class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-yellow-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">E-mail</label>
                        <input type="email" name="email" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-yellow-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">WhatsApp / Telefone</label>
                        <input type="text" name="telefone" required placeholder="(00) 00000-0000" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-yellow-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">CPF</label>
                        <input type="text" name="cpf" placeholder="000.000.000-00" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-yellow-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2">Sua Congregação</label>
                        <select name="congregacao_id" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-yellow-500 outline-none transition-all">
                            <option value="">Selecione...</option>
                            <?php foreach ($congregacoes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="w-full bg-yellow-500 text-black font-black py-5 rounded-2xl uppercase tracking-widest hover:scale-[1.02] transition-all shadow-xl shadow-yellow-500/10 mt-4">
                    Enviar Cadastro
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
