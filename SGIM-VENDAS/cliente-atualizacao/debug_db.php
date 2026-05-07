<?php
/**
 * SGIM - Script de Diagnóstico de Banco de Dados
 * Use este arquivo para testar a conexão pura com o MySQL.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico de Conexão SGIM</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? 'localhost');
    $db   = trim($_POST['db'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    echo "<h3>Tentando conectar...</h3>";
    echo "<ul>
            <li>Host: <code>$host</code></li>
            <li>Banco: <code>$db</code></li>
            <li>Usuário: <code>$user</code></li>
          </ul>";

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<h2 style='color: green;'>✅ SUCESSO! A conexão funciona.</h2>";
        
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "<p>Tabelas encontradas: " . implode(', ', $tables) . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Conectado, mas o banco está VAZIO (nenhuma tabela encontrada).</p>";
        }
        
    } catch (PDOException $e) {
        echo "<h2 style='color: red;'>❌ FALHA NA CONEXÃO</h2>";
        echo "<p><b>Código do Erro:</b> " . $e->getCode() . "</p>";
        echo "<p><b>Mensagem:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
        
        if ($e->getCode() == 1045) {
            echo "<div style='background: #fee; border: 1px solid red; padding: 15px; margin-top: 20px;'>
                    <h3>Como resolver o Erro 1045 (Access Denied) no cPanel:</h3>
                    <ol>
                        <li><b>PASSO MAIS IMPORTANTE:</b> No cPanel, vá em 'Bancos de Dados MySQL'.</li>
                        <li>Procure a seção <b>'Adicionar usuário ao banco de dados'</b> (fica no final da página).</li>
                        <li>Selecione o usuário <code>$user</code> e o banco <code>$db</code>.</li>
                        <li>Clique em <b>ADICIONAR</b> e marque a opção <b>'TODOS OS PRIVILÉGIOS'</b>.</li>
                        <li>Se você já fez isso, tente <b>redefinir a senha</b> do usuário no cPanel e teste aqui novamente.</li>
                    </ol>
                  </div>";
        }
        
        if ($host === 'localhost') {
            echo "<p>💡 <b>Dica:</b> Em alguns servidores, tente trocar 'localhost' por <code>127.0.0.1</code>.</p>";
        }
    }
}
?>

<hr>
<form method="POST" style="line-height: 2em;">
    Host: <input type="text" name="host" value="localhost"><br>
    Banco: <input type="text" name="db" placeholder="usuario_sistema-sgim"><br>
    Usuário: <input type="text" name="user" placeholder="usuario_admin-sgim"><br>
    Senha: <input type="password" name="pass"><br>
    <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">Testar Conexão Agora</button>
</form>
