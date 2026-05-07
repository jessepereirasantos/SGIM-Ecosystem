<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pass, $user['senha'])) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nome'] = $user['nome'];
        $_SESSION['usuario_email'] = $user['email'];
        $_SESSION['usuario_nivel'] = $user['nivel'];

        if ($user['nivel'] === 'admin') {
            $_SESSION['admin_logged'] = true;
            header("Location: admin.php");
        } else {
            header("Location: cliente/dashboard.php");
        }
        exit;
    } else {
        $error = "E-mail ou senha inválidos.";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>SGIM Admin Master - Login</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#ec5b13",
                        "brand": "#FFC107",
                        "background-dark": "#050505",
                        "card-dark": "#121212",
                    },
                    fontFamily: { "sans": ["Inter", "sans-serif"] },
                },
            },
        }
    </script>
</head>
<body class="bg-background-dark min-h-screen flex items-center justify-center font-sans antialiased">
    <div class="w-full max-w-md px-6">
        <div class="flex flex-col items-center mb-8">
            <div class="bg-brand/10 p-3 rounded-xl mb-4">
                <span class="material-symbols-outlined text-brand text-4xl" style="font-variation-settings: 'FILL' 1">shield_person</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-100 tracking-tight">SGIM Admin Master</h1>
            <p class="text-slate-400 text-sm mt-1">Gestão Inteligente de Vendas e Licenças</p>
        </div>

        <div class="bg-card-dark border border-slate-800/50 p-8 rounded-xl shadow-2xl">
            <h2 class="text-xl font-semibold text-slate-100 mb-6">Acesse sua conta</h2>
            
            <?php if(isset($error)): ?>
                <div class="mb-4 p-3 rounded bg-red-500/10 border border-red-500/20 text-red-500 text-sm"><?= $error ?></div>
            <?php endif; ?>

            <form action="login.php" class="space-y-5" method="POST">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">E-mail</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-500 text-xl">mail</span>
                        </div>
                        <input class="block w-full pl-10 pr-3 py-3 border border-slate-800 rounded-lg bg-black/40 text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-colors text-sm" name="email" placeholder="seu@email.com.br" type="email" required/>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-sm font-medium text-slate-300">Senha</label>
                        <a class="text-xs font-semibold text-brand hover:text-brand/80 transition-colors" href="#" onclick="document.getElementById('modal-reset').style.display='flex'; return false;">Esqueci minha senha</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-500 text-xl">lock</span>
                        </div>
                        <input class="block w-full pl-10 pr-3 py-3 border border-slate-800 rounded-lg bg-black/40 text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-colors text-sm" name="password" placeholder="••••••••" type="password" required/>
                    </div>
                </div>
                <button class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-black bg-brand hover:bg-brand-dark focus:outline-none transition-all uppercase tracking-wider" type="submit">
                    Entrar
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL RECUPERAÇÃO DE SENHA -->
    <div id="modal-reset" style="display:none;" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-card-dark border border-slate-800 max-w-md w-full p-8 rounded-xl shadow-2xl relative">
            <button onclick="document.getElementById('modal-reset').style.display='none'" class="absolute top-4 right-4 text-slate-500 hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
            <div class="flex flex-col items-center mb-6">
                <div class="bg-brand/10 p-3 rounded-xl mb-3">
                    <span class="material-symbols-outlined text-brand text-3xl">lock_reset</span>
                </div>
                <h3 class="text-lg font-bold text-white">Recuperar Senha</h3>
                <p class="text-slate-400 text-sm mt-1 text-center">Informe o e-mail cadastrado. Enviaremos uma nova senha temporária.</p>
            </div>

            <div id="reset-alert" class="hidden mb-4 p-3 rounded-lg text-sm font-medium"></div>

            <form id="form-reset" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">E-mail cadastrado</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-500 text-xl">mail</span>
                        </div>
                        <input id="reset-email" class="block w-full pl-10 pr-3 py-3 border border-slate-800 rounded-lg bg-black/40 text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-brand/50 focus:border-brand transition-colors text-sm" placeholder="seu@email.com.br" type="email" required/>
                    </div>
                </div>
                <button type="submit" id="btn-reset" class="w-full py-3 px-4 rounded-lg text-sm font-bold text-black bg-brand hover:bg-yellow-500 transition-all uppercase tracking-wider flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-base">send</span>
                    Enviar Nova Senha
                </button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('form-reset').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = document.getElementById('reset-email').value;
        const btn = document.getElementById('btn-reset');
        const alert = document.getElementById('reset-alert');

        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-base">autorenew</span> Enviando...';
        alert.className = 'hidden mb-4 p-3 rounded-lg text-sm font-medium';

        const form = new FormData();
        form.append('email', email);

        fetch('reset_password.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                alert.classList.remove('hidden');
                if (data.success) {
                    alert.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-green-500/10 border border-green-500/20 text-green-400';
                    alert.textContent = data.message;
                    btn.innerHTML = '<span class="material-symbols-outlined text-base">check_circle</span> Enviado!';
                } else {
                    alert.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-500/10 border border-red-500/20 text-red-400';
                    alert.textContent = data.message;
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined text-base">send</span> Enviar Nova Senha';
                }
            })
            .catch(() => {
                alert.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-500/10 border border-red-500/20 text-red-400';
                alert.textContent = 'Erro de conexão. Tente novamente.';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-base">send</span> Enviar Nova Senha';
            });
    });
    </script>
</body>
</html>
