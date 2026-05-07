require_once 'includes/header.php';

// Verificação de Autenticação e Conexão de Banco (Bootstrap já faz o session_start)
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

// Auto-patch do banco de dados para o Módulo de Eventos Professional
try {
    // Unificar nomes de colunas via Migração Defensiva
    ensureColumnExists($pdo, 'eventos', 'titulo', "VARCHAR(255) NOT NULL");
    ensureColumnExists($pdo, 'eventos', 'data_inicio', "DATETIME NOT NULL");
    ensureColumnExists($pdo, 'eventos', 'data_fim', "DATETIME NULL");
    ensureColumnExists($pdo, 'eventos', 'banner_url', "VARCHAR(255) NULL");
    ensureColumnExists($pdo, 'eventos', 'publico', "BOOLEAN DEFAULT 0");
    ensureColumnExists($pdo, 'eventos', 'status', "ENUM('Agendado', 'Em Andamento', 'Concluído', 'Cancelado') DEFAULT 'Agendado'");

    // Patch Legado: Caso as colunas antigas existam, migrar dados (Opcional/Seguro)
    @$pdo->exec("UPDATE eventos SET titulo = nome WHERE titulo IS NULL AND nome IS NOT NULL");
    @$pdo->exec("UPDATE eventos SET data_inicio = data_evento WHERE data_inicio IS NULL AND data_evento IS NOT NULL");
} catch(Exception $e) {
    error_log("Erro no Patch de Eventos: " . $e->getMessage());
}

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d\TH:i');
    $data_fim = $_POST['data_fim'] ?? $data_inicio;
    $local = $_POST['local'] ?? '';
    $status = $_POST['status'] ?? 'Agendado';
    $publico = isset($_POST['publico']) ? 1 : 0;
    $banner_url = null;
    
    // Tratamento de Upload de Banner
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/eventos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('banner_') . '.' . $ext;
        if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $fileName)) {
            $banner_url = $uploadDir . $fileName;
        }
    }

    if (empty($titulo) || empty($data_inicio)) {
        $erro = true;
        $mensagem = "O Título do evento e a Data de Início são obrigatórios.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO eventos (titulo, descricao, data_inicio, data_fim, local, status, banner_url, publico) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $descricao, $data_inicio, $data_fim, $local, $status, $banner_url, $publico]);
            
            header("Location: eventos.php?sucesso=1");
            exit;
        } catch (PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao cadastrar evento: " . $e->getMessage();
        }
    }
}

// Processamento concluído...
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Novo Evento</h2>
            <p class="text-sm text-gray-500 mt-1">Agende uma nova atividade no calendário da congregação.</p>
        </div>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden text-gray-300">
        <div class="p-6 border-b border-darkborder bg-white/[0.02]">
            <h2 class="text-lg font-bold text-white">Detalhes do Evento</h2>
            <p class="text-sm text-gray-500">Transforme suas atividades em experiências visuais.</p>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-8 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Banner Upload Area -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Banner do Evento (Recomendado 1200x600px)</label>
                    <div class="relative group cursor-pointer">
                        <input type="file" name="banner" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer" onchange="previewImage(this)">
                        <div id="banner-preview" class="w-full aspect-[21/9] rounded-2xl border-2 border-dashed border-darkborder bg-darkbg flex flex-col items-center justify-center transition-all group-hover:border-brand/40 overflow-hidden">
                            <span class="material-symbols-outlined text-4xl text-gray-600 mb-2">image</span>
                            <p class="text-xs text-gray-500">Clique para selecionar imagem</p>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Título do Evento</label>
                    <input name="titulo" required class="w-full px-5 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all placeholder:text-gray-700" placeholder="Ex: Grande Congresso de Missões" type="text"/>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Data e Hora de Início</label>
                    <input name="data_inicio" required class="w-full px-5 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-1 focus:ring-brand outline-none [color-scheme:dark]" type="datetime-local" value="<?= date('Y-m-d\TH:i') ?>"/>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Local / Endereço</label>
                    <input name="local" class="w-full px-5 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-1 focus:ring-brand outline-none" placeholder="Templo Sede, Quadra Esportiva, etc." type="text"/>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Status Inicial</label>
                    <select name="status" class="w-full px-5 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-1 focus:ring-brand outline-none appearance-none">
                        <option value="Agendado" selected>Agendado</option>
                        <option value="Em Andamento">Em Andamento</option>
                        <option value="Concluído">Concluído</option>
                    </select>
                </div>

                <div class="flex items-center gap-4 bg-darkbg/50 p-4 rounded-xl border border-darkborder">
                    <div class="size-10 rounded-full bg-brand/10 flex items-center justify-center text-brand">
                        <span class="material-symbols-outlined text-xl">ios_share</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-white uppercase tracking-tight">Publicar no Portal</p>
                        <p class="text-[10px] text-gray-500">Se ativado, o evento aparecerá no site público dos membros.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="publico" value="1" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                    </label>
                </div>

                <div class="md:col-span-2 space-y-2">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Descrição Detalhada</label>
                    <textarea name="descricao" rows="4" class="w-full px-5 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-1 focus:ring-brand outline-none resize-none" placeholder="Escreva sobre o cronograma, preletores, louvor e informações importantes..."></textarea>
                </div>
            </div>

            <div class="pt-10 flex flex-col sm:flex-row items-center justify-end gap-5 border-t border-darkborder">
                <a href="eventos.php" class="w-full text-center sm:w-auto px-10 py-3.5 rounded-xl border border-darkborder text-gray-400 font-bold hover:bg-white/5 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="w-full sm:w-auto px-16 py-3.5 rounded-xl bg-brand hover:bg-yellow-500 text-black font-black uppercase tracking-widest shadow-xl shadow-brand/10 transition-all">
                    Criar Evento Master
                </button>
            </div>
        </form>
    </div>

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('banner-preview');
                preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                preview.classList.remove('border-dashed');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

<?php
require_once 'includes/footer.php';
?>
