<?php
/**
 * Script de Recuperação de Acesso - SGIM-VENDAS
 * Redefine o usuário administrador.
 */
require_once 'config/database.php';

$email = 'teste@escolateologicaeloha.com.br'; // E-mail oficial configurado no SMTP
$senha = 'admin123';        // Senha padrão temporária
$hash = password_hash($senha, PASSWORD_DEFAULT);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    echo "Limpando usuários antigos (Opcional)...<br>";
    // $pdo->exec("DELETE FROM usuarios WHERE email = '$email'");

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, nivel = 'admin', nome = 'Administrador Master' WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        echo "<b>Usuário '$email' atualizado com sucesso!</b><br>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES ('Administrador Master', ?, ?, 'admin')");
        $stmt->execute([$email, $hash]);
        echo "<b>Usuário '$email' criado com sucesso!</b><br>";
    }

    echo "Dados de Acesso:<br>";
    echo "E-mail: <b>$email</b><br>";
    echo "Senha: <b>$senha</b><br><br>";
    echo "<a href='login.php'>Ir para Login</a>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
