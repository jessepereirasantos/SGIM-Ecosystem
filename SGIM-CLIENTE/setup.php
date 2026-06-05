<?php
ob_start();
session_start();
// O carregamento do DB deve ser condicional, pois no setup inicial o arquivo ainda não existe.
if (file_exists('config/db.php')) {
    include_once 'config/db.php';
}

// Higienização do domínio para consulta remota (remove www)
$domain_raw = $_SERVER['HTTP_HOST'] ?? 'iadeeloha.com.br';
$domain = preg_replace('/^www\./', '', $domain_raw);

$api_base_url = 'https://escolateologicaeloha.com.br/api';

// Tentar detectar se estamos em localhost para facilitar testes
$is_local_env = false;
$host_clean = preg_replace('/:.*$/', '', $_SERVER['HTTP_HOST'] ?? ''); // Remove a porta
if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || $host_clean === 'localhost' || $host_clean === '127.0.0.1') {
    $is_local_env = true;
    $api_base_url = 'http://localhost/api'; 
}

// 1. Verificação de Domínio Pré-Ativado (Master-Key) - Lookup Autoritativo
$is_pre_activated = false;
$pre_activated_key = $_POST['pre_activated_key'] ?? '';

// FORÇAR LIMPEZA DE TRAVAS ANTIGAS SE ACESSADO DIRETAMENTE
if (isset($_GET['force_clean']) || isset($_POST['db_host'])) {
    $locks = [
        __DIR__ . '/.installed',
        __DIR__ . '/config/.installed',
        dirname(__DIR__) . '/.installed'
    ];
    foreach ($locks as $lock) {
        if (file_exists($lock)) @unlink($lock);
    }
}

