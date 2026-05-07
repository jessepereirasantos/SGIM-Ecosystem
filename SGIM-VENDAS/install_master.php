<?php
/**
 * SGIM-VENDAS - Instalador Automático v1.6.0
 * Ponto de Contato: Setup + Login
 */
session_start();

$config_file = __DIR__ . '/config/database.php';
$schema_file = __DIR__ . '/database/schema.sql';

$step = $_GET['step'] ?? 1;
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 1) {
    $host = $_POST['host'] ?? 'localhost';
    $dbname = $_POST['dbname'] ?? '';
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Cria o banco se não existir
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname` text");

        // Salva arquivo de configuração
        $content = "<?php\n";
        $content .= "\$host = '$host';\n";
        $content .= "\$dbname = '$dbname';\n";
        $content .= "\$user = '$user';\n";
        $content .= "\$pass = '$pass';\n\n";
        $content .= "try {\n";
        $content .= "    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$user, \$pass);\n";
        $content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        $content .= "} catch (PDOException \$e) {\n";
        $content .= "    die(\"Erro na conexão master: \" . \$e->getMessage());\n";
        $content .= "}\n";
        
        if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0755, true);
        file_put_contents($config_file, $content);

        // Importa Schema
        if (file_exists($schema_file)) {
            $sql = file_get_contents($schema_file);
            $pdo->exec($sql);
            header("Location: install_master.php?step=2");
            exit;
        } else {
            $erro = "Arquivo schema.sql não encontrado!";
        }

    } catch (PDOException $e) {
        $erro = "Falha na conexão: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Instalador SGIM-VENDAS v1.6.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-gray-800 p-8 rounded-2xl shadow-2xl border border-gray-700">
        <h1 class="text-2xl font-bold mb-6 text-[#FFC107]">SGIM-VENDAS (Master)</h1>
        
        <?php if ($step == 1): ?>
            <p class="text-gray-400 mb-6 text-sm">Configure o banco de dados master para o seu sistema de vendas.</p>
            <?php if ($erro): ?>
                <div class="bg-red-500/20 text-red-400 p-3 rounded mb-4 text-xs border border-red-500/30"><?= $erro ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Host</label>
                    <input type="text" name="host" value="localhost" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:border-[#FFC107] outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Nome do Banco</label>
                    <input type="text" name="dbname" placeholder="ex: sgim_vendas" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:border-[#FFC107] outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Usuário MySQL</label>
                    <input type="text" name="user" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:border-[#FFC107] outline-none" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Senha MySQL</label>
                    <input type="password" name="pass" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:border-[#FFC107] outline-none">
                </div>
                <button type="submit" class="w-full bg-[#FFC107] hover:bg-yellow-500 text-black font-bold py-3 rounded-xl transition-all uppercase tracking-wider text-xs">
                    Instalar Master
                </button>
            </form>
        <?php else: ?>
            <div class="text-center">
                <div class="bg-green-500/20 text-green-400 p-4 rounded-full inline-block mb-4">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-xl font-bold mb-2">Instalação Concluída!</h2>
                <p class="text-gray-400 text-sm mb-6">O banco master foi criado e o administrador configurado.</p>
                <div class="bg-gray-900 p-4 rounded-lg text-left text-xs mb-6 border border-gray-700">
                    <p><b>URL da API:</b> https://<?= $_SERVER['HTTP_HOST'] ?>/api</p>
                    <p><b>Login Admin:</b> admin@sgim.com</p>
                    <p><b>Senha Admin:</b> sgim2026</p>
                </div>
                <a href="login.php" class="block w-full bg-[#FFC107] hover:bg-yellow-500 text-black font-bold py-3 rounded-xl transition-all text-sm uppercase">Acessar Painel Master</a>
                <p class="text-xs text-red-400 mt-4 font-bold">⚠️ EXCLUA O ARQUIVO install_master.php APÓS O USO.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
