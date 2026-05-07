<?php
ob_start();
session_start();

// O db.php já foi blindado para ser passivo.
require_once 'config/db.php';

// 1. Verificação de Conexão - Garantir que a flag é respeitada
$db_error_message = null;
if (!$is_configured) {
    $db_error_message = "O sistema não conseguiu conectar ao banco de dados MySQL.";
} else {
    // Se o banco está OK, limpamos qualquer mensagem de erro residual
    $db_error_message = null;
}

// 2. Lógica de Redirecionamento de Entrada (Evita Loops)
if ($is_configured && isset($_SESSION['user_id'])) {
    if (ob_get_length()) ob_end_clean();
    header('Location: dashboard.php');
    exit;
}

// 3. Se não houver banco E não houver arquivo .installed, manda para o setup
if (!$is_configured && !$is_installed_local) {
    if (ob_get_length()) ob_end_clean();
    header('Location: setup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao'])) {
    if (!$is_configured) {
        $erro = "Não é possível realizar login sem conexão com o banco de dados. " . $db_error_message;
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        
        if (empty($email) || empty($senha)) {
            $erro = "Por favor, preencha todos os campos.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($senha, $user['senha'])) {
                $two_factor_enabled = $user['two_factor_enabled'] ?? 0;
                if ($two_factor_enabled && !empty($user['two_factor_secret'])) {
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['pending_2fa_user_nome'] = $user['nome'];
                    $_SESSION['pending_2fa_user_nivel'] = $user['nivel_acesso'];
                    $_SESSION['pending_2fa_secret'] = $user['two_factor_secret'];
                    $show_2fa_login = true;
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nome'] = $user['nome'];
                    $_SESSION['user_nivel'] = $user['nivel_acesso'];
                    if (ob_get_length()) ob_end_clean();
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $erro = "E-mail ou senha inválidos.";
            }
        }
    }
}

// Processar código 2FA no Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'verify_2fa_login') {
    // ... existente ...
}

