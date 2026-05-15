<?php
/**
 * SGIM GATEWAY - BOOTSTRAP BRIDGE
 * Este arquivo atua como o ponto de entrada atômico para o sistema OTA.
 */
$activeRelease = __DIR__ . '/releases/current/index.php';

if (file_exists($activeRelease)) {
    // Carrega a versão atômica (v3.0+)
    require_once $activeRelease;
    exit;
}

// Fallback: Modo Legado (v1.0)
if (file_exists('src/bootstrap.php')) {
    require_once 'src/bootstrap.php';
    
    use App\Core\Router;

    if (!isset($_SESSION['user_id'])) {
        $route = $_GET['route'] ?? '';
        if ($route !== 'login') {
            header('Location: login.php');
            exit;
        }
    }

    if (isset($pdo)) {
        $router = new Router($pdo);
        $router->run();
    } else {
        die("ERRO CRÍTICO: Conexão com o banco de dados não estabelecida. Verifique o arquivo config/database.php.");
    }
} else {
    die("SISTEMA OFFLINE: Arquivos base não encontrados. Se você acabou de atualizar, aguarde o redirecionamento ou limpe o cache.");
}
?>
