<?php
namespace App\Core;

class Controller {
    protected function render($view, $data = []) {
        extract($data);
        require_once __DIR__ . "/../../src/Views/{$view}.php";
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }
}
