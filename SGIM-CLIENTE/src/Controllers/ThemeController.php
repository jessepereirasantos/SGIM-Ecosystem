<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Models\ThemeModel;

class ThemeController extends Controller {
    private $model;

    public function __construct($pdo) {
        $this->model = new ThemeModel($pdo);
    }

    public function index() {
        $mensagem = '';
        $erro = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['acao']) && $_POST['acao'] === 'restaurar') {
                $this->model->restoreDefaults();
                $mensagem = "Cores e tema restaurados para o Padrão de Fábrica SGIM!";
            } else {
                $data = [
                    'cor_brand' => $_POST['cor_brand'] ?? '#FFC107',
                    'cor_brand_dark' => $_POST['cor_brand_dark'] ?? '#D4AF37',
                    'cor_brand_light' => $_POST['cor_brand_light'] ?? '#FFD54F',
                    'darkbg' => $_POST['darkbg'] ?? '#050505',
                    'darkcard' => $_POST['darkcard'] ?? '#121212',
                    'darkborder' => $_POST['darkborder'] ?? '#1E1E1E',
                    'lightbg' => $_POST['lightbg'] ?? '#F3F4F6',
                    'lightcard' => $_POST['lightcard'] ?? '#FFFFFF',
                    'lightborder' => $_POST['lightborder'] ?? '#E5E7EB',
                    'modo_padrao' => $_POST['modo_padrao'] ?? 'dark'
                ];

                $logo_url = $this->handleLogoUpload();
                $this->model->updateTheme($data, $logo_url);
                $mensagem = "Aparência atualizada com sucesso!";
            }
        }

        $cfg = $this->model->getTheme();
        return $this->render('theme_view', [
            'cfg' => $cfg,
            'mensagem' => $mensagem,
            'erro' => $erro
        ]);
    }

    private function handleLogoUpload() {
        if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $tmp_name = $_FILES['logo_upload']['tmp_name'];
            $name = basename($_FILES['logo_upload']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'])) {
                $new_filename = 'logo_ministerio_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                    return $upload_dir . $new_filename;
                }
            }
        }
        return null;
    }
}
