<?php
/**
 * SGIM GATEWAY - BOOTSTRAP BRIDGE v1.1.66
 */
$activeRelease = __DIR__ . '/releases/current/index.php';

if (file_exists($activeRelease)) {
    // Carrega a versão atômica (OTA v3.0+)
    require_once $activeRelease;
    exit;
}

// Modo Legado (v1.0): Verifica se o banco está configurado antes de carregar
$dbConfigExists = file_exists(__DIR__ . '/config/db_config.php')
    || file_exists(__DIR__ . '/db_config.php');

// Se não há banco de dados configurado, redireciona para o setup (evita Erro 500)
if (!$dbConfigExists) {
    header('Location: setup.php');
    exit;
}

// Fallback: Carrega o bootstrap legado com caminho absoluto
if (file_exists(__DIR__ . '/src/bootstrap.php')) {
    require_once __DIR__ . '/src/bootstrap.php';

    if (!isset($_SESSION['user_id'])) {
        $route = isset($_GET['route']) ? $_GET['route'] : '';
        if ($route !== 'login') {
            header('Location: login.php');
            exit;
        }
    }

    if (isset($pdo) && class_exists('App\\Core\\Router')) {
        $router = new \App\Core\Router($pdo);
        $router->run();
    } else {
        die("ERRO: O sistema não conseguiu carregar as dependências vitais.");
    }
} else {
    // Fallback final: redireciona para setup
    header('Location: setup.php');
    exit;
}