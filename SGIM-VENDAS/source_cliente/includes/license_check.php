<?php
// Tenta carregar o banco de dados. Como license_check.php pode ser incluído de vários níveis, 
// usamos caminhos absolutos baseados no diretório atual do script.
$db_path = __DIR__ . '/../config/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
}

// URL da API de validação - Alterado para o domínio real de produção
$api_base_url = 'https://escolateologicaeloha.com.br/api';
$domain = $_SERVER['HTTP_HOST'];

if (!isset($pdo)) {
    // Se o PDO não estiver disponível, não bloqueamos o sistema com tela branca, 
    // apenas pulamos a validação de licença neste hit.
    return;
}

// Get license info from DB
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('license_key', 'api_token', 'license_status', 'last_validation')");
$configs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $configs[$row['chave']] = $row['valor'];
}

$status = $configs['license_status'] ?? '';
$last_validation = (int)($configs['last_validation'] ?? 0);

// If not activated, redirect to login.php (which will handle activation via db.php)
if ($status !== 'active') {
    $current_page = basename($_SERVER['PHP_SELF']);
    if (!in_array($current_page, ['login.php', 'setup.php', 'index.php'])) {
        header('Location: login.php');
        exit;
    }
}

// If active, check if 24 hours have passed since last validation
if ($status === 'active' && (time() - $last_validation) > 86400) {
    $data = [
        'license_key' => $configs['license_key'] ?? '',
        'domain' => $domain,
        'api_token' => $configs['api_token'] ?? ''
    ];

    $ch = curl_init($api_base_url . '/validate-license.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Timeout: 5']); // 5 sec timeout to avoid hanging
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] === 'active') {
            // Update last_validation time
            $now = time();
            $stmtSave = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('last_validation', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmtSave->execute([$now]);
        } else {
            // Invalid or rejected
            $pdo->exec("UPDATE configuracoes SET valor = 'invalid' WHERE chave = 'license_status'");
            $current_page = basename($_SERVER['PHP_SELF']);
            if (!in_array($current_page, ['login.php', 'setup.php', 'index.php'])) {
                header('Location: login.php');
                exit;
            }
        }
    }
    // Se o servidor de vendas estiver offline, mantemos o acesso e tentamos de novo no proximo hit
}
?>
