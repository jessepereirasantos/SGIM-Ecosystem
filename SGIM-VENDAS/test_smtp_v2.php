<?php
require_once 'includes/EmailService.php';
require_once 'config/smtp.php';

$to = 'silsantos.barbosa@gmail.com'; // E-mail de teste do usuário
$nome = 'Usuario Teste SGIM';
$chave = 'TEST-SMTP-SOCKET-12345';
$pedido_id = '999';

echo "Iniciando teste de envio SMTP via Socket para $to...<br>";

if (EmailService::sendOrderApproved($to, $nome, $chave, $pedido_id)) {
    echo "<b>SUCESSO! O e-mail foi aceito pelo servidor SMTP.</b><br>";
} else {
    echo "<b>FALHA! O e-mail não pôde ser enviado. Verifique o error_log.</b><br>";
}
?>
