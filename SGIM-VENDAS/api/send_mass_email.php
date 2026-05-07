<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/EmailService.php';

$assunto  = trim($_POST['assunto']  ?? 'Mensagem SGIM Master');
$mensagem = trim($_POST['mensagem'] ?? '');

if (empty($mensagem)) {
    echo json_encode(['success' => false, 'message' => 'Mensagem obrigatória.']);
    exit;
}

try {
    $config = require __DIR__ . '/../config/smtp.php';

    $stmt = $pdo->query("SELECT email, nome FROM clientes WHERE email IS NOT NULL AND email != ''");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clientes)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum cliente encontrado.']);
        exit;
    }

    $sucesso = 0;
    $falhas  = 0;
    foreach ($clientes as $c) {
        $body = nl2br(htmlspecialchars($mensagem));
        if (EmailService::sendGeneric($c['email'], $assunto, $body, $config)) {
            $sucesso++;
        } else {
            $falhas++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Envio concluído: $sucesso enviados, $falhas falhas."
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
