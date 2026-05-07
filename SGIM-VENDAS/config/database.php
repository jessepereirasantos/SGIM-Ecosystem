<?php
// SGIM-VENDAS - Configuração Unificada de Produção
$host = 'localhost';
$dbname = 'hg9a3205_sgim-vendas'; // Usando o nome com hífen conforme cPanel
$username = 'hg9a3205_sgim-user-vendas';
$password = 'jjds06091985';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Constantes OBRIGATÓRIAS para o Layout e Funcionamento do Master
    if (!defined('SITE_URL')) define('SITE_URL', 'https://escolateologicaeloha.com.br');
    if (!defined('MP_PUBLIC_KEY')) define('MP_PUBLIC_KEY', 'APP_USR-14185dd1-f6c5-44c3-9239-a3db8f0c8f41');
    if (!defined('MP_ACCESS_TOKEN')) define('MP_ACCESS_TOKEN', 'APP_USR-8327652982952175-031321-768d755f1ef4591220750bea6c6e00ba-233044452');
    if (!defined('PRODUCT_PRICE')) define('PRODUCT_PRICE', 3597.00);
    if (!defined('PRODUCT_NAME')) define('PRODUCT_NAME', 'SGIM Master');

} catch (PDOException $e) {
    die("<div style='padding:20px; background:#fff5f5; color:#c53030; border:1px solid #feb2b2; border-radius:8px; font-family:sans-serif;'>
        <h3>🚨 Erro de Conexão com o Banco de Dados</h3>
        <p>Não foi possível conectar ao banco de dados do Master.</p>
        <p><b>Detalhe técnico:</b> " . $e->getMessage() . "</p>
        <p>Verifique as credenciais no arquivo <code>config/database.php</code>.</p>
    </div>");
}

/**
 * Função Universal de Migração Defensiva (SGIM v5.0)
 * Garante compatibilidade com HostGator e servidores legados.
 */
function ensureColumnExists($pdo, $table, $column, $definition) {
    try {
        if (!($pdo instanceof PDO)) return false;
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
            return true;
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar/adicionar coluna $column na tabela $table: " . $e->getMessage());
    }
    return false;
}
?>
