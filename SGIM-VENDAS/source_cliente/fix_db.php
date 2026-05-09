<?php
require 'config/db.php';
try {
    $pdo->exec("DROP TABLE IF EXISTS comunicacoes");
    $pdo->exec("CREATE TABLE comunicacoes (id INT AUTO_INCREMENT PRIMARY KEY, assunto VARCHAR(255) NOT NULL, mensagem TEXT NOT NULL, canal ENUM('email', 'whatsapp') DEFAULT 'email', status ENUM('rascunho', 'enviado') DEFAULT 'enviado', data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    echo "DB Fixed!";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