// O setup.php agora só é acessível se o domínio já estiver vinculado no VENDAS
try {
    if ($is_local_env) {
        $is_pre_activated = true;
        $pre_activated_key = 'SGIM-DEV-LOCAL-KEY-999';
    } else {
        $check_url = $api_base_url . '/check-domain.php?domain=' . urlencode($domain);
        $ch = curl_init($check_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['success']) && $result['success'] && isset($result['is_activated']) && $result['is_activated']) {
            $is_pre_activated = true;
            $pre_activated_key = $result['license_key'] ?? '';
        }
    }

    // Bloqueio profissional: Se não houver registro de ativação, impede a instalação
    if (!$is_pre_activated) {
        ?>
        <!DOCTYPE html>
        <html class="dark" lang="pt-BR">
        <head>
            <meta charset="utf-8"/>
            <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
            <title>Bloqueio de Segurança - SGIM</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet"/>
            <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
            <style>
                body { font-family: 'Public Sans', sans-serif; background-color: #050505; color: #fff; }
                .glass { background: rgba(18, 18, 18, 0.8); backdrop-filter: blur(10px); border: 1px solid #1e1e1e; }
            </style>
        </head>
        <body class="min-h-screen flex items-center justify-center p-4">
            <div class="glass max-w-lg w-full p-8 rounded-2xl shadow-2xl border border-white/5 space-y-6">
                <div class="flex items-center gap-4 text-[#FFC107]">
                    <span class="material-symbols-outlined text-4xl">security</span>
                    <h1 class="text-2xl font-bold">Bloqueio de Segurança SGIM Master</h1>
                </div>
                <p class="text-gray-400 text-sm">O domínio (<b><?= htmlspecialchars($domain) ?></b>) ainda não possui uma licença vinculada.</p>
                <hr class="border-white/10">
                <div class="space-y-3">
                    <p class="text-sm font-semibold text-white">Ações Necessárias:</p>
                    <ol class="list-decimal pl-5 text-gray-400 text-xs space-y-2">
                        <li>Acesse seu <b>Painel de Compras</b> no site onde adquiriu o sistema.</li>
                        <li>Localize seu pedido e clique no botão <b>SITE</b> (Vincular Domínio).</li>
                        <li>Digite exatamente o domínio: <u class="text-[#FFC107] font-mono"><?= htmlspecialchars($domain) ?></u></li>
                    </ol>
                </div>
                <div class="bg-[#FFC107]/10 border border-[#FFC107]/20 text-[#FFC107] p-4 rounded-xl text-xs flex gap-2">
                    <span class="material-symbols-outlined text-base">info</span>
                    <p>Após vincular, recarregue esta página para prosseguir com a configuração.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Throwable $t) {
    error_log("Master-Key Check Error: " . $t->getMessage());
    ?>
    <!DOCTYPE html>
    <html class="dark" lang="pt-BR">
    <head>
        <meta charset="utf-8"/>
        <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
        <title>Erro de Licença - SGIM</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
        <style>
            body { font-family: 'Public Sans', sans-serif; background-color: #050505; color: #fff; }
            .glass { background: rgba(18, 18, 18, 0.8); backdrop-filter: blur(10px); border: 1px solid #1e1e1e; }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-4">
        <div class="glass max-w-lg w-full p-8 rounded-2xl shadow-2xl border border-white/5 space-y-6 text-center">
            <div class="mb-4 inline-flex p-4 bg-red-500/10 rounded-full text-red-500">
                <span class="material-symbols-outlined text-5xl">warning</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Erro de Conexão com Servidor de Licenças</h1>
            <p class="text-gray-400 text-sm leading-relaxed">Não foi possível estabelecer contato com o servidor de ativação. Por favor, tente novamente em instantes.</p>
            <p class="text-xs text-gray-500 font-mono">Erro: <?= htmlspecialchars($t->getMessage()) ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Se já houver conexão ativa, manda para o Login ou Dashboard
if (isset($pdo) && $pdo !== null) {
    if (isset($_SESSION['user_id'])) {
        header('Location: dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

$db_configured = false;
$config_file = __DIR__ . '/config/db_config.php';
$installed_file = __DIR__ . '/.installed';

if (file_exists($config_file) && file_exists($installed_file)) {
    $db_configured = true;
}

if ($db_configured) {
    ?>
    <!DOCTYPE html>
    <html class="dark" lang="pt-BR">
    <head>
        <meta charset="utf-8"/>
        <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
        <title>Setup Concluído - SGIM</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
        <style>
            body { font-family: 'Public Sans', sans-serif; background-color: #050505; color: #fff; }
            .glass { background: rgba(18, 18, 18, 0.8); backdrop-filter: blur(10px); border: 1px solid #1e1e1e; }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-4">
        <div class="glass max-w-md w-full p-8 rounded-2xl text-center shadow-2xl border border-white/5">
            <div class="mb-6 inline-flex p-4 bg-green-500/10 rounded-full text-green-500">
                <span class="material-symbols-outlined text-5xl">check_circle</span>
            </div>
            <h1 class="text-2xl font-bold mb-2">Banco de Dados Configurado!</h1>
            <p class="text-gray-400 mb-8 text-sm">O sistema já possui conexão com o banco de dados. Você já pode prosseguir para a ativação da sua licença.</p>
            
            <div class="flex flex-col gap-3">
                <a href="login.php" class="w-full bg-[#FFC107] hover:bg-yellow-500 text-black font-bold py-3 px-6 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">login</span>
                    IR PARA LOGIN
                </a>
                <p class="text-xs text-gray-500 mt-4">Deseja reconfigurar? Remova o arquivo <code>config/db_config.php</code> no seu servidor.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aplicando trim() para evitar espaços invisíveis
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = trim($_POST['db_pass'] ?? '');
    
    $admin_nome = trim($_POST['admin_nome'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass = trim($_POST['admin_pass'] ?? '');

    if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_nome) || empty($admin_email) || empty($admin_pass)) {
        $erro = "Revisão necessária: Por favor, preencha todos os campos obrigatórios.";
    } else {
        try {
            // 1. Testar conexão (Tenta localhost e 127.0.0.1)
            $connection_success = false;
            $hosts_to_try = [$db_host];
            if ($db_host === 'localhost') {
                $hosts_to_try[] = '127.0.0.1';
            } else if ($db_host === '127.0.0.1') {
                $hosts_to_try[] = 'localhost';
            }
            
            $last_pdo_error = '';
            foreach ($hosts_to_try as $host) {
                try {
                    // Usando aspas simples para a DSN para evitar interpolação indesejada
                    $dsn = "mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $db_host = $host; // Atualiza para o host que funcionou
                    $connection_success = true;
                    break;
                } catch (PDOException $e) {
                    $last_pdo_error = $e->getMessage();
                }
            }

            if (!$connection_success) {
                throw new Exception("Erro de Conexão: " . $last_pdo_error);
            }
            
            // 2. Verificar e Importar Schema se necessário (Autodidata)
            $stmtCheck = $pdo->query("SHOW TABLES LIKE 'usuarios'");
            if ($stmtCheck->rowCount() == 0) {
                $schema_file = __DIR__ . '/database/schema.sql';
                if (!file_exists($schema_file)) {
                    throw new Exception("Arquivo de estrutura (schema.sql) não encontrado em database/.");
                }
                
                $sql_content = file_get_contents($schema_file);
                
                // Remover comentários SQL
                $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                
                // Dividir por ponto e vírgula
                $queries = array_filter(array_map('trim', explode(';', $sql_content)));
                
                foreach ($queries as $query) {
                    if (!empty($query)) {
                        try {
                            $pdo->exec($query);
                        } catch (PDOException $e) {
                            // Ignora erros de "tabela já existe" durante a importação
                            if ($e->getCode() != '42S01') {
                                throw $e;
                            }
                        }
                    }
                }
            }
            
            // 3. Criar usuário admin (Vínculo automático com Admin Total Fase 2)
            $senha_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmtAdmin = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel_acesso, cargo_id, ativo) VALUES (?, ?, ?, 'admin', 1, 1) ON DUPLICATE KEY UPDATE cargo_id=1, senha=VALUES(senha)");
            $stmtAdmin->execute([$admin_nome, $admin_email, $senha_hash]);
            // 4. Salvar Configuração (Garante que o diretório existe e tem permissão)
            $config_dir = __DIR__ . '/config';
            if (!is_dir($config_dir)) {
                if (!mkdir($config_dir, 0755, true)) {
                    // Se falhar a criação da pasta, tentaremos salvar na raiz como último recurso
                    error_log("SGIM Error: Não foi possível criar o diretório /config");
                }
            }
            
            // Tenta dar permissão se a pasta já existir mas estiver bloqueada
            if (is_dir($config_dir) && !is_writable($config_dir)) {
                chmod($config_dir, 0755);
            }

            $db_config_content = "<?php\n";
            $db_config_content .= "// ARQUIVO DE CONFIGURAÇÃO CRÍTICO - NÃO EXCLUIR\n";
            $db_config_content .= "\$is_configured = true;\n";
            $db_config_content .= "\$is_installed_local = true;\n";
            $db_config_content .= "\$host = '{$db_host}';\n";
            $db_config_content .= "\$dbname = '{$db_name}';\n";
            $db_config_content .= "\$user = '{$db_user}';\n";
            $db_config_content .= "\$pass = '{$db_pass}';\n\n";
            $db_config_content .= "try {\n";
            $db_config_content .= "    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$user, \$pass);\n";
            $db_config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $db_config_content .= "} catch (PDOException \$e) {\n";
            $db_config_content .= "    \$is_configured = false;\n";
            $db_config_content .= "    \$pdo = null;\n";
            $db_config_content .= "}\n";
            $db_config_content .= "?>";
            
            // TENTA GRAVAR EM TRÊS LUGARES PARA GARANTIR PERSISTÊNCIA NO CPANEL
            $success_write = false;
            $possible_paths = [
                $config_dir . '/db_config.php',
                __DIR__ . '/db_config.php',
                dirname(__DIR__) . '/db_config.php'
            ];

            foreach ($possible_paths as $path) {
                if (file_put_contents($path, $db_config_content) !== false) {
                    $success_write = true;
                    error_log("SGIM Success: Arquivo de config gravado em $path");
                }
            }

            if (!$success_write) {
                throw new Exception("ERRO CRÍTICO DE PERMISSÃO: O servidor HostGator bloqueou a gravação do arquivo de configuração em todos os locais possíveis. Por favor, verifique as permissões de pasta (CHMOD 755).");
            }

            // CRIAR O ARQUIVO .installed EM MÚLTIPLOS LOCAIS (Trava Física)
            $installed_paths = [
                __DIR__ . '/.installed',
                $config_dir . '/.installed',
                dirname(__DIR__) . '/.installed'
            ];

            foreach ($installed_paths as $path) {
                file_put_contents($path, date('Y-m-d H:i:s'));
            }
            
            // 5. Automação Master-Key (Sempre pré-ativado agora no novo fluxo)
            $stmtLock = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('license_key', ?), ('license_status', 'active') ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmtLock->execute([$pre_activated_key]);

            // 6. CRÍTICO: Registrar a versão instalada no banco de dados
            // Sem isso, o OTA mostra "0.0.0" para todos os clientes novos.
            $sgim_version_file = __DIR__ . '/database/schema.sql';
            $versao_instalada = '1.1.66'; // Versão desta release do instalador
            $stmtVersao = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmtVersao->execute([$versao_instalada]);
            
            // Redirecionamento automático para a dashboard do cliente instalada
            header('Location: dashboard.php?installed=1');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 1045 || strpos($e->getMessage(), 'Access denied') !== false) {
                $erro = "<div class='space-y-3 uppercase font-bold text-xs'>
                            <p class='text-lg'>⚠️ ALERTA DE PERMISSÃO (cPanel)</p>
                            <p>O MySQL rejeitou o acesso. Siga estes 3 passos no cPanel para resolver:</p>
                            <ol class='list-decimal pl-5 space-y-1'>
                                <li>Vá em 'Bancos de Dados MySQL'</li>
                                <li>No final da página, localize <b>'Adicionar usuário ao banco de dados'</b></li>
                                <li>Selecione o usuário e o banco e clique em <b>ADICIONAR</b> e marque <b>TODOS OS PRIVILÉGIOS</b>.</li>
                            </ol>
                            <p class='mt-2 text-blue-400'>💡 Dica: Se o erro persistir, tente trocar o host 'localhost' por '127.0.0.1'</p>
                         </div>";
            } else {
                $erro = "Erro de Conexão: " . htmlspecialchars($e->getMessage());
            }
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
}

$db_error_msg = isset($_GET['db_error']) ? 'A conexão com o banco de dados falhou. Reconfigure abaixo.' : '';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Setup SGIM - Configuração</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#FFC107",
                        "background-light": "#f8f6f6",
                        "background-dark": "#050505",
                        "surface-dark": "#121212",
                        "border-dark": "#1e1e1e"
                    },
                    fontFamily: {
                        "display": ["Public Sans", "sans-serif"]
                    }
                }
            }
        }
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
    <style>body { font-family: 'Public Sans', sans-serif; }</style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen flex flex-col items-center py-10 px-4">
    <div class="w-full max-w-3xl flex flex-col gap-6">
        <div class="flex flex-col items-center text-center gap-3 mb-4">
            <div class="bg-primary/10 p-4 rounded-xl border border-primary/20">
                <span class="material-symbols-outlined text-primary text-4xl">dns</span>
            </div>
            <div>
                <h1 class="text-slate-900 dark:text-slate-100 text-3xl font-bold tracking-tight">Configuração do Banco</h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2 text-sm">Configure o acesso ao MySQL para ativar seu SGIM.</p>
            </div>
        </div>

        <?php if ($is_pre_activated): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-6 rounded-xl flex items-start gap-4 shadow-lg mb-6">
                <span class="material-symbols-outlined text-3xl">verified</span>
                <div>
                    <h3 class="font-bold text-lg uppercase tracking-tight">Ativação Detectada!</h3>
                    <p class="text-sm mt-1">Este domínio (<b><?= $domain ?></b>) já possui uma licença SGIM Master ativa.</p>
                    <p class="text-xs text-gray-400 mt-2 italic">Configure o banco de dados abaixo para restaurar o sistema automaticamente.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-500 p-6 rounded-xl flex items-start gap-4 shadow-lg animate-pulse">
                <span class="material-symbols-outlined text-3xl">error_outline</span>
                <div class="text-sm font-medium"><?= $erro ?></div>
            </div>
        <?php endif; ?>

        <div class="bg-blue-500/10 border border-blue-500/20 text-blue-400 p-5 rounded-xl flex gap-4 text-sm leading-relaxed">
            <span class="material-symbols-outlined text-2xl">help_center</span>
            <div>
                <strong>Passo Crítico cPanel:</strong> Você DEVE criar o banco, criar o usuário E depois usar a opção "Adicionar usuário ao banco" habilitando <b>TODOS OS PRIVILÉGIOS</b>. Sem isso, o acesso será negado mesmo com a senha certa.
            </div>
        </div>

        <form method="POST" class="flex flex-col gap-6">
            <!-- Banco de Dados -->
            <div class="bg-white dark:bg-surface-dark border border-slate-200 dark:border-border-dark rounded-xl p-8 shadow-xl">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">database</span>
                    Dados de Conexão
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2 flex flex-col gap-2 mb-4">
                        <label class="text-sm font-medium text-slate-300">Domínio de Instalação (Ex: meudominio.com.br)</label>
                        <input name="dominio_instalacao" value="<?= htmlspecialchars($_POST['dominio_instalacao'] ?? $_SERVER['HTTP_HOST']) ?>" required class="w-full px-4 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="text" placeholder="meudominio.com.br"/>
                        <p class="text-[10px] text-slate-500 italic">Confirme o domínio onde o sistema está sendo instalado agora.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">Host (Geralmente <code>localhost</code>)</label>
                        <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required class="w-full px-4 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="text"/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">Nome do Banco (Com prefixo)</label>
                        <input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required class="w-full px-4 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="text" placeholder="ex: jessep71_sistema-sgim"/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">Usuário do Banco (Com prefixo)</label>
                        <input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required class="w-full px-4 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="text" placeholder="ex: jessep71_admin-sgim"/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">Senha do Banco</label>
                        <div class="relative">
                            <input id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" class="w-full pl-4 pr-12 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="password"/>
                            <button type="button" onclick="togglePassword('db_pass', 'eye-db')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-primary">
                                <span id="eye-db" class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conta Admin -->
            <div class="bg-white dark:bg-surface-dark border border-slate-200 dark:border-border-dark rounded-xl p-8 shadow-xl">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">person_add</span>
                    Primeiro Usuário (Administrador)
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2 flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">Nome Completo</label>
                        <input name="admin_nome" value="<?= htmlspecialchars($_POST['admin_nome'] ?? '') ?>" required class="w-full px-4 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="text" placeholder="Jesse Pereira"/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">E-mail de Login</label>
                        <input name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required class="w-full px-4 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="email" placeholder="admin@igreja.com.br"/>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-300">Senha de Acesso</label>
                        <div class="relative">
                            <input id="admin_pass" name="admin_pass" required class="w-full pl-4 pr-12 py-3 bg-background-dark border border-border-dark rounded-lg text-slate-100 focus:ring-2 focus:ring-primary/50 outline-none transition-all" type="password" placeholder="••••••••"/>
                            <button type="button" onclick="togglePassword('admin_pass', 'eye-admin')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-primary">
                                <span id="eye-admin" class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-yellow-500 text-black font-bold py-4 rounded-xl transition-all shadow-lg hover:scale-[1.01] active:scale-[0.99] uppercase">
                ⚙️ Finalizar e Instalar Agora
            </button>
        </form>
        
        <p class="text-center text-slate-500 text-xs mt-4">
            Em caso de dúvidas persistentes, acesse <code>/debug_db.php</code> para diagnóstico técnico.
        </p>
    </div>
</body>
</html>
