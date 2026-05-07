<?php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/EmailService.php';

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'E-mail obrigatório.']);
    exit;
}

// Buscar usuário
$stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Por segurança, não revelamos se o e-mail existe
    echo json_encode(['success' => true, 'message' => 'Se o e-mail estiver cadastrado, você receberá a nova senha.']);
    exit;
}

// Gerar nova senha temporária
$nova_senha = substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#'), 0, 10);
$hash = password_hash($nova_senha, PASSWORD_DEFAULT);

// Atualizar no banco
$pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $user['id']]);

// Enviar e-mail com nova senha
$enviado = EmailService::sendPasswordReset($user['email'], $user['nome'], $nova_senha);

if ($enviado) {
    echo json_encode(['success' => true, 'message' => 'Nova senha enviada para ' . $user['email'] . '. Verifique sua caixa de entrada.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar e-mail. Contate o suporte.']);
}
