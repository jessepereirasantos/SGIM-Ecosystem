<?php
ob_start();
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_whatsapp_config') {
    $api_url = rtrim($_POST['api_url'] ?? '', '/');
    $api_token = $_POST['api_token'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('whatsapp_api_url', ?), ('whatsapp_api_token', ?) 
                               ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->execute([$api_url, $api_token]);
        
        echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco: ' . $e->getMessage()]);
    }
    exit;
}

// Buscar configurações existentes
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('whatsapp_api_url', 'whatsapp_api_token')");
$config = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['chave']] = $row['valor'];
}

$current_page = 'whatsapp';
$page_title = 'WhatsApp - SGIM';
require_once 'includes/header.php';

// Buscar Cargos para Filtros
try {
    $stmtCargos = $pdo->query("SELECT id, nome FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC");
    $cargos = $stmtCargos ? $stmtCargos->fetchAll(PDO::FETCH_ASSOC) : [];

    // Buscar Congregações para Filtros
    $stmtCong = $pdo->query("SELECT id, nome FROM congregacoes WHERE status = 'Ativa' ORDER BY nome ASC");
    $congregacoes = $stmtCong ? $stmtCong->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $cargos = [];
    $congregacoes = [];
}
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Coluna de Configuração e Filtros -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-darkcard border border-darkborder rounded-2xl p-6 shadow-xl">
            <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">settings_input_component</span>
                Configuração da API
            </h2>
            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-2">URL da API (Discloud)</label>
                    <input type="text" id="api_url" value="<?= $config['whatsapp_api_url'] ?? '' ?>" placeholder="https://sua-api.discloud.app" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm text-gray-300 focus:border-brand outline-none transition-all">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-2">Token de Autenticação</label>
                    <input type="password" id="api_token" value="<?= $config['whatsapp_api_token'] ?? '' ?>" placeholder="••••••••••••••••" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm text-gray-300 focus:border-brand outline-none transition-all">
                </div>
                <button onclick="saveApiConfig()" id="btn_save_config" class="w-full bg-brand/10 hover:bg-brand/20 text-brand border border-brand/20 py-3 rounded-xl font-bold text-xs uppercase tracking-widest transition-all">
                    Salvar Configuração
                </button>
            </div>
        </div>

        <div class="bg-darkcard border border-darkborder rounded-2xl p-6 shadow-xl">
            <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">filter_alt</span>
                Público-Alvo
            </h2>
            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-2">Enviar Para</label>
                    <select id="send_to" onchange="toggleFilters()" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm text-gray-300 focus:border-brand outline-none transition-all">
                        <option value="all">Todos os Membros</option>
                        <option value="individual">Membro Individual</option>
                        <option value="cargo">Por Cargo (Pastores, Líderes...)</option>
                        <option value="congregacao">Por Congregação</option>
                    </select>
                </div>

                <!-- Filtro Individual -->
                <div id="filter_individual" class="hidden">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-2">Pesquisar Membro</label>
                    <input type="text" id="search_membro" placeholder="Nome ou Telefone..." class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm text-gray-300 focus:border-brand outline-none transition-all">
                    <div id="membro_results" class="mt-2 max-h-40 overflow-y-auto bg-darkbg border border-darkborder rounded-xl hidden"></div>
                </div>

                <!-- Filtro Cargo -->
                <div id="filter_cargo" class="hidden">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-2">Selecione o Cargo</label>
                    <select id="select_cargo" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm text-gray-300 focus:border-brand outline-none transition-all">
                        <?php foreach($cargos as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro Congregação -->
                <div id="filter_cong" class="hidden">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-2">Selecione a Congregação</label>
                    <select id="select_cong" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-sm text-gray-300 focus:border-brand outline-none transition-all">
                        <?php foreach($congregacoes as $con): ?>
                            <option value="<?= $con['id'] ?>"><?= $con['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Coluna de Mensagem e Envio -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-darkcard border border-darkborder rounded-2xl p-8 shadow-xl">
            <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
                <span class="material-symbols-outlined text-brand">chat_bubble</span>
                Compor Mensagem
            </h2>
            
            <div class="space-y-6">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Conteúdo da Mensagem</label>
                        <div class="flex gap-2">
                            <button onclick="insertVar('{nome}')" class="text-[9px] bg-white/5 hover:bg-white/10 text-gray-400 px-2 py-1 rounded border border-white/5 transition-all">Variável {nome}</button>
                            <button onclick="insertVar('{saudacao}')" class="text-[9px] bg-white/5 hover:bg-white/10 text-gray-400 px-2 py-1 rounded border border-white/5 transition-all">Variável {saudacao}</button>
                        </div>
                    </div>
                    <textarea id="message_content" rows="8" placeholder="Digite sua mensagem aqui... Use {nome} para personalizar." class="w-full bg-darkbg border border-darkborder rounded-2xl px-6 py-4 text-sm text-gray-300 focus:border-brand outline-none transition-all resize-none"></textarea>
                </div>

                <div class="flex items-center gap-4">
                    <button id="btn_send" onclick="sendWhatsApp()" class="flex-1 bg-brand text-black font-black py-4 rounded-2xl uppercase tracking-widest hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-brand/20 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined">send</span>
                        Disparar Mensagens
                    </button>
                </div>
            </div>
        </div>

        <!-- Log de Envio -->
        <div id="sending_status" class="hidden bg-darkcard border border-darkborder rounded-2xl p-6 shadow-xl animate-in fade-in slide-in-from-top-4 duration-500">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-white uppercase tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand animate-spin">sync</span>
                    Progresso do Envio
                </h3>
                <span id="progress_percent" class="text-xs font-black text-brand">0%</span>
            </div>
            <div class="w-full bg-darkbg rounded-full h-2 mb-4 overflow-hidden border border-darkborder">
                <div id="progress_bar" class="bg-brand h-full w-0 transition-all duration-300"></div>
            </div>
            <div id="send_log" class="text-[10px] font-mono text-gray-500 h-32 overflow-y-auto space-y-1 bg-black/30 p-4 rounded-xl border border-darkborder">
                <!-- Logs via JS -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleFilters() {
    const type = document.getElementById('send_to').value;
    document.getElementById('filter_individual').classList.toggle('hidden', type !== 'individual');
    document.getElementById('filter_cargo').classList.toggle('hidden', type !== 'cargo');
    document.getElementById('filter_cong').classList.toggle('hidden', type !== 'congregacao');
}

function insertVar(val) {
    const area = document.getElementById('message_content');
    area.value += val;
}

async function saveApiConfig() {
    const url = document.getElementById('api_url').value;
    const token = document.getElementById('api_token').value;
    const btn = document.getElementById('btn_save_config');

    if(!url || !token) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Preencha a URL e o Token.', background: '#121212', color: '#fff' });
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm">sync</span> Salvando...';

    try {
        const formData = new FormData();
        formData.append('action', 'save_whatsapp_config');
        formData.append('api_url', url);
        formData.append('api_token', token);

        const res = await fetch('whatsapp.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if(data.success) {
            Swal.fire({ icon: 'success', title: 'Sucesso', text: data.message, background: '#121212', color: '#fff' });
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: data.message, background: '#121212', color: '#fff' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro Crítico', text: 'Não foi possível salvar as configurações.', background: '#121212', color: '#fff' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Salvar Configuração';
    }
}

async function sendWhatsApp() {
    const url = document.getElementById('api_url').value;
    const token = document.getElementById('api_token').value;
    const msg = document.getElementById('message_content').value;
    const type = document.getElementById('send_to').value;
    
    let filterId = null;
    if(type === 'individual') filterId = document.getElementById('select_membro')?.value;
    if(type === 'cargo') filterId = document.getElementById('select_cargo').value;
    if(type === 'congregacao') filterId = document.getElementById('select_cong').value;

    if(!url || !token || !msg) {
        Swal.fire({ icon: 'error', title: 'Campos Vazios', text: 'Preencha a API e a Mensagem.', background: '#121212', color: '#fff' });
        return;
    }

    const btn = document.getElementById('btn_send');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span> Processando Lista...';

    try {
        // 1. Buscar Destinatários via API Interna
        const res = await fetch('api/whatsapp_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ api_url: url, api_token: token, message: msg, type: type, filter_id: filterId })
        });
        const data = await res.json();

        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Erro', text: data.message, background: '#121212', color: '#fff' });
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined">send</span> Disparar Mensagens';
            return;
        }

        // 2. Iniciar Disparo Controlado (Fila no Frontend)
        document.getElementById('sending_status').classList.remove('hidden');
        const log = document.getElementById('send_log');
        log.innerHTML = `<div>[INFO] Iniciando disparo para ${data.count} contatos...</div>`;
        
        let sent = 0;
        const contacts = data.contacts;

        for (const contact of contacts) {
            try {
                // Formatar saudação dinâmica
                const hour = new Date().getHours();
                const saudacao = hour < 12 ? 'Bom dia' : (hour < 18 ? 'Boa tarde' : 'Boa noite');
                const finalMsg = msg.replace(/{nome}/g, contact.nome).replace(/{saudacao}/g, saudacao);

                // Chamada para a API da Discloud via Backend PHP (para evitar CORS)
                const apiRes = await fetch('api/whatsapp_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_direct',
                        api_url: url,
                        api_token: token,
                        message: finalMsg,
                        contact: contact
                    })
                });

                const apiData = await apiRes.json();

                if(apiData.success) {
                    log.innerHTML += `<div class="text-green-500 font-bold">[SUCCESS] Enviado para ${contact.nome}</div>`;
                } else {
                    log.innerHTML += `<div class="text-red-500">[ERROR] ${apiData.message} para ${contact.nome}</div>`;
                }
            } catch (err) {
                console.error('Erro no processamento:', err);
                log.innerHTML += `<div class="text-red-500 font-bold">[FATAL] Erro interno ao tentar enviar para ${contact.nome}.</div>`;
            }

            sent++;
            const percent = Math.round((sent / data.count) * 100);
            document.getElementById('progress_bar').style.width = percent + '%';
            document.getElementById('progress_percent').innerText = percent + '%';
            log.scrollTop = log.scrollHeight;

            // Delay de 2 segundos entre mensagens para evitar banimento
            if(sent < data.count) await new Promise(r => setTimeout(r, 2000));
        }

        Swal.fire({ icon: 'success', title: 'Envio Finalizado!', text: 'O processo de disparo foi concluído.', background: '#121212', color: '#fff' });
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">send</span> Disparar Mensagens';

    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro Crítico', text: 'Não foi possível processar o envio.', background: '#121212', color: '#fff' });
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">send</span> Disparar Mensagens';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
