<?php
/**
 * SGIM MASTER - Roteador de Segurança
 * Redireciona para o novo ecossistema unificado.
 */
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

// Redireciona para a nova Dashboard (Obsidian Amber)
header("Location: dashboard.php");
exit;
