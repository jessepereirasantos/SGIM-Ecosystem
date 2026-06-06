<?php
ob_start();
session_start();
require_once 'config/database.php';

// Verificação de Autenticação e Conexão de Banco
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$membro = null;
$mensagem = '';
$erro = false;

// 🛡️ Inicializa o AccessManager para proteção de rota antecipada
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de gravação
if ($id) {
    if (!$access->can('membros', 'editar')) {
        echo "<script>alert('Acesso Negado: Você não tem permissão para editar membros.'); window.location.href='membros.php';</script>";
        exit;
    }
} else {
    if (!$access->can('membros', 'cadastrar')) {
        echo "<script>alert('Acesso Negado: Você não tem permissão para cadastrar membros.'); window.location.href='membros.php';</script>";
        exit;
    }
}

// Buscar opções para os selects de acordo com o escopo
$cargos_opt = $pdo->query("SELECT id, nome FROM cargos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($access->isGlobal()) {
    $congregacoes_opt = $pdo->query("SELECT id, nome FROM congregacoes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $congregacoes_opt = $pdo->query("SELECT id, nome FROM congregacoes WHERE id = " . (int)$access->getCongregacaoId() . " ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
}

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
    $stmt->execute([$id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membro) {
        header('Location: membros.php');
        exit;
    }
    
    // Se for escopo LOCAL, valida se o membro pertence à mesma congregação
    if (!$access->isGlobal() && $membro['congregacao_id'] != $access->getCongregacaoId()) {
        echo "<script>alert('Acesso Negado: Este membro pertence a outra congregação.'); window.location.href='membros.php';</script>";
        exit;
    }
    
    $nome = $membro['nome'];
    $telefone = $membro['telefone'];
    $email = $membro['email'];
    $data_nascimento = $membro['data_nascimento'];
    $data_batismo = $membro['data_batismo'];
    $data_conversao = $membro['data_conversao'];
    $cpf = $membro['cpf'];
    $endereco = $membro['endereco'];
    $cargo_id = $membro['cargo_id'];
    $congregacao_id = $membro['congregacao_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $data_batismo = !empty($_POST['data_batismo']) ? $_POST['data_batismo'] : null;
    $data_conversao = !empty($_POST['data_conversao']) ? $_POST['data_conversao'] : null;
    $cpf = trim($_POST['cpf'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cargo_id = !empty($_POST['cargo_id']) ? intval($_POST['cargo_id']) : null;
    if ($access->isGlobal()) {
        $congregacao_id = !empty($_POST['congregacao_id']) ? intval($_POST['congregacao_id']) : null;
    } else {
        $congregacao_id = (int)$access->getCongregacaoId();
    }
    
    // Upload de Foto
    $foto = $membro['foto'] ?? null;
    $data_cadastro = $membro['data_cadastro'] ?? date('Y-m-d H:i:s');
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/membros/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $file_name)) {
            $foto = $file_name;
        }
    }
    
    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome é obrigatório.";
    } else {
        try {
            // Sincronização de Schema: Verifica se a coluna existe antes de tentar adicionar
            $checkCols = $pdo->query("SHOW COLUMNS FROM membros");
            $cols = $checkCols->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('data_conversao', $cols)) {
                $pdo->exec("ALTER TABLE membros ADD COLUMN data_conversao DATE AFTER data_batismo");
            }
            
            if (!in_array('foto', $cols)) {
                $pdo->exec("ALTER TABLE membros ADD COLUMN foto VARCHAR(255) AFTER congregacao_id");
            }

            // Garante que as colunas cargo_id e congregacao_id existam (caso o schema.sql não tenha rodado completo)
            if (!in_array('cargo_id', $cols)) {
                $pdo->exec("ALTER TABLE membros ADD COLUMN cargo_id INT AFTER data_conversao");
            }
            if (!in_array('congregacao_id', $cols)) {
                $pdo->exec("ALTER TABLE membros ADD COLUMN congregacao_id INT AFTER cargo_id");
            }

            // BLINDAGEM CONTRA FK: Se o cargo_id ou congregacao_id não existir na tabela pai, força NULL
            if ($cargo_id !== null) {
                $stmtCheck = $pdo->prepare("SELECT id FROM cargos WHERE id = ?");
                $stmtCheck->execute([$cargo_id]);
                if (!$stmtCheck->fetch()) $cargo_id = null;
            }
            if ($congregacao_id !== null) {
                $stmtCheck = $pdo->prepare("SELECT id FROM congregacoes WHERE id = ?");
                $stmtCheck->execute([$congregacao_id]);
                if (!$stmtCheck->fetch()) $congregacao_id = null;
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE membros SET nome=?, telefone=?, email=?, data_nascimento=?, data_batismo=?, data_conversao=?, cpf=?, endereco=?, cargo_id=?, congregacao_id=?, foto=? WHERE id=?");
                $stmt->execute([$nome, $telefone, $email, $data_nascimento, $data_batismo, $data_conversao, $cpf, $endereco, $cargo_id, $congregacao_id, $foto, $id]);
                $mensagem = "Membro atualizado com sucesso!";
                $erro = false;
            } else {
                $stmt = $pdo->prepare("INSERT INTO membros (nome, telefone, email, data_nascimento, data_batismo, data_conversao, cpf, endereco, cargo_id, congregacao_id, foto, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo')");
                $stmt->execute([$nome, $telefone, $email, $data_nascimento, $data_batismo, $data_conversao, $cpf, $endereco, $cargo_id, $congregacao_id, $foto]);
                
                // Redirecionamento limpo
                if (ob_get_length()) ob_end_clean();
                header("Location: membros.php?sucesso=1");
                exit;
            }
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao processar membro: " . $e->getMessage();
        }
    }
}

