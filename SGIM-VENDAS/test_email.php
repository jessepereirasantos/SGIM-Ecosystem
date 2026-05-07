<?php
/**
 * Teste de Envio de E-mail de Licença - SGIM-VENDAS
 */
require_once 'config/database.php';
require_once 'includes/EmailService.php';

echo "<h2>Teste de Envio de E-mail de Licença</h2>";

// Dados de teste (substitua pelo seu e-mail se quiser testar real)
$teste_email = 'silvanabarbosa@exemplo.com'; // O usuário pode alterar aqui
$teste_nome = 'Cliente Teste';
$teste_chave = 'SGIM-TEST-1234-ABCD';
$teste_pedido = 9999;

echo "Tentando enviar e-mail para: $teste_email...<br>";

try {
    $enviado = EmailService::sendOrderApproved($teste_email, $teste_nome, $teste_chave, $teste_pedido);
    
    if ($enviado) {
        echo "<b style='color:green;'>SUCESSO!</b> O e-mail foi aceito pelo servidor SMTP.";
    } else {
        echo "<b style='color:red;'>FALHA!</b> O e-mail não foi enviado. Verifique o log de erros do PHP.";
    }
} catch (Exception $e) {
    echo "<b style='color:red;'>ERRO:</b> " . $e->getMessage();
}

echo "<br><br><a href='cliente/dashboard.php'>Voltar para Dashboard</a>";
?><br>
