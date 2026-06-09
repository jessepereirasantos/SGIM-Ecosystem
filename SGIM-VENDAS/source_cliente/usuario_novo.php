<?php
/**
 * SGIM ERP - REDIRECIONAMENTO DE SEGURANÇA
 * Este arquivo foi desativado e redireciona diretamente para a nova interface unificada em usuarios.php.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redireciona o fluxo para a aba unificada de Usuários e Permissões
header('Location: usuarios.php');
exit;
