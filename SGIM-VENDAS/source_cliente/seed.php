<?php
/**
 * SGIM CLIENT - DATA SEEDER v1.0.0
 * Popular banco local/produção com dados fictícios para testes estruturais e visuais.
 */
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';
$seeded = false;

// 1. Verificar se o banco de dados está configurado
if (!$is_configured) {
    die("<div style='background:#121212; border:1px solid #c53030; padding:30px; color:#f56565; font-family:sans-serif; border-radius:12px; max-w:500px; margin:50px auto; text-align:center;'>
            <h2>🚨 Banco de Dados não Configurado</h2>
            <p>Por favor, realize a instalação pelo <code>setup.php</code> antes de popular os dados fictícios.</p>
         </div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed_data') {
    try {
        // Limpar dados existentes para evitar duplicidade se solicitado
        $clean = isset($_POST['clean_tables']) && $_POST['clean_tables'] === '1';
        
        if ($clean) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tables = ['membros', 'congregacoes', 'departamentos', 'cargos', 'transacoes', 'eventos'];
            foreach ($tables as $table) {
                try {
                    $pdo->exec("TRUNCATE TABLE `$table`");
                } catch (Throwable $t) {
                    // Fallback para SQLite ou se truncate falhar
                    $pdo->exec("DELETE FROM `$table`");
                }
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }

        // ── 1. INSERIR CONGREGAÇÕES ──
        $congs = [
            ['nome' => 'Igreja Central Sede', 'sigla' => 'SEDE', 'icone' => 'church', 'cnpj' => '12.345.678/0001-99', 'telefone' => '(11) 98765-4321', 'email' => 'sede@sgim.com', 'cep' => '01001-000', 'endereco' => 'Avenida Principal', 'numero' => '1000', 'bairro' => 'Centro', 'cidade' => 'São Paulo', 'estado' => 'SP', 'status' => 'Ativa'],
            ['nome' => 'Congregação Setor Norte', 'sigla' => 'NORTE', 'icone' => 'home', 'cnpj' => '12.345.678/0002-88', 'telefone' => '(11) 98765-4322', 'email' => 'norte@sgim.com', 'cep' => '02002-000', 'endereco' => 'Rua das Palmeiras', 'numero' => '250', 'bairro' => 'Vila Nova', 'cidade' => 'São Paulo', 'estado' => 'SP', 'status' => 'Ativa'],
            ['nome' => 'Congregação Setor Sul', 'sigla' => 'SUL', 'icone' => 'apartment', 'cnpj' => '12.345.678/0003-77', 'telefone' => '(11) 98765-4323', 'email' => 'sul@sgim.com', 'cep' => '03003-000', 'endereco' => 'Estrada do Campo', 'numero' => '500', 'bairro' => 'Jardins', 'cidade' => 'São Paulo', 'estado' => 'SP', 'status' => 'Ativa']
        ];

        $stmtCong = $pdo->prepare("INSERT INTO congregacoes (nome, sigla, icone, cnpj, telefone, email, cep, endereco, numero, bairro, cidade, estado, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $congIds = [];
        foreach ($congs as $c) {
            $stmtCong->execute([$c['nome'], $c['sigla'], $c['icone'], $c['cnpj'], $c['telefone'], $c['email'], $c['cep'], $c['endereco'], $c['numero'], $c['bairro'], $c['cidade'], $c['estado'], $c['status']]);
            $congIds[] = $pdo->lastInsertId();
        }

        // ── 2. INSERIR DEPARTAMENTOS ──
        $deps = [
            ['nome' => 'Ministério de Louvor & Adoração', 'icone' => 'music_note', 'descricao' => 'Grupo de música e coral oficial da igreja.'],
            ['nome' => 'Ministério Infantil (Crianças)', 'icone' => 'child_care', 'descricao' => 'Escola bíblica e cultos voltados para crianças.'],
            ['nome' => 'União de Jovens e Adolescentes (UMAD)', 'icone' => 'groups', 'descricao' => 'Trabalhos, cultos e reuniões de jovens.'],
            ['nome' => 'Círculo de Oração Feminino', 'icone' => 'volunteer_activism', 'descricao' => 'Grupo de oração e intercessão de mulheres.']
        ];

        $stmtDep = $pdo->prepare("INSERT INTO departamentos (nome, icone, descricao, congregacao_id, status) VALUES (?, ?, ?, ?, 'Ativo')");
        $depIds = [];
        foreach ($deps as $d) {
            $stmtDep->execute([$d['nome'], $d['icone'], $d['descricao'], $congIds[0]]);
            $depIds[] = $pdo->lastInsertId();
        }

        // ── 3. INSERIR CARGOS ──
        $cargos = [
            ['nome' => 'Pastor Presidente', 'descricao' => 'Liderança máxima da instituição.', 'nivel' => 5, 'escopo' => 'global'],
            ['nome' => 'Pastor Auxiliar', 'descricao' => 'Pastores auxiliares nas congregações.', 'nivel' => 4, 'escopo' => 'local'],
            ['nome' => 'Presbítero', 'descricao' => 'Líderes de setores e pregadores oficiais.', 'nivel' => 3, 'escopo' => 'local'],
            ['nome' => 'Diácono', 'descricao' => 'Auxílio na organização dos cultos e assistência social.', 'nivel' => 2, 'escopo' => 'local'],
            ['nome' => 'Membro', 'descricao' => 'Membros regulares da igreja.', 'nivel' => 1, 'escopo' => 'local']
        ];

        $stmtCargo = $pdo->prepare("INSERT INTO cargos (nome, descricao, nivel_hierarquico, escopo, departamento_id, status) VALUES (?, ?, ?, ?, NULL, 'Ativo')");
        $cargoIds = [];
        foreach ($cargos as $cg) {
            $stmtCargo->execute([$cg['nome'], $cg['descricao'], $cg['nivel'], $cg['escopo']]);
            $cargoIds[] = $pdo->lastInsertId();
        }

        // ── 4. INSERIR MEMBROS ──
        $membros = [
            ['nome' => 'Ev. Jesse Pereira', 'email' => 'ev.jessepereira@sgim.com', 'telefone' => '(11) 99999-1111', 'genero' => 'M', 'civil' => 'Casado', 'cargo' => $cargoIds[0], 'cong' => $congIds[0]],
            ['nome' => 'Silvana Barbosa', 'email' => 'silvana@sgim.com', 'telefone' => '(11) 99999-2222', 'genero' => 'F', 'civil' => 'Casado', 'cargo' => $cargoIds[4], 'cong' => $congIds[0]],
            ['nome' => 'Carlos Eduardo Lima', 'email' => 'carlos.lima@gmail.com', 'telefone' => '(11) 98888-3333', 'genero' => 'M', 'civil' => 'Solteiro', 'cargo' => $cargoIds[2], 'cong' => $congIds[1]],
            ['nome' => 'Mariana de Souza Cruz', 'email' => 'mariana.cruz@hotmail.com', 'telefone' => '(11) 97777-4444', 'genero' => 'F', 'civil' => 'Casado', 'cargo' => $cargoIds[3], 'cong' => $congIds[1]],
            ['nome' => 'Lucas Rodrigues Santos', 'email' => 'lucas.santos@outlook.com', 'telefone' => '(11) 96666-5555', 'genero' => 'M', 'civil' => 'Solteiro', 'cargo' => $cargoIds[4], 'cong' => $congIds[2]],
            ['nome' => 'Ana Clara Oliveira', 'email' => 'ana.clara@gmail.com', 'telefone' => '(11) 95555-6666', 'genero' => 'F', 'civil' => 'Solteiro', 'cargo' => $cargoIds[4], 'cong' => $congIds[2]],
            ['nome' => 'Marcos Vinicius Neves', 'email' => 'marcos.neves@sgim.com', 'telefone' => '(11) 94444-7777', 'genero' => 'M', 'civil' => 'Divorciado', 'cargo' => $cargoIds[1], 'cong' => $congIds[2]]
        ];

        $stmtMem = $pdo->prepare("INSERT INTO membros (nome, email, telefone, genero, estado_civil, cargo_id, congregacao_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Ativo')");
        $membroIds = [];
        foreach ($membros as $m) {
            $stmtMem->execute([$m['nome'], $m['email'], $m['telefone'], $m['genero'], $m['civil'], $m['cargo'], $m['cong']]);
            $membroIds[] = $pdo->lastInsertId();
        }

        // ── 5. INSERIR TRANSAÇÕES FINANCEIRAS (Para povoar a dashboard) ──
        $transacoes = [
            ['tipo' => 'receita', 'categoria' => 'Dízimo', 'valor' => 1200.00, 'venc' => date('Y-m-10'), 'pag' => date('Y-m-10'), 'desc' => 'Dízimo do Membro Jesse Pereira', 'mem' => $membroIds[0], 'cong' => $congIds[0], 'status' => 'pago'],
            ['tipo' => 'receita', 'categoria' => 'Oferta Especial', 'valor' => 450.00, 'venc' => date('Y-m-15'), 'pag' => date('Y-m-15'), 'desc' => 'Oferta de Culto de Jovens', 'mem' => NULL, 'cong' => $congIds[0], 'status' => 'pago'],
            ['tipo' => 'receita', 'categoria' => 'Dízimo', 'valor' => 850.00, 'venc' => date('Y-m-12'), 'pag' => date('Y-m-12'), 'desc' => 'Dízimo de Silvana Barbosa', 'mem' => $membroIds[1], 'cong' => $congIds[0], 'status' => 'pago'],
            ['tipo' => 'receita', 'categoria' => 'Oferta Geral', 'valor' => 350.00, 'venc' => date('Y-m-18'), 'pag' => date('Y-m-18'), 'desc' => 'Ofertas do Culto de Domingo', 'mem' => NULL, 'cong' => $congIds[1], 'status' => 'pago'],
            
            ['tipo' => 'despesa', 'categoria' => 'Energia Elétrica', 'valor' => 380.00, 'venc' => date('Y-m-20'), 'pag' => date('Y-m-20'), 'desc' => 'Conta de Luz Templo Sede', 'mem' => NULL, 'cong' => $congIds[0], 'status' => 'pago'],
            ['tipo' => 'despesa', 'categoria' => 'Aluguel do Imóvel', 'valor' => 1100.00, 'venc' => date('Y-m-05'), 'pag' => date('Y-m-05'), 'desc' => 'Aluguel do Templo Setor Norte', 'mem' => NULL, 'cong' => $congIds[1], 'status' => 'pago'],
            ['tipo' => 'despesa', 'categoria' => 'Água e Saneamento', 'valor' => 120.00, 'venc' => date('Y-m-18'), 'pag' => date('Y-m-18'), 'desc' => 'Conta de Água Sede', 'mem' => NULL, 'cong' => $congIds[0], 'status' => 'pago'],
            ['tipo' => 'despesa', 'categoria' => 'Equipamento de Som', 'valor' => 650.00, 'venc' => date('Y-m-25'), 'pag' => NULL, 'desc' => 'Parcela do Microfone Sem Fio', 'mem' => NULL, 'cong' => $congIds[0], 'status' => 'pendente']
        ];

        $stmtTrans = $pdo->prepare("INSERT INTO transacoes (tipo, categoria, valor, data_vencimento, data_pagamento, descricao, membro_id, congregacao_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($transacoes as $t) {
            $stmtTrans->execute([$t['tipo'], $t['categoria'], $t['valor'], $t['venc'], $t['pag'], $t['desc'], $t['mem'], $t['cong'], $t['status']]);
        }

        // ── 6. INSERIR EVENTOS ──
        $eventos = [
            ['titulo' => 'Grande Culto de Adoração e Milagres', 'desc' => 'Culto especial com toda a igreja unida no Templo Sede.', 'inicio' => date('Y-m-d 19:00:00', strtotime('next Sunday')), 'fim' => date('Y-m-d 21:30:00', strtotime('next Sunday')), 'local' => 'Templo Sede', 'cong' => $congIds[0], 'dep' => NULL, 'tipo' => 'Culto'],
            ['titulo' => 'Reunião de Líderes e Diaconato', 'desc' => 'Alinhamento de metas administrativas para o próximo mês.', 'inicio' => date('Y-m-d 15:00:00', strtotime('next Saturday')), 'fim' => date('Y-m-d 17:00:00', strtotime('next Saturday')), 'local' => 'Sala de Reuniões Sede', 'cong' => $congIds[0], 'dep' => NULL, 'tipo' => 'Reunião'],
            ['titulo' => 'Congresso Geral da Juventude (UMAD)', 'desc' => 'Grande festividade de jovens com pregações e música.', 'inicio' => date('Y-m-d 18:00:00', strtotime('+2 weeks Saturday')), 'fim' => date('Y-m-d 22:00:00', strtotime('+2 weeks Sunday')), 'local' => 'Ginásio Central', 'cong' => $congIds[0], 'dep' => $depIds[2], 'tipo' => 'Congresso']
        ];

        $stmtEv = $pdo->prepare("INSERT INTO eventos (titulo, descricao, data_inicio, data_fim, local, congregacao_id, departamento_id, tipo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Agendado')");
        foreach ($eventos as $ev) {
            $stmtEv->execute([$ev['titulo'], $ev['desc'], $ev['inicio'], $ev['fim'], $ev['local'], $ev['cong'], $ev['dep'], $ev['tipo']]);
        }

        $seeded = true;
        $message = "Banco de dados populado com sucesso com dados fictícios de demonstração!";
        
        // Limpar o cache de versão de sessão para forçar recarregamento na dashboard
        unset($_SESSION['sys_version_cache']);
        unset($_SESSION['user_ctx_cache']);

    } catch (Throwable $e) {
        $error = "Falha ao popular banco de dados: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Povoamento de Testes - SGIM</title>
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
            <span class="material-symbols-outlined text-4xl">database</span>
            <h1 class="text-2xl font-bold">Instalação de Dados de Teste</h1>
        </div>
        
        <p class="text-gray-400 text-sm leading-relaxed">
            Este script popula as tabelas do seu sistema **SGIM** com um cliente fictício contendo congregações, departamentos, cargos, membros e transações financeiras de teste. Ideal para validar o dashboard e relatórios imediatamente.
        </p>

        <?php if ($seeded): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-xl text-sm flex gap-3">
                <span class="material-symbols-outlined">check_circle</span>
                <div>
                    <p class="font-bold">Sucesso!</p>
                    <p class="text-xs mt-1"><?= htmlspecialchars($message) ?></p>
                </div>
            </div>
            
            <div class="flex flex-col gap-3 pt-4">
                <a href="login.php" class="w-full bg-[#FFC107] hover:bg-yellow-500 text-black font-bold py-3 px-6 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">login</span>
                    IR PARA LOGIN
                </a>
                <a href="dashboard.php" class="w-full bg-white/5 hover:bg-white/10 text-gray-300 font-bold py-3 px-6 rounded-xl transition-all border border-white/10 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">dashboard</span>
                    IR PARA DASHBOARD
                </a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl text-sm flex gap-3">
                    <span class="material-symbols-outlined">error</span>
                    <div>
                        <p class="font-bold">Erro técnico</p>
                        <p class="text-xs mt-1"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 pt-2">
                <input type="hidden" name="action" value="seed_data">
                
                <div class="flex items-center gap-3 bg-white/5 p-4 rounded-xl border border-white/5">
                    <input type="checkbox" id="clean_tables" name="clean_tables" value="1" checked class="w-4 h-4 rounded border-gray-300 text-[#FFC107] focus:ring-[#FFC107] bg-darkbg">
                    <label for="clean_tables" class="text-xs text-gray-300 font-semibold cursor-pointer select-none">
                        Limpar tabelas existentes antes de popular (Evita duplicados)
                    </label>
                </div>

                <div class="bg-[#FFC107]/10 border border-[#FFC107]/20 text-[#FFC107] p-4 rounded-xl text-xs flex gap-2">
                    <span class="material-symbols-outlined text-base">info</span>
                    <p>Ao clicar abaixo, congregações, cargos, membros e transações serão inseridos e vinculados automaticamente.</p>
                </div>

                <button type="submit" class="w-full bg-[#FFC107] hover:bg-yellow-500 text-black font-black py-4 px-6 rounded-xl transition-all shadow-lg shadow-[#FFC107]/10 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">data_object</span>
                    POPULAR CLIENTE FICTÍCIO AGORA
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
