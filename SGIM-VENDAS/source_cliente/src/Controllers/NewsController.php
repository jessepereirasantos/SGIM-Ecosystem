<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\NewsModel;

class NewsController extends Controller {
    private $model;

    public function __construct($pdo) {
        $this->model = new NewsModel($pdo);
    }

    public function index() {
        // Marcar como lido ao visualizar
        $this->model->markAsRead();
        $novidades = $this->model->getAll();

        return $this->render('news_view', [
            'novidades' => $novidades
        ]);
    }
}
