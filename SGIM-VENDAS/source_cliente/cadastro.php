<?php
ob_start();
session_start();
require_once 'config/database.php';

// Rota Pública de Auto-Cadastro de Membros Completo
$page_title = "Cadastro de Membro - SGIM";

$sucesso = false;
$erro = false;
$mensagem = "";

// Buscar Congregações e Cargos ativos do banco principal
$congregacoes = [];
$cargos = [];

if ($is_configured) {
    try {
        $congregacoes = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
        $cargos = $pdo->query("SELECT id, nome FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Falha ao buscar opções de cadastro público: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastro_publico') {
    // 1. Dados Pessoais
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $rg = trim($_POST['rg'] ?? '');
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $genero = $_POST['genero'] ?? null;
    $estado_civil = $_POST['estado_civil'] ?? null;

    // 2. Endereço
    $cep = trim($_POST['cep'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');

    // 3. Dados Eclesiásticos
    $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
    $cargo_id = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    $data_conversao = !empty($_POST['data_conversao']) ? $_POST['data_conversao'] : null;
    $data_batismo = !empty($_POST['data_batismo']) ? $_POST['data_batismo'] : null;

    // 4. Upload de Foto
    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/membros/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed_exts)) {
            $file_name = uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $file_name)) {
                $foto = $file_name;
            }
        }
    }
    
    // Higienização e validação de obrigatórios
    if (empty($nome) || empty($telefone)) {
        $erro = true;
        $mensagem = "Nome Completo e WhatsApp/Telefone são campos obrigatórios.";
    } else {
        try {
            // Mapeamento de Gênero para ENUM ('M', 'F', 'Outro')
            if ($genero === 'Masculino' || $genero === 'M') {
                $genero_db = 'M';
            } elseif ($genero === 'Feminino' || $genero === 'F') {
                $genero_db = 'F';
            } else {
                $genero_db = !empty($genero) ? 'Outro' : null;
            }

            // Mapeamento de Estado Civil para ENUM ('Solteiro', 'Casado', 'Divorciado', 'Viúvo', 'Outro')
            $estados_civis_validos = ['Solteiro', 'Casado', 'Divorciado', 'Viúvo', 'Outro'];
            $estado_civil_db = null;
            if (!empty($estado_civil)) {
                $civil_clean = str_replace('(a)', '', $estado_civil);
                if (in_array($civil_clean, $estados_civis_validos)) {
                    $estado_civil_db = $civil_clean;
                } else {
                    $estado_civil_db = 'Outro';
                }
            }

            // Validação/Blindagem de FK contra valores fantasmas no select
            if ($cargo_id !== null) {
                $stmtCheckCargo = $pdo->prepare("SELECT id FROM cargos WHERE id = ?");
                $stmtCheckCargo->execute([$cargo_id]);
                if (!$stmtCheckCargo->fetch()) {
                    $cargo_id = null;
                }
            }
            if ($congregacao_id !== null) {
                $stmtCheckCong = $pdo->prepare("SELECT id FROM congregacoes WHERE id = ?");
                $stmtCheckCong->execute([$congregacao_id]);
                if (!$stmtCheckCong->fetch()) {
                    $congregacao_id = null;
                }
            }

            // Sincronização automática de colunas para evitar erros de banco legado do cliente
            $checkCols = $pdo->query("SHOW COLUMNS FROM membros");
            $cols = $checkCols->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('data_conversao', $cols)) {
                $pdo->exec("ALTER TABLE membros ADD COLUMN data_conversao DATE AFTER data_batismo");
            }
            if (!in_array('foto', $cols)) {
                $pdo->exec("ALTER TABLE membros ADD COLUMN foto VARCHAR(255) AFTER congregacao_id");
            }

            // Inserção na tabela membros com status 'Inativo' e todos os novos campos
            $sqlInsert = "INSERT INTO membros (
                nome, email, telefone, cpf, rg, data_nascimento, genero, estado_civil, 
                cep, endereco, numero, complemento, bairro, cidade, estado, 
                congregacao_id, cargo_id, data_conversao, data_batismo, foto, status, data_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inativo', NOW())";
            
            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute([
                $nome, 
                $email, 
                $telefone, 
                $cpf, 
                $rg,
                $data_nascimento, 
                $genero_db, 
                $estado_civil_db, 
                $cep,
                $endereco,
                $numero,
                $complemento,
                $bairro,
                $cidade,
                $estado,
                $congregacao_id, 
                $cargo_id,
                $data_conversao,
                $data_batismo,
                $foto
            ]);
            
            $sucesso = true;
            $mensagem = "Ficha cadastrada com sucesso! Suas informações e foto foram enviadas para validação da secretaria.";
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao realizar cadastro. Por favor, verifique se os dados digitados estão corretos.";
            error_log("Erro no auto-cadastro de membros completo: " . $e->getMessage());
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;900&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
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
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        display: ['Space Grotesk', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        /* CSS Premium de Combate ao Autofill e Sugestões do Navegador */
        .floating-input:-webkit-autofill,
        .floating-input:-webkit-autofill:hover, 
        .floating-input:-webkit-autofill:focus, 
        .floating-input:-webkit-autofill:active,
        .floating-input:-internal-autofill-previewed,
        .floating-input:-internal-autofill-selected {
            -webkit-text-fill-color: #ffffff !important;
            -webkit-box-shadow: 0 0 0 1000px #121212 inset !important;
            box-shadow: 0 0 0 1000px #121212 inset !important;
            transition: background-color 5000s ease-in-out 0s !important;
            background-color: #121212 !important;
            color: #ffffff !important;
        }

        .floating-input:hover,
        .floating-input:focus,
        .floating-input:active { 
            outline: none !important; 
            border-color: #FFC107 !important; 
            background-color: #0b0b0b !important; 
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.15) !important; 
            color: #ffffff !important;
        }

        /* Labels Flutuantes */
        .floating-input-container { 
            position: relative; 
        }
        .floating-label { 
            position: absolute; 
            top: 15px; 
            left: 16px; 
            color: #8F8F9D; 
            font-size: 14px; 
            transition: all 0.2s ease; 
            pointer-events: none; 
            opacity: 0.8; 
            background: transparent; 
        } 
        /* Flutua a label ao focar ou quando houver caracteres digitados */
        .floating-input:focus ~ .floating-label, 
        .floating-input:not(:placeholder-shown) ~ .floating-label { 
            top: 5px; 
            font-size: 10px; 
            color: #FFC107; 
            opacity: 1; 
            background: transparent; 
        }
    </style>
</head>
<body class="bg-darkbg text-gray-100 font-sans p-4 md:p-8 selection:bg-brand/20 selection:text-brand">
    <div class="max-w-3xl mx-auto my-6">
        <!-- Header -->
        <div class="text-center mb-10">
            <div class="inline-flex p-4 bg-brand/10 rounded-2xl text-brand mb-4 hover:scale-105 transition-transform duration-300">
                <span class="material-symbols-outlined text-4xl">church</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold font-display tracking-tight text-white uppercase">Ficha Ministerial de Membro</h1>
            <p class="text-gray-400 mt-2 text-sm font-medium">Preencha sua ficha completa para a emissão da sua carteirinha digital.</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-8 p-5 rounded-2xl border <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> flex items-center gap-4 animate-bounce">
                <span class="material-symbols-outlined text-2xl"><?= $erro ? 'error_outline' : 'check_circle_outline' ?></span>
                <p class="text-sm font-semibold leading-relaxed"><?= htmlspecialchars($mensagem) ?></p>
            </div>
            <?php if ($sucesso): ?>
                <div class="text-center">
                    <a href="portal.php" class="inline-flex items-center gap-2 bg-brand hover:bg-yellow-500 text-black font-bold px-8 py-4 rounded-xl transition-all shadow-lg shadow-brand/10 hover:shadow-brand/20 active:scale-98">
                        <span class="material-symbols-outlined text-sm font-bold">arrow_back</span>
                        Voltar ao Portal
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$sucesso): ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="acao" value="cadastro_publico">
            
            <!-- Seção 1: Dados Pessoais & Foto -->
            <div class="bg-darkcard p-6 md:p-8 rounded-3xl border border-darkborder space-y-6 shadow-2xl">
                <div class="flex items-center gap-3 text-brand border-b border-darkborder pb-4 mb-2">
                    <span class="material-symbols-outlined text-lg">person</span>
                    <h2 class="text-xs font-bold uppercase tracking-widest font-display">1. Identificação & Foto</h2>
                </div>

                <!-- Campo de Foto com Preview -->
                <div class="flex flex-col md:flex-row gap-6 items-center mb-6">
                    <div class="relative group">
                        <div id="photo-preview" class="size-32 rounded-3xl bg-darkbg border-2 border-dashed border-darkborder flex items-center justify-center overflow-hidden group-hover:border-brand transition-all">
                            <span class="material-symbols-outlined text-gray-600 text-4xl">add_a_photo</span>
                        </div>
                        <input type="file" name="foto" id="foto-input" class="hidden" accept="image/*"/>
                        <button type="button" onclick="document.getElementById('foto-input').click()" class="absolute -bottom-1 -right-1 size-9 bg-brand rounded-xl flex items-center justify-center text-black shadow-lg hover:scale-105 active:scale-95 transition-transform">
                            <span class="material-symbols-outlined text-sm font-bold">edit</span>
                        </button>
                    </div>
                    <div class="flex-1 space-y-1.5 text-center md:text-left">
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide">Sua Foto de Perfil</label>
                        <p class="text-[11px] text-gray-500 leading-normal">Escolha uma foto clara com fundo neutro para exibição na sua carteirinha digital. (Formatos: JPG, PNG, WEBP).</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2 floating-input-container">
                        <input name="nome" id="nome" required class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="text" placeholder=" ">
                        <label for="nome" class="floating-label">Nome Completo</label>
                    </div>
                    
                    <div class="floating-input-container">
                        <input name="email" id="email" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="email" placeholder=" ">
                        <label for="email" class="floating-label">E-mail</label>
                    </div>
                    
                    <div class="floating-input-container">
                        <input name="telefone" id="telefone" required class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="tel" placeholder=" " oninput="mascaraTelefone(this)">
                        <label for="telefone" class="floating-label">WhatsApp / Telefone</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="cpf" id="cpf" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none font-mono" type="text" placeholder=" " oninput="mascaraCPF(this)">
                        <label for="cpf" class="floating-label">CPF</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="rg" id="rg" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none font-mono" type="text" placeholder=" ">
                        <label for="rg" class="floating-label">RG</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="data_nascimento" id="data_nascimento" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none [color-scheme:dark]" type="date" placeholder=" ">
                        <label for="data_nascimento" class="floating-label">Data de Nascimento</label>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Gênero</label>
                        <select name="genero" class="w-full bg-darkbg border border-darkborder rounded-2xl px-4 py-3.5 text-sm text-gray-300 focus:ring-0 focus:border-brand outline-none appearance-none cursor-pointer">
                            <option value="">Selecione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Feminino</option>
                            <option value="Outro">Outro / Prefiro não declarar</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Estado Civil</label>
                        <select name="estado_civil" class="w-full bg-darkbg border border-darkborder rounded-2xl px-4 py-3.5 text-sm text-gray-300 focus:ring-0 focus:border-brand outline-none appearance-none cursor-pointer">
                            <option value="">Selecione...</option>
                            <option value="Solteiro">Solteiro(a)</option>
                            <option value="Casado">Casado(a)</option>
                            <option value="Divorciado">Divorciado(a)</option>
                            <option value="Viúvo">Viúvo(a)</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seção 2: Endereço Residencial (Com busca automática de CEP) -->
            <div class="bg-darkcard p-6 md:p-8 rounded-3xl border border-darkborder space-y-6 shadow-2xl">
                <div class="flex items-center gap-3 text-brand border-b border-darkborder pb-4 mb-2">
                    <span class="material-symbols-outlined text-lg">home</span>
                    <h2 class="text-xs font-bold uppercase tracking-widest font-display">2. Endereço Residencial</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="floating-input-container">
                        <input name="cep" id="cep" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none font-mono" type="text" placeholder=" " oninput="mascaraCEP(this)" onblur="buscarCEP(this.value)">
                        <label for="cep" class="floating-label">CEP (Busca Automática)</label>
                    </div>
                    
                    <div class="md:col-span-2 floating-input-container">
                        <input name="endereco" id="endereco" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="text" placeholder=" ">
                        <label for="endereco" class="floating-label">Rua / Avenida</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="numero" id="numero" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="text" placeholder=" ">
                        <label for="numero" class="floating-label">Número</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="complemento" id="complemento" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="text" placeholder=" ">
                        <label for="complemento" class="floating-label">Complemento</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="bairro" id="bairro" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="text" placeholder=" ">
                        <label for="bairro" class="floating-label">Bairro</label>
                    </div>

                    <div class="md:col-span-2 floating-input-container">
                        <input name="cidade" id="cidade" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none" type="text" placeholder=" ">
                        <label for="cidade" class="floating-label">Cidade</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="estado" id="estado" maxlength="2" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none uppercase font-bold" type="text" placeholder=" ">
                        <label for="estado" class="floating-label">Estado (UF)</label>
                    </div>
                </div>
            </div>

            <!-- Seção 3: Dados Eclesiásticos -->
            <div class="bg-darkcard p-6 md:p-8 rounded-3xl border border-darkborder space-y-6 shadow-2xl">
                <div class="flex items-center gap-3 text-brand border-b border-darkborder pb-4 mb-2">
                    <span class="material-symbols-outlined text-lg">church</span>
                    <h2 class="text-xs font-bold uppercase tracking-widest font-display">3. Vida Congregacional & Histórico</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Selecione sua Congregação</label>
                        <select name="congregacao_id" class="w-full bg-darkbg border border-darkborder rounded-2xl px-4 py-3.5 text-sm text-gray-300 focus:ring-0 focus:border-brand outline-none appearance-none cursor-pointer">
                            <option value="">Selecione...</option>
                            <?php foreach ($congregacoes as $cong): ?>
                                <option value="<?= $cong['id'] ?>"><?= htmlspecialchars($cong['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Cargo ou Função Pretendida</label>
                        <select name="cargo_id" class="w-full bg-darkbg border border-darkborder rounded-2xl px-4 py-3.5 text-sm text-gray-300 focus:ring-0 focus:border-brand outline-none appearance-none cursor-pointer">
                            <option value="">Selecione...</option>
                            <?php foreach ($cargos as $cargo): ?>
                                <option value="<?= $cargo['id'] ?>"><?= htmlspecialchars($cargo['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="floating-input-container">
                        <input name="data_conversao" id="data_conversao" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none [color-scheme:dark]" type="date" placeholder=" ">
                        <label for="data_conversao" class="floating-label">Data de Conversão</label>
                    </div>

                    <div class="floating-input-container">
                        <input name="data_batismo" id="data_batismo" class="floating-input w-full bg-darkbg border border-darkborder rounded-2xl px-4 pt-5 pb-2 text-sm focus:ring-0 focus:border-brand outline-none [color-scheme:dark]" type="date" placeholder=" ">
                        <label for="data_batismo" class="floating-label">Data de Batismo nas Águas</label>
                    </div>
                </div>
            </div>

            <!-- Botão de Finalizar -->
            <div class="space-y-4">
                <button type="submit" class="w-full bg-brand hover:bg-yellow-500 text-black font-black py-4.5 rounded-2xl shadow-xl shadow-brand/10 transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-2 active:scale-[0.98]">
                    <span class="material-symbols-outlined font-bold text-sm">send</span>
                    Finalizar e Enviar Ficha
                </button>
                <p class="text-center text-[10px] text-gray-500 uppercase tracking-wider font-bold">
                    Ao enviar a ficha, suas informações serão processadas na base de dados principal da igreja.
                </p>
            </div>
        </form>
        <?php endif; ?>
        
        <footer class="mt-16 text-center text-gray-600 text-[10px] uppercase tracking-[0.2em] font-black">
            &copy; <?= date('Y') ?> SGIM - Sistema de Gestão de Igrejas e Membros
        </footer>
    </div>

    <!-- Scripts de Mascara e Consulta de CEP -->
    <script>
        // Preview de Foto
        document.getElementById('foto-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photo-preview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover rounded-3xl">`;
                }
                reader.readAsDataURL(file);
            }
        });

        // Mascaras
        function mascaraTelefone(i) {
            let v = i.value.replace(/\D/g, "");
            v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
            v = v.replace(/(\d)(\d{4})$/, "$1-$2");
            i.value = v;
        }

        function mascaraCPF(i) {
            let v = i.value.replace(/\D/g, "");
            i.setAttribute("maxlength", "14");
            if (v.length > 9) {
                v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
            } else if (v.length > 6) {
                v = v.replace(/^(\d{3})(\d{3})(\d)/, "$1.$2.$3");
            } else if (v.length > 3) {
                v = v.replace(/^(\d{3})(\d)/, "$1.$2");
            }
            i.value = v;
        }

        function mascaraCEP(i) {
            let v = i.value.replace(/\D/g, "");
            i.setAttribute("maxlength", "9");
            if (v.length > 5) {
                v = v.replace(/^(\d{5})(\d)/, "$1-$2");
            }
            i.value = v;
        }

        // Consulta de CEP Inteligente via ViaCEP
        function buscarCEP(cepVal) {
            const cleanCep = cepVal.replace(/\D/g, "");
            if (cleanCep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cleanCep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('endereco').value = data.logradouro;
                            document.getElementById('endereco').dispatchEvent(new Event('input'));

                            document.getElementById('bairro').value = data.bairro;
                            document.getElementById('bairro').dispatchEvent(new Event('input'));

                            document.getElementById('cidade').value = data.localidade;
                            document.getElementById('cidade').dispatchEvent(new Event('input'));

                            document.getElementById('estado').value = data.uf;
                            document.getElementById('estado').dispatchEvent(new Event('input'));

                            // Foca no numero
                            document.getElementById('numero').focus();
                        }
                    })
                    .catch(err => console.error("Erro ao buscar CEP: ", err));
            }
        }
    </script>
</body>
</html>
