<?php
// API para validar cupons de desconto no Checkout - SGIM-VENDAS
header('Content-Type: application/json');
require_once '../config/database.php';

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código não fornecido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ? LIMIT 1");
    $stmt->execute([$codigo]);
    $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cupom) {
        echo json_encode(['success' => false, 'message' => 'Cupom inválido ou não encontrado.']);
        exit;
    }

    // Verificar Validade
    if ($cupom['validade'] && strtotime($cupom['validade']) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success' => false, 'message' => 'Este cupom já expirou.']);
        exit;
    }

    // Verificar Limite de Usos
    if ($cupom['limite_usos'] > 0 && $cupom['usos_atuais'] >= $cupom['limite_usos']) {
        echo json_encode(['success' => false, 'message' => 'Este cupom atingiu o limite de uso.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'tipo' => $cupom['tipo'],
        'valor' => (float)$cupom['valor']
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