$page_title = 'SGIM - ' . ($id ? 'Editar Membro' : 'Novo Membro');
$current_page = 'membros';

require_once 'includes/header.php';
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Cadastro de Membro</h2>
            <p class="text-sm text-gray-500 mt-1">Adicione novos membros à congregação</p>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <!-- Registration Form Card -->
    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden">
        <div class="p-6 border-b border-darkborder bg-white/[0.02]">
            <h2 class="text-lg font-bold text-white">Informações Gerais</h2>
            <p class="text-sm text-gray-500">Preencha os dados abaixo com atenção.</p>
        </div>
        <form action="#" method="POST" enctype="multipart/form-data" class="p-8 space-y-12">
            <!-- SEÇÃO 1: DADOS PESSOAIS -->
            <div class="space-y-6">
                <h3 class="text-sm font-black text-brand uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">person</span>
                    1. Dados Pessoais
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                    <div class="col-span-1 md:col-span-2 flex flex-col md:flex-row gap-8 items-center mb-4">
                        <div class="relative group">
                            <div id="photo-preview" class="size-32 rounded-full bg-darkbg border-2 border-dashed border-darkborder flex items-center justify-center overflow-hidden group-hover:border-brand transition-all">
                                <?php if (isset($membro['foto']) && $membro['foto']): ?>
                                    <img src="uploads/membros/<?= $membro['foto'] ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-gray-600 text-4xl">add_a_photo</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="foto" id="foto-input" class="hidden" accept="image/*"/>
                            <button type="button" onclick="document.getElementById('foto-input').click()" class="absolute bottom-0 right-0 size-8 bg-brand rounded-full flex items-center justify-center text-black shadow-lg">
                                <span class="material-symbols-outlined text-sm font-bold">edit</span>
                            </button>
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome Completo</label>
                            <input name="nome" value="<?= $membro['nome'] ?? '' ?>" required class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" placeholder="Digite o nome completo" type="text"/>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Telefone / WhatsApp</label>
                        <input name="telefone" value="<?= $membro['telefone'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="(00) 00000-0000" type="tel"/>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">E-mail</label>
                        <input name="email" value="<?= $membro['email'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="membro@exemplo.com" type="email"/>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">CPF</label>
                        <input name="cpf" value="<?= $membro['cpf'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" type="text" placeholder="000.000.000-00"/>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Data de Nascimento</label>
                        <input name="data_nascimento" value="<?= $membro['data_nascimento'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none [color-scheme:dark]" type="date"/>
                    </div>
                    <div class="md:col-span-2 space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Endereço Residencial</label>
                        <input name="endereco" value="<?= $membro['endereco'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none" placeholder="Rua, Número, Bairro, Cidade - UF" type="text"/>
                    </div>
                </div>
            </div>

            <!-- SEÇÃO 2: DADOS DE CONVERSÃO -->
            <div class="space-y-6">
                <h3 class="text-sm font-black text-brand uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">auto_awesome</span>
                    2. Experiência de Conversão
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Data de Conversão</label>
                        <input name="data_conversao" value="<?= $membro['data_conversao'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none [color-scheme:dark]" type="date"/>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Data de Batismo</label>
                        <input name="data_batismo" value="<?= $membro['data_batismo'] ?? '' ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none [color-scheme:dark]" type="date"/>
                    </div>
                </div>
            </div>

            <!-- SEÇÃO 3: DADOS CONGREGACIONAIS -->
            <div class="space-y-6">
                <h3 class="text-sm font-black text-brand uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">church</span>
                    3. Vida Congregacional
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Congregação</label>
                        <select name="congregacao_id" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                            <option value="">Selecione a congregação</option>
                            <?php foreach ($congregacoes_opt as $con): ?>
                                <option value="<?= $con['id'] ?>" <?= (isset($membro['congregacao_id']) && $membro['congregacao_id'] == $con['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($con['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Cargo / Função</label>
                        <select name="cargo_id" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand outline-none appearance-none">
                            <option value="">Selecione um cargo</option>
                            <?php foreach ($cargos_opt as $car): ?>
                                <option value="<?= $car['id'] ?>" <?= (isset($membro['cargo_id']) && $membro['cargo_id'] == $car['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($car['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-6 flex flex-col sm:flex-row items-center justify-end gap-4 border-t border-darkborder">
                <a href="membros.php" class="w-full text-center sm:w-auto px-8 py-3 rounded-twelve border border-darkborder text-gray-400 font-semibold hover:bg-white/5 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="w-full sm:w-auto px-12 py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all">
                    Finalizar Cadastro
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('foto-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photo-preview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>

<?php
require_once 'includes/footer.php';
?>
