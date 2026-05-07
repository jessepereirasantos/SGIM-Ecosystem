<?php
/**
 * Entry Point: Aparência e Temas
 * Este arquivo agora segue o padrão MVC (Fase 1)
 */
ob_start();
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
require_once 'src/Core/Controller.php';
require_once 'src/Core/Model.php';
require_once 'src/Models/ThemeModel.php';
require_once 'src/Controllers/ThemeController.php';

use App\Controllers\ThemeController;

$controller = new ThemeController($pdo);
$controller->index();
?>
