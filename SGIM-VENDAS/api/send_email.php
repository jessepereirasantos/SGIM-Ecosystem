<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/EmailService.php';

$para    = trim($_POST['para']     ?? '');
$assunto = trim($_POST['assunto']  ?? 'Mensagem SGIM Master');
$mensagem = trim($_POST['mensagem'] ?? '');

if (empty($para) || empty($mensagem)) {
    echo json_encode(['success' => false, 'message' => 'Destinatário e mensagem são obrigatórios.']);
    exit;
}

try {
    $config = require __DIR__ . '/../config/smtp.php';
    $result = EmailService::sendGeneric($para, $assunto, $mensagem, $config);
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'E-mail enviado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha no envio. Verifique as configurações SMTP.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
