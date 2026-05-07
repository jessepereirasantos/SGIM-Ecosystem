<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$codigo = $_GET['codigo'] ?? '';
$valorPedido = (float)($_GET['valor_pedido'] ?? PRODUCT_PRICE);

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código não informado']);
    exit;
}

$resultado = aplicarCupom($codigo, $valorPedido, $pdo);

echo json_encode($resultado);