// Lógica de Recuperação de Senha (v2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'forgot_password') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $erro = "Por favor, informe seu e-mail de compra.";
    } else {
        try {
            // Buscar usuário no banco de VENDAS (que é o mestre)
            $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                require_once __DIR__ . '/../SGIM-VENDAS/includes/EmailService.php';
                
                // Gerar nova senha temporária
                $nova_senha = substr(md5(uniqid(rand(), true)), 0, 8);
                $hash_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                // Atualizar no banco
                $update = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $update->execute([$hash_senha, $user['id']]);
                
                // Enviar e-mail
                if (EmailService::sendPasswordReset($email, $user['nome'], $nova_senha)) {
                    $sucesso_recuperacao = "Uma nova senha temporária foi enviada para seu e-mail.";
                } else {
                    $erro = "Falha ao enviar e-mail. Verifique as configurações de SMTP.";
                }
            } else {
                $erro = "E-mail não localizado em nossa base de clientes.";
            }
        } catch (Exception $e) {
            $erro = "Erro interno: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login - SGIM Cliente</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#FFC107",
                        "background-light": "#f8f6f6",
                        "background-dark": "#0a0a0a",
                        "surface-dark": "#1a1a1a",
                        "border-dark": "#2d2d2d"
                    },
                    fontFamily: {
                        "display": ["Public Sans", "sans-serif"]
                    }
                }
            }
        }
    </script>
    <style>body { font-family: 'Public Sans', sans-serif; }</style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-[440px] flex flex-col gap-8">
        <div class="flex flex-col items-center gap-4">
            <div class="bg-primary/10 p-4 rounded-xl border border-primary/20">
                <span class="material-symbols-outlined text-primary text-5xl">church</span>
            </div>
            <div class="text-center">
                <h1 class="text-slate-900 dark:text-slate-100 text-3xl font-bold tracking-tight">SGIM Cliente</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm">Gestão de Igrejas e Membros</p>
            </div>
        </div>
        
        <div class="bg-white dark:bg-surface-dark border border-slate-200 dark:border-border-dark rounded-xl p-8 shadow-xl">
            <?php if ($db_error_message): ?>
                <div class="bg-amber-500/10 border border-amber-500/20 text-amber-500 p-4 rounded-xl mb-6 text-sm flex items-start gap-3">
                    <span class="material-symbols-outlined text-xl">warning</span>
                    <div>
                        <p class="font-bold">Aviso de Conexão</p>
                        <p class="opacity-80"><?= $db_error_message ?></p>
                        <a href="setup.php" class="inline-block mt-2 text-primary hover:underline font-bold">Ir para Configuração (Setup)</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($show_2fa_login) && $show_2fa_login): ?>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-2">Verificação 2FA</h2>
                <p class="text-xs text-gray-500 mb-6 uppercase tracking-widest font-bold">Insira o código do seu aplicativo de autenticação.</p>
                
                <?php if (isset($erro)): ?>
                    <div class="bg-red-500/10 text-red-500 p-3 rounded mb-4 text-sm">
                        <?= $erro ?>
                    </div>
                <?php endif; ?>

                <form class="flex flex-col gap-5" method="POST">
                    <input type="hidden" name="acao" value="verify_2fa_login">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Código de 6 dígitos</label>
                        <input name="2fa_code" maxlength="6" class="w-full px-4 py-4 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-border-dark rounded-lg text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all text-center text-3xl font-mono tracking-[0.5em]" placeholder="000000" type="text" required autofocus />
                    </div>
                    <button class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-3.5 rounded-lg transition-colors uppercase tracking-wider text-sm mt-2" type="submit">
                        CONFIRMAR CÓDIGO
                    </button>
                    <a href="login.php" class="text-center text-xs text-slate-500 hover:text-primary transition-all">Voltar ao Login</a>
                </form>
            <?php else: ?>
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">Acesse sua conta</h2>
                <?php if (isset($erro)): ?>
                    <div class="bg-red-500/10 text-red-500 p-3 rounded mb-4 text-sm">
                        <?= $erro ?>
                    </div>
                <?php endif; ?>
                <form class="flex flex-col gap-5" method="POST">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">E-mail corporativo</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
                            <input name="email" class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-border-dark rounded-lg text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-600" placeholder="exemplo@sgim.com.br" type="email" required />
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between items-center">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Senha</label>
                            <button type="button" onclick="document.getElementById('modal-forgot').style.display='flex'" class="text-xs font-semibold text-primary hover:underline bg-transparent border-none p-0 cursor-pointer">Esqueceu a senha?</button>
                        </div>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">lock</span>
                            <input id="senha_login" name="senha" class="w-full pl-10 pr-12 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-border-dark rounded-lg text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all placeholder:text-slate-400 dark:placeholder:text-slate-600" placeholder="••••••••" type="password" required />
                            <button type="button" onclick="togglePassword('senha_login', 'eye_login')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary focus:outline-none">
                                <span id="eye_login" class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>
                    <button class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-3.5 rounded-lg transition-colors uppercase tracking-wider text-sm mt-2" type="submit">
                        ENTRAR NO SISTEMA
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL ESQUECI MINHA SENHA -->
    <div id="modal-forgot" style="display:none;" class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-surface-dark border border-slate-200 dark:border-border-dark max-w-md w-full p-8 rounded-2xl shadow-2xl animate-in zoom-in duration-300">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-slate-900 dark:text-slate-100">Recuperar Senha</h3>
                <button onclick="document.getElementById('modal-forgot').style.display='none'" class="text-slate-500 hover:text-red-500 transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Informe o e-mail que você utilizou para comprar o sistema. Enviaremos uma nova senha temporária para ele.</p>

            <form method="POST" class="flex flex-col gap-5">
                <input type="hidden" name="acao" value="forgot_password">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Seu e-mail de compra</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">mail</span>
                        <input name="email" class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-border-dark rounded-lg text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" placeholder="exemplo@email.com" type="email" required />
                    </div>
                </div>
                <button class="w-full bg-primary hover:bg-primary/90 text-background-dark font-bold py-3.5 rounded-lg transition-colors uppercase tracking-wider text-sm shadow-lg shadow-primary/20" type="submit">
                    ENVIAR NOVA SENHA
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($sucesso_recuperacao)): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'E-mail Enviado!',
            text: '<?= $sucesso_recuperacao ?>',
            background: '#121212',
            color: '#fff',
            confirmButtonColor: '#FFC107'
        });
    </script>
    <?php endif; ?>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }
    </script>
</body>
</html>
