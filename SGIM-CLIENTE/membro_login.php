<?php
// AUTO-PONTE: Se existir uma versão mais nova ativa pelo OTA, desvia para ela
$bridge = __DIR__ . '/releases/current/' . basename(__FILE__);
if (file_exists($bridge) && strpos(__DIR__, 'releases') === false) {
    require_once $bridge;
    exit;
}

session_start();
require_once __DIR__ . '/config/database.php';

// Redireciona se já estiver logado como membro
if (isset($_SESSION['membro_id'])) {
    header('Location: membro_dashboard.php');
    exit;
}

$erro = false;
$mensagem = '';
$pendente = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpa o CPF para comparar apenas números
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';

    if (empty($cpf) || empty($data_nascimento)) {
        $erro = true;
        $mensagem = "Por favor, informe seu CPF e Data de Nascimento.";
    } else {
        try {
            // Busca o membro pelo CPF limpo e data de nascimento
            $stmt = $pdo->prepare("SELECT id, nome, status, cpf FROM membros WHERE data_nascimento = ?");
            $stmt->execute([$data_nascimento]);
            $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $membro_encontrado = null;
            foreach ($membros as $m) {
                $m_cpf_limpo = preg_replace('/\D/', '', $m['cpf']);
                if ($m_cpf_limpo === $cpf) {
                    $membro_encontrado = $m;
                    break;
                }
            }

            if ($membro_encontrado) {
                if ($membro_encontrado['status'] === 'Ativo') {
                    $_SESSION['membro_id'] = $membro_encontrado['id'];
                    $_SESSION['membro_nome'] = $membro_encontrado['nome'];
                    header('Location: membro_dashboard.php');
                    exit;
                } elseif ($membro_encontrado['status'] === 'Inativo') {
                    $pendente = true;
                    $mensagem = "Seu cadastro foi recebido, mas ainda está pendente de aprovação pela secretaria da congregação.";
                } else {
                    $erro = true;
                    $mensagem = "Seu cadastro encontra-se com o status: " . $membro_encontrado['status'] . ". Entre em contato com a secretaria.";
                }
            } else {
                $erro = true;
                $mensagem = "Membro não localizado. Verifique se o CPF e a Data de Nascimento foram digitados corretamente.";
            }
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro de banco de dados. Tente novamente mais tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Acesso do Membro - SGIM</title>
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
<body class="bg-darkbg text-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-darkcard rounded-3xl border border-darkborder p-8 md:p-10 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-6 opacity-5 pointer-events-none">
            <span class="material-symbols-outlined text-9xl">badge</span>
        </div>

        <div class="text-center mb-8">
            <div class="inline-flex p-4 bg-brand/10 rounded-2xl text-brand mb-4">
                <span class="material-symbols-outlined text-4xl">badge</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white uppercase">Área do Membro</h1>
            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Consulte seus dados e sua Carteirinha</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-xl border <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-brand/10 border-brand/20 text-brand' ?> flex items-start gap-3">
                <span class="material-symbols-outlined mt-0.5"><?= $erro ? 'error' : 'info' ?></span>
                <p class="text-xs font-semibold leading-relaxed"><?= htmlspecialchars($mensagem) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Seu CPF</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 text-lg">fingerprint</span>
                    <input name="cpf" id="cpf" required 
                           class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-darkborder bg-black text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all font-mono" 
                           placeholder="000.000.000-00" type="text" oninput="mascaraCPF(this)"/>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Data de Nascimento</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 text-lg">calendar_month</span>
                    <input name="data_nascimento" required 
                           class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-darkborder bg-black text-white focus:ring-2 focus:ring-brand focus:border-brand outline-none transition-all [color-scheme:dark]" 
                           type="date"/>
                </div>
            </div>

            <button type="submit" class="w-full bg-brand hover:bg-yellow-500 text-black font-black py-4 rounded-xl shadow-lg shadow-brand/10 transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-2 active:scale-[0.98]">
                <span class="material-symbols-outlined text-lg">login</span>
                Acessar Painel
            </button>

            <div class="pt-6 border-t border-darkborder flex items-center justify-between text-[11px] font-bold uppercase tracking-wider">
                <a href="portal.php" class="text-gray-500 hover:text-white transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Voltar ao Portal
                </a>
                <a href="cadastro" class="text-brand hover:underline">
                    Quero me Cadastrar
                </a>
            </div>
        </form>
    </div>

    <script>
        function mascaraCPF(i){
            let v = i.value;
            if(isNaN(v.charAt(v.length-1))){
                i.value = v.substring(0, v.length-1);
                return;
            }
            i.setAttribute("maxlength", "14");
            if (v.length == 3 || v.length == 7) i.value += ".";
            if (v.length == 11) i.value += "-";
        }
    </script>
</body>
</html>
