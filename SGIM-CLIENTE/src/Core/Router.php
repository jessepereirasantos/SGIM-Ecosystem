<?php
namespace App\Core;

class Router {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function run() {
        $route = $_GET['route'] ?? 'dashboard';
        
        // Mapeamento de Rotas (Início da Fase 1)
        $routes = [
            'tema' => ['controller' => 'App\Controllers\ThemeController', 'action' => 'index'],
            'novidades' => ['controller' => 'App\Controllers\NewsController', 'action' => 'index'],
        ];

        if (array_key_exists($route, $routes)) {
            $conf = $routes[$route];
            $controllerClass = $conf['controller'];
            $action = $conf['action'];

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass($this->pdo);
                $controller->$action();
                return;
            }
        }

        // Fallback para o legado (Arquivos .php no root)
        // Isso permite o Strangler Fig Pattern funcionar preservando as URLs antigas
        $legacy_file = __DIR__ . "/../../" . $route . ".php";
        if (file_exists($legacy_file)) {
            require_once $legacy_file;
        } else {
            // 404
            http_response_code(404);
            echo "Página não encontrada.";
        }
    }
}
