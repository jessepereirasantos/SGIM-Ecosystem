<?php
ob_start();
session_start();
require_once 'config/database.php';

// Cadastro Público - Auto-Cadastro de Membros
$page_title = "Cadastro de Membro - SGIM";

$sucesso = false;
$erro = false;
$mensagem = "";

// Buscar Congregações e Cargos para o formulário
$congregacoes = [];
$cargos = [];

if ($is_configured) {
    try {
        $congregacoes = $pdo->query("SELECT id, nome FROM congregacoes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
        $cargos = $pdo->query("SELECT id, nome FROM cargos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silencioso se as tabelas ainda não existirem
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastro_publico') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $genero = $_POST['genero'] ?? '';
    $estado_civil = $_POST['estado_civil'] ?? '';
    $congregacao_id = $_POST['congregacao_id'] ?? null;
    $cargo_id = $_POST['cargo_id'] ?? null;
    
    if (empty($nome) || empty($telefone)) {
        $erro = true;
        $mensagem = "Nome e Telefone são obrigatórios.";
    } else {
        try {
            // Garantir que a coluna status existe e aceita 'Inativo'
            $stmt = $pdo->prepare("INSERT INTO membros (nome, email, telefone, cpf, data_nascimento, genero, estado_civil, congregacao_id, cargo_id, status, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inativo', NOW())");
            $stmt->execute([
                $nome, 
                $email, 
                $telefone, 
                $cpf, 
                $data_nascimento, 
                $genero, 
                $estado_civil, 
                $congregacao_id, 
                $cargo_id
            ]);
            $sucesso = true;
            $mensagem = "Seu cadastro foi enviado com sucesso! Ele passará por uma aprovação da administração.";
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao realizar cadastro. Verifique se os dados estão corretos ou se você já possui cadastro.";
            // Log do erro para o admin ver depois se necessário
            error_log("Erro no auto-cadastro: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
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
</head>
<body class="bg-darkbg text-gray-100 font-sans p-4 md:p-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="portal.php" class="inline-flex p-3 bg-brand/10 rounded-full text-brand mb-4 hover:scale-110 transition-transform">
                <span class="material-symbols-outlined text-3xl">church</span>
            </a>
            <h1 class="text-3xl font-bold tracking-tight">Ficha de Membro</h1>
            <p class="text-gray-400 mt-2">Preencha seus dados para solicitar o cadastro no sistema.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-xl border <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> flex items-center gap-3">
                <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
                <p class="text-sm font-semibold"><?= $mensagem ?></p>
            </div>
            <?php if ($sucesso): ?>
                <div class="text-center">
                    <a href="portal.php" class="inline-block bg-brand text-black font-bold px-8 py-3 rounded-xl hover:bg-yellow-500 transition-all">Voltar ao Portal</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$sucesso): ?>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="acao" value="cadastro_publico">
            
            <!-- Dados Pessoais -->
            <div class="bg-darkcard p-6 rounded-2xl border border-darkborder space-y-4">
                <div class="flex items-center gap-2 text-brand border-b border-darkborder pb-3 mb-2">
                    <span class="material-symbols-outlined text-sm">person</span>
                    <h2 class="text-xs font-bold uppercase tracking-widest">Dados Pessoais</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nome Completo</label>
                        <input name="nome" required class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none" type="text" placeholder="Seu nome completo">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">E-mail</label>
                        <input name="email" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none" type="email" placeholder="email@exemplo.com">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">WhatsApp</label>
                        <input name="telefone" required class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none" type="tel" placeholder="(00) 00000-0000">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">CPF</label>
                        <input name="cpf" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none" type="text" placeholder="000.000.000-00">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Data de Nascimento</label>
                        <input name="data_nascimento" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none" type="date">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Gênero</label>
                        <select name="genero" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none">
                            <option value="">Selecione...</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Feminino">Feminino</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Estado Civil</label>
                        <select name="estado_civil" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none">
                            <option value="">Selecione...</option>
                            <option value="Solteiro(a)">Solteiro(a)</option>
                            <option value="Casado(a)">Casado(a)</option>
                            <option value="Divorciado(a)">Divorciado(a)</option>
                            <option value="Viúvo(a)">Viúvo(a)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Dados Eclesiásticos -->
            <div class="bg-darkcard p-6 rounded-2xl border border-darkborder space-y-4">
                <div class="flex items-center gap-2 text-brand border-b border-darkborder pb-3 mb-2">
                    <span class="material-symbols-outlined text-sm">church</span>
                    <h2 class="text-xs font-bold uppercase tracking-widest">Dados Eclesiásticos</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Congregação</label>
                        <select name="congregacao_id" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none">
                            <option value="">Selecione sua congregação...</option>
                            <?php foreach ($congregacoes as $cong): ?>
                                <option value="<?= $cong['id'] ?>"><?= htmlspecialchars($cong['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Função/Cargo</label>
                        <select name="cargo_id" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm focus:ring-1 focus:ring-brand outline-none">
                            <option value="">Selecione seu cargo...</option>
                            <?php foreach ($cargos as $cargo): ?>
                                <option value="<?= $cargo['id'] ?>"><?= htmlspecialchars($cargo['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-brand hover:bg-yellow-500 text-black font-bold py-4 rounded-xl shadow-lg transition-all uppercase tracking-widest text-sm">
                Finalizar Cadastro
            </button>
            
            <p class="text-center text-xs text-gray-500">
                Ao clicar em finalizar, seus dados serão enviados para análise.
            </p>
        </form>
        <?php endif; ?>
        
        <footer class="mt-12 text-center text-gray-600 text-[10px] uppercase tracking-widest font-bold">
            &copy; <?= date('Y') ?> SGIM - Sistema de Gestão de Igrejas e Membros
        </footer>
    </div>
</body>
</html>
