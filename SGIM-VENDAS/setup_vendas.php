<?php
/**
 * SETUP VENDAS - Iniciador de Instalação SGIM Master
 * Local: escolateologicaeloha.com.br/setup_vendas.php
 * 
 * Este arquivo reside no servidor de vendas para capturar os dados iniciais
 * e disparar o download automático.
 */
session_start();
require_once 'config/database.php';

// Verificar se o cliente está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$sucesso = false;

// Buscar dados do cliente para pré-preencher
$stmt = $pdo->prepare("SELECT nome, email FROM clientes WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dominio = trim($_POST['dominio'] ?? '');
    
    // Limpar o domínio (remover http, https, www e barras)
    $dominio_clean = preg_replace('/^https?:\/\//i', '', $dominio);
    $dominio_clean = preg_replace('/^www\./i', '', $dominio_clean);
    $dominio_clean = rtrim($dominio_clean, '/');

    if (empty($dominio_clean)) {
        $erro = "Por favor, informe o domínio onde o sistema será instalado.";
    } else {
        try {
            if (!$pdo->inTransaction()) $pdo->beginTransaction();

            // Buscar a licença do cliente para este pedido ou a mais recente
            $pedido_id = $_GET['pedido_id'] ?? null;
            if ($pedido_id) {
                $stmtLic = $pdo->prepare("SELECT id FROM licencas WHERE pedido_id = ? AND cliente_id = ?");
                $stmtLic->execute([$pedido_id, $usuario_id]);
            } else {
                $stmtLic = $pdo->prepare("SELECT id FROM licencas WHERE cliente_id = ? ORDER BY id DESC LIMIT 1");
                $stmtLic->execute([$usuario_id]);
            }
            
            $licenca = $stmtLic->fetch();

            if ($licenca) {
                // Vínculo Permanente: Atualiza o domínio e marca como ativa
                $stmtUpdate = $pdo->prepare("UPDATE licencas SET dominio = ?, status = 'ativa', ultimo_acesso = NOW() WHERE id = ?");
                $stmtUpdate->execute([$dominio_clean, $licenca['id']]);
                
                $pdo->commit();
                $_SESSION['setup_dominio'] = $dominio_clean;
                $sucesso = true;
            } else {
                $erro = "Licença não localizada. Por favor, contate o suporte.";
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erro = "Erro ao vincular domínio: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Configurar Instalação - SGIM Master</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #050505; color: #f8fafc; }
        .glass-card { background: rgba(10, 10, 10, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255, 193, 7, 0.1); }
        .btn-premium { background: linear-gradient(135deg, #FFC107 0%, #e6af06 100%); }
    </style>
    <?php if ($sucesso): ?>
    <script>
        // Disparar download automático ao carregar após o sucesso do vínculo
        window.onload = function() {
            setTimeout(function() {
                window.location.href = 'cliente/download.php?file=system';
            }, 1500);
        };
    </script>
    <?php endif; ?>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-2xl w-full glass-card p-10 rounded-[2.5rem] shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-yellow-500 to-yellow-600"></div>

        <?php if (!$sucesso): ?>
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-yellow-500/10 rounded-3xl mb-6">
                    <i class="fas fa-rocket text-4xl text-[#FFC107]"></i>
                </div>
                <h1 class="text-3xl font-black text-white uppercase tracking-tighter">Iniciando Instalação</h1>
                <p class="text-slate-400 mt-2">O download do seu sistema SGIM Master deve começar em instantes.</p>
                <div class="mt-4">
                    <a href="cliente/download.php?file=system" class="text-accent-gold hover:underline text-xs">
                        <i class="fas fa-download mr-1"></i> Se o download não iniciou, clique aqui.
                    </a>
                </div>
            </div>

            <form method="POST" class="space-y-6">
                <div class="bg-yellow-500/5 border border-yellow-500/10 p-6 rounded-2xl mb-8">
                    <h3 class="text-white font-bold mb-4 flex items-center gap-2">
                        <i class="fas fa-globe text-yellow-500"></i> Onde você vai instalar?
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-black text-slate-500 uppercase tracking-widest block mb-2">Domínio do seu site (Ex: meudominio.com.br)</label>
                            <input type="text" name="dominio" required placeholder="Ex: iadeeloha.com.br" 
                                   class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:border-yellow-500 outline-none transition-all">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white/5 p-4 rounded-xl border border-white/5">
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Nome do Admin</span>
                        <input type="text" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" disabled class="bg-transparent text-slate-300 w-full outline-none text-sm">
                    </div>
                    <div class="bg-white/5 p-4 rounded-xl border border-white/5">
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">E-mail de Acesso</span>
                        <input type="text" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" disabled class="bg-transparent text-slate-300 w-full outline-none text-sm">
                    </div>
                </div>

                <button type="submit" class="w-full btn-premium text-black font-black py-4 rounded-2xl uppercase tracking-widest hover:scale-[1.02] transition-all shadow-lg shadow-yellow-500/10">
                    Confirmar e Avançar
                </button>
            </form>
        <?php else: ?>
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-green-500/10 rounded-full mb-8">
                    <i class="fas fa-check-circle text-5xl text-green-500"></i>
                </div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tighter mb-4">Download Concluído!</h1>
                <p class="text-slate-300 text-lg mb-10">Agora siga estes 3 passos simples para finalizar:</p>
                
                <div class="text-left space-y-6 max-w-md mx-auto mb-12">
                    <div class="flex gap-5 items-start">
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/20 text-yellow-500 flex items-center justify-center font-black flex-shrink-0">1</div>
                        <p class="text-slate-400 text-sm">Suba o arquivo <strong>sgim_master.zip</strong> para a pasta <code>public_html</code> do seu servidor (via cPanel ou FTP).</p>
                    </div>
                    <div class="flex gap-5 items-start">
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/20 text-yellow-500 flex items-center justify-center font-black flex-shrink-0">2</div>
                        <p class="text-slate-400 text-sm">Extraia os arquivos e acesse: <br><a href="http://<?= htmlspecialchars($_SESSION['setup_dominio']) ?>/setup.php" target="_blank" class="text-yellow-500 hover:underline font-bold">http://<?= htmlspecialchars($_SESSION['setup_dominio']) ?>/setup.php <i class="fas fa-external-link-alt text-[10px] ml-1"></i></a></p>
                    </div>
                    <div class="flex gap-5 items-start">
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/20 text-yellow-500 flex items-center justify-center font-black flex-shrink-0">3</div>
                        <p class="text-slate-400 text-sm">Insira os dados do banco de dados e sua <strong>Chave de Licença</strong> para ativar o sistema.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <a href="cliente/dashboard.php" class="text-slate-500 hover:text-white uppercase tracking-widest font-black text-xs transition-all">
                        <i class="fas fa-arrow-left mr-2"></i> Voltar para minha Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mt-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-500 text-sm text-center font-bold">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?= $erro ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
