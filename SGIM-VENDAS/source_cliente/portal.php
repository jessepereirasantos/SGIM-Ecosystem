<?php
ob_start();
session_start();
require_once 'config/database.php';

if (isset($_GET['logout_membro'])) {
    unset($_SESSION['membro_id']);
    unset($_SESSION['membro_nome']);
    header('Location: portal.php');
    exit;
}

// Portal do Membro - Acesso Público para Auto-Cadastro e Consulta
$page_title = "Portal do Membro - SGIM";

// Lógica de Postagem (Auto-Cadastro)
$sucesso = false;
$erro = false;
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'auto_cadastro') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    
    if (empty($nome) || empty($telefone)) {
        $erro = true;
        $mensagem = "Nome e Telefone são obrigatórios.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO membros (nome, email, telefone, cpf, status) VALUES (?, ?, ?, ?, 'Inativo')");
            $stmt->execute([$nome, $email, $telefone, $cpf]);
            $sucesso = true;
            $mensagem = "Cadastro realizado com sucesso! Aguarde a aprovação da sua congregação.";
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao realizar cadastro. Verifique se os dados estão corretos.";
        }
    }
}

// Buscar Eventos Ativos Públicos
$eventos = [];
if ($is_configured) {
    try {
        $stmt = $pdo->query("SELECT * FROM eventos WHERE data_inicio >= NOW() AND status = 'Agendado' AND publico = 1 ORDER BY data_inicio ASC LIMIT 5");
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: '#FFC107',
                        darkbg: '#050505',
                        darkcard: '#121212',
                        darkborder: '#1E1E1E'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-darkbg text-gray-100 p-4 md:p-8">
    <div class="max-w-4xl mx-auto space-y-12">
        <!-- Header -->
        <div class="text-center space-y-5">
            <div class="inline-flex p-5 bg-brand/10 rounded-full text-brand mb-2 animate-bounce">
                <span class="material-symbols-outlined text-5xl">church</span>
            </div>
            <h1 class="text-4xl font-black tracking-tighter text-white uppercase">Portal da Igreja</h1>
            <p class="text-gray-500 font-medium">Acesse serviços, agenda e sua carteirinha digital.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="p-5 rounded-2xl border <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> flex items-center gap-4 shadow-lg">
                <span class="material-symbols-outlined text-2xl"><?= $erro ? 'error' : 'check_circle' ?></span>
                <p class="text-sm font-bold uppercase tracking-tight"><?= $mensagem ?></p>
            </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 gap-10">
            <!-- Coluna 1: Auto-Cadastro / Login -->
            <div class="bg-darkcard p-10 rounded-3xl border border-darkborder space-y-8 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 opacity-5">
                    <span class="material-symbols-outlined text-9xl">person_add</span>
                </div>
                <div class="flex items-center gap-4 border-b border-darkborder pb-6">
                    <div class="size-12 rounded-2xl bg-brand/10 flex items-center justify-center text-brand">
                        <span class="material-symbols-outlined text-2xl font-black">edit_square</span>
                    </div>
                    <h2 class="text-2xl font-black text-white uppercase tracking-tight">Novo Membro</h2>
                </div>
                <p class="text-sm text-gray-400 leading-relaxed font-medium">
                    Deseja fazer parte da nossa família digital? Inicie sua jornada ministerial preenchendo sua ficha online.
                </p>
                <a href="cadastro" class="w-full bg-brand hover:bg-yellow-500 text-black font-black py-5 rounded-2xl shadow-xl shadow-brand/10 transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-3 active:scale-95">
                    <span class="material-symbols-outlined text-lg">edit_note</span>
                    Preencher Ficha Ministerial
                </a>
                <div class="pt-6 border-t border-darkborder text-center">
                    <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest mb-4">Membro já cadastrado?</p>
                    <a href="membro_login.php" class="inline-flex items-center gap-3 text-brand font-black text-xs uppercase tracking-widest hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-lg">badge</span>
                        Minha Carteirinha Digital
                    </a>
                </div>
            </div>

            <!-- Coluna 2: Próximos Eventos -->
            <div class="space-y-8">
                <div class="bg-darkcard p-10 rounded-3xl border border-darkborder shadow-2xl backdrop-blur-sm">
                    <div class="flex items-center gap-4 border-b border-darkborder pb-6 mb-8">
                        <div class="size-12 rounded-2xl bg-brand/10 flex items-center justify-center text-brand">
                            <span class="material-symbols-outlined text-2xl font-black">event_available</span>
                        </div>
                        <h2 class="text-2xl font-black text-white uppercase tracking-tight">Agenda Oficial</h2>
                    </div>
                    <div class="space-y-6">
                        <?php if (empty($eventos)): ?>
                            <div class="text-center py-10 opacity-40">
                                <span class="material-symbols-outlined text-5xl mb-3">calendar_today</span>
                                <p class="text-xs uppercase font-black tracking-widest">Aguardando novos eventos</p>
                            </div>
                        <?php else: foreach ($eventos as $ev): ?>
                            <div class="group bg-darkbg rounded-2xl border border-darkborder overflow-hidden transition-all hover:border-brand/50 hover:shadow-lg hover:shadow-brand/5">
                                <?php if (!empty($ev['banner_url'])): ?>
                                    <div class="w-full h-32 overflow-hidden border-b border-darkborder">
                                        <img src="<?= $ev['banner_url'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                    </div>
                                <?php endif; ?>
                                <div class="p-5 flex items-start gap-4">
                                    <div class="text-center bg-brand/5 p-3 rounded-xl border border-brand/10 min-w-[60px]">
                                        <span class="block text-brand font-black text-xl leading-none"><?= date('d', strtotime($ev['data_inicio'])) ?></span>
                                        <span class="text-[10px] uppercase font-black text-gray-500"><?= date('M', strtotime($ev['data_inicio'])) ?></span>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-black text-sm text-gray-200 uppercase tracking-tight group-hover:text-brand transition-colors"><?= htmlspecialchars($ev['titulo']) ?></h3>
                                        <div class="flex flex-col gap-1 mt-2">
                                            <p class="text-[10px] text-gray-500 font-bold flex items-center gap-1.5 uppercase tracking-wider">
                                                <span class="material-symbols-outlined text-[14px] text-brand">schedule</span>
                                                <?= date('H:i', strtotime($ev['data_inicio'])) ?>h
                                            </p>
                                            <p class="text-[10px] text-gray-500 font-bold flex items-center gap-1.5 uppercase tracking-wider">
                                                <span class="material-symbols-outlined text-[14px] text-brand">location_on</span>
                                                <?= htmlspecialchars($ev['local'] ?? 'Templo Sede') ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="bg-brand p-8 rounded-3xl text-black shadow-xl shadow-brand/10 group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-125 transition-transform duration-500">
                        <span class="material-symbols-outlined text-9xl">support_agent</span>
                    </div>
                    <h3 class="font-black text-xl uppercase tracking-tighter mb-2">Suporte Ministerial</h3>
                    <p class="text-xs font-bold opacity-70 mb-8 leading-relaxed">Teve algum problema com seu cadastro ou acesso? Procure a secretaria da sua congregação.</p>
                    <a href="https://wa.me/55000000000" target="_blank" class="w-full bg-black text-white font-black py-4 rounded-2xl block text-center uppercase tracking-widest text-[10px] hover:bg-neutral-900 transition-all shadow-lg active:scale-95">
                        Chamar no WhatsApp
                    </a>
                </div>
            </div>
        </div>
        
        <footer class="pt-10 text-center">
            <p class="text-gray-600 text-[10px] uppercase tracking-[0.3em] font-black opacity-50">
                &copy; <?= date('Y') ?> SGIM - Gestão Inteligente
            </p>
        </footer>
    </div>
</body>
</html>
