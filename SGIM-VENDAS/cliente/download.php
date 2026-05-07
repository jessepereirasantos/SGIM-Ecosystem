<?php
/**
 * Download Service - SGIM-VENDAS
 * Gerencia o download forçado do sistema e manual.
 */
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$type = $_GET['file'] ?? '';
$files = [
    'system' => 'sgim_master.zip',
    'sistema' => 'sgim_master.zip', // Alias para compatibilidade com o modal
    'manual' => 'manual-sgim.pdf'
];

if (!isset($files[$type])) {
    die("Ação de download inválida.");
}

$filename = $files[$type];

// Caminho absoluto para a pasta de downloads do SGIM-VENDAS
$finalPath = __DIR__ . '/../downloads/' . $filename;

if (!file_exists($finalPath)) {
    // Tenta caminhos alternativos apenas se o principal falhar
    foreach ([__DIR__ . '/downloads/', __DIR__ . '/../', __DIR__ . '/../assets/'] as $dir) {
        $loc = $dir . $filename;
        if (file_exists($loc)) {
            $finalPath = $loc;
            break;
        }
    }
}

if (empty($finalPath)) {
    echo "
    <style>
        body { font-family: sans-serif; background: #050505; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #0A0A0A; padding: 40px; border-radius: 20px; border: 1px solid #1A1A1A; text-align: center; max-width: 400px; }
        h2 { color: #FFC107; margin-bottom: 10px; }
        p { font-size: 14px; opacity: 0.7; line-height: 1.6; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #FFC107; color: #000; text-decoration: none; font-weight: bold; border-radius: 8px; }
    </style>
    <div class='card'>
        <h2>Ops! Arquivo não encontrado</h2>
        <p>O arquivo <strong>$filename</strong> ainda não foi enviado para o servidor pelo administrador.</p>
        <p>Por favor, contate o suporte ou tente novamente em alguns instantes.</p>
        <a href='dashboard.php' class='btn'>Voltar para Dashboard</a>
    </div>";
    exit;
}

// Forçar Download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($finalPath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($finalPath));
readfile($finalPath);
exit;
