<?php
/**
 * Entry Point: Central de Novidades
 */
session_start();
require_once 'config/db.php';

$page_title = 'Novidades e Atualizações';
$current_page = 'novidades';

require_once 'includes/header.php';

use App\Controllers\NewsController;
$controller = new NewsController($pdo);
$controller->index();

require_once 'includes/footer.php';
?>
