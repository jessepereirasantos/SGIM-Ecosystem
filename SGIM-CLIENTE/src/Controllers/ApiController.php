<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\JwtHelper;
use PDO;

class ApiController extends Controller {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function login() {
        header('Content-Type: application/json');
        
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';

        $stmt = $this->pdo->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = ? AND status = 'Ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($senha, $user['senha'])) {
            $token = JwtHelper::encode([
                'user_id' => $user['id'],
                'exp' => time() + 3600 // 1 hora
            ]);
            echo json_encode(['success' => true, 'token' => $token, 'user' => ['nome' => $user['nome']]]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Credenciais inválidas.']);
        }
    }

    public function me() {
        header('Content-Type: application/json');
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
        
        $decoded = JwtHelper::decode($token);
        if ($decoded) {
            echo json_encode(['success' => true, 'user_id' => $decoded['user_id']]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado.']);
        }
    }
}
