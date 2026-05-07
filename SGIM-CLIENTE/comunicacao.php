<?php
ob_start();
session_start();
require_once 'config/db.php';

// Auto-patch do banco de dados para evitar o erro de 'Unknown column canal'
try {
    $pdo->exec("ALTER TABLE comunicacoes ADD COLUMN canal ENUM('email', 'whatsapp') DEFAULT 'email'");
    $pdo->exec("ALTER TABLE comunicacoes ADD COLUMN status ENUM('rascunho', 'enviado') DEFAULT 'enviado'");
    $pdo->exec("ALTER TABLE comunicacoes ADD COLUMN data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch(Exception $e) {}

// Verificação de Autenticação e Conexão de Banco (v1.4.9 logic)
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assunto = $_POST['assunto'] ?? '';
    $conteudo = $_POST['mensagem'] ?? '';
    $canal = $_POST['canal'] ?? 'email';
    $acao = $_POST['acao'] ?? 'enviar'; // rascunho ou enviar
    $status = $acao == 'rascunho' ? 'rascunho' : 'enviado';
    
    if (empty($assunto) || empty($conteudo)) {
        $erro = true;
        $mensagem = "Assunto e Mensagem são obrigatórios.";
    } else {
        try {
            if ($acao == 'enviar' && $canal == 'email') {
                require_once 'src/bootstrap.php';
                $jobModel = new \App\Models\JobModel($pdo);
                
                // Tema para o bodyHtml
                $cor_brand = '#FFC107';
                $logo_html = '';
                $themeModel = new \App\Models\ThemeModel($pdo);
                $theme_db = $themeModel->getTheme();
                if ($theme_db) {
                    $cor_brand = $theme_db['cor_brand'] ?? '#FFC107';
                    if (!empty($theme_db['logo_url'])) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $url_logo = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/' . ltrim($theme_db['logo_url'], '/');
                        $logo_html = "<div style='text-align:center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #f0f0f0;'><img src='{$url_logo}' alt='Logo' style='max-height: 80px;' /></div>";
                    }
                }

                $stmtMembros = $pdo->query("SELECT m.email, m.nome FROM membros m WHERE m.status = 'Ativo' AND m.email IS NOT NULL AND m.email != ''");
                $membros = $stmtMembros->fetchAll();
                
                foreach ($membros as $m) {
                    $bodyRaw = nl2br(htmlspecialchars(str_replace('{{PRIMEIRO_NOME}}', explode(' ', trim($m['nome']))[0], $conteudo)));
                    $bodyHtml = "<div style='font-family: Arial; padding: 30px; border-top: 6px solid {$cor_brand};'>{$logo_html}<div style='color: #333;'>{$bodyRaw}</div></div>";
                    
                    $jobModel->add('email_massa', [
                        'para' => $m['email'],
                        'assunto' => $assunto,
                        'mensagem' => $bodyHtml
                    ]);
                }
                $sucessoEnvio = count($membros);
                $msgRetorno = "Campanha agendada! {$sucessoEnvio} e-mails entraram na fila de processamento em segundo plano.";
            } else {
                $msgRetorno = "Rascunho salvo com sucesso!";
            }

            $stmt = $pdo->prepare("INSERT INTO comunicacoes (assunto, mensagem, canal, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$assunto, $conteudo, $canal, $status]);
            
            $_SESSION['flash_msg'] = $msgRetorno;
            header("Location: comunicacao.php?sucesso=1");
            exit;
        } catch (Throwable $t) {
            $erro = true;
            $mensagem = "Erro ao processar comunicação: " . $t->getMessage();
        }
    }
}

// Fetch recent campaigns
try {
    $stmt = $pdo->query("SELECT * FROM comunicacoes ORDER BY data_cadastro DESC");
    $comunicacoes = $stmt->fetchAll();
    $recentes = $comunicacoes; // Define $recentes para evitar erro no count()
} catch (Throwable $t) {
    error_log("Comunicacao Data Error: " . $t->getMessage());
    $recentes = [];
}

$page_title = 'SGIM - Comunicação em Massa';
$current_page = 'comunicacao';

require_once 'includes/header.php';
?>

    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <p class="text-sm font-semibold">
                <?= isset($_SESSION['flash_msg']) ? htmlspecialchars($_SESSION['flash_msg']) : 'Campanha processada com sucesso!' ?>
                <?php unset($_SESSION['flash_msg']); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <div class="flex flex-col xl:flex-row gap-6">
        <!-- Main Content: Message Composer -->
        <section class="flex-1 flex flex-col bg-darkbg rounded-twelve border border-darkborder">
            <form method="POST" class="p-8 w-full flex flex-col h-full">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Comunicação em Massa</h1>
                        <p class="text-xs text-gray-500">Comunique-se com sua congregação rapidamente e de forma eficiente.</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" name="acao" value="rascunho" class="px-5 py-2.5 text-sm font-semibold border border-darkborder bg-darkcard text-gray-400 rounded-twelve hover:text-brand hover:border-brand transition-all">Salvar Rascunho</button>
                        <button type="submit" name="acao" value="enviar" class="px-6 py-2.5 text-sm font-bold bg-brand hover:bg-brand-dark text-darkbg rounded-twelve transition-all shadow-lg shadow-brand/10 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">send</span>
                            Revisar e Enviar
                        </button>
                    </div>
                </div>

                <!-- Channel Selector -->
                <div class="flex bg-darkcard p-1 rounded-twelve border border-darkborder mb-8 w-fit">
                    <label class="cursor-pointer flex items-center gap-2 px-6 py-2 rounded-lg bg-brand text-darkbg font-bold shadow-md transition-all">
                        <input type="radio" name="canal" value="email" checked class="hidden">
                        <span class="material-symbols-outlined text-[20px]">mail</span>
                        <span class="text-sm">Campanha Email</span>
                    </label>
                    <label class="cursor-pointer flex items-center gap-2 px-6 py-2 rounded-lg text-gray-500 hover:text-gray-300 transition-all opacity-50 cursor-not-allowed" title="Em breve">
                        <input type="radio" name="canal" value="whatsapp" disabled class="hidden">
                        <span class="material-symbols-outlined text-[20px]">chat</span>
                        <span class="text-sm font-semibold">Mensagem WhatsApp</span>
                    </label>
                </div>

                <!-- Email Composer -->
                <div class="flex-1 flex flex-col bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden mb-12">
                    <div class="p-6 border-b border-darkborder space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-[100px_1fr] items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Para:</span>
                            <div class="flex flex-wrap gap-2">
                                <span class="bg-brand/10 text-brand text-[10px] font-bold px-3 py-1 rounded-full flex items-center gap-1 border border-brand/20">
                                    TODOS OS CONTATOS <span class="material-symbols-outlined text-[14px] cursor-pointer">close</span>
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-[100px_1fr] items-center gap-2 border-t border-darkborder pt-4">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Assunto:</span>
                            <input name="assunto" required class="w-full border-none focus:ring-0 text-white font-medium placeholder:text-gray-600 bg-transparent text-sm" placeholder="Digite o assunto da campanha..." type="text"/>
                        </div>
                    </div>
                    <!-- Editor Toolbar -->
                    <div class="bg-white/5 border-b border-darkborder px-4 py-2 flex items-center gap-1 flex-wrap">
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">format_bold</span></button>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">format_italic</span></button>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">format_underlined</span></button>
                        <div class="w-px h-6 bg-darkborder mx-2"></div>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">format_list_bulleted</span></button>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">format_list_numbered</span></button>
                        <div class="w-px h-6 bg-darkborder mx-2"></div>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">link</span></button>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">image</span></button>
                        <button type="button" class="p-2 rounded hover:bg-white/10 text-gray-400 hover:text-brand transition-colors"><span class="material-symbols-outlined text-[20px]">attachment</span></button>
                        <div class="ml-auto flex items-center gap-2">
                            <button type="button" class="flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold uppercase text-brand hover:bg-brand/10 border border-brand/20 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-[16px]">pattern</span>
                                Usar Template
                            </button>
                        </div>
                    </div>
                    <!-- Editor Area -->
                    <div class="flex-1 p-8">
                        <textarea name="mensagem" required class="w-full h-full min-h-[250px] border-none focus:ring-0 bg-transparent text-gray-300 leading-relaxed resize-none text-sm" placeholder="Escreva o conteúdo do email aqui... Use {{name}} para personalizar."></textarea>
                    </div>
                </div>
            </form>
        </section>

        <!-- Right Sidebar: Helpers -->
        <aside class="hidden xl:flex w-80 border border-darkborder rounded-twelve bg-darkcard flex-col p-6 gap-8 overflow-y-auto">
            <div class="space-y-4">
                <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-[20px]">lightbulb</span>
                    Sugestões Inteligentes
                </h3>
                <div class="p-4 bg-brand/5 rounded-twelve border border-brand/10">
                    <p class="text-xs text-gray-400 leading-relaxed">Considere enviar emails na <span class="font-bold text-brand">terça-feira de manhã</span> para maiores taxas de abertura.</p>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-[20px]">variable_insert</span>
                    Personalização
                </h3>
                <div class="grid grid-cols-2 gap-2">
                    <button class="text-[10px] font-bold bg-darkbg p-2 rounded border border-darkborder text-gray-500 hover:border-brand hover:text-brand transition-all uppercase tracking-tight">{{primeiro_nome}}</button>
                    <button class="text-[10px] font-bold bg-darkbg p-2 rounded border border-darkborder text-gray-500 hover:border-brand hover:text-brand transition-all uppercase tracking-tight">{{ultimo_nome}}</button>
                    <button class="text-[10px] font-bold bg-darkbg p-2 rounded border border-darkborder text-gray-500 hover:border-brand hover:text-brand transition-all uppercase tracking-tight">{{congregacao}}</button>
                    <button class="text-[10px] font-bold bg-darkbg p-2 rounded border border-darkborder text-gray-500 hover:border-brand hover:text-brand transition-all uppercase tracking-tight">{{cargo}}</button>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">Campanhas Recentes</h3>
                <div class="space-y-3">
                    <?php if (count($recentes) > 0): ?>
                        <?php foreach ($recentes as $r): ?>
                            <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-white/5 transition-colors border border-transparent hover:border-darkborder group">
                                <?php if ($r['canal'] == 'email'): ?>
                                    <div class="size-8 rounded bg-brand/10 flex items-center justify-center text-brand">
                                        <span class="material-symbols-outlined text-[18px]">mail</span>
                                    </div>
                                <?php else: ?>
                                    <div class="size-8 rounded bg-green-500/10 flex items-center justify-center text-green-500">
                                        <span class="material-symbols-outlined text-[18px]">chat</span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-white truncate group-hover:text-brand transition-colors"><?= htmlspecialchars($r['assunto']) ?></p>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-tighter">
                                        <?= $r['status'] == 'enviado' ? 'Enviado' : 'Rascunho' ?> • 
                                        <?= date('d/m/Y', strtotime($r['data_criacao'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 p-2 text-center border border-darkborder rounded-lg border-dashed">
                            Nenhuma campanha recente.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>

<?php
require_once 'includes/footer.php';
?>
