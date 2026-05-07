<?php
/**
 * SGIM - Script de Emergência para Diagnosticar Tela Branca
 * Suba para a raiz do seu site e acesse: iadeeloha.com.br/debug_sgim.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>SGIM - Diagnóstico de Erros (Debug)</h2>";
echo "<hr>";

$config_file = __DIR__ . '/config/db_config.php';

echo "<h3>1. Verificando Arquivo de Configuração</h3>";
if (file_exists($config_file)) {
    echo "<li style='color:green'>ARQUIVO ENCONTRADO em: <b>$config_file</b></li>";
    
    // Testar leitura sem incluir (segurança)
    $content = file_get_contents($config_file);
    if (strpos($content, '$pdo = new PDO') !== false) {
        echo "<li style='color:green'>CONTEÚDO DO ARQUIVO: Parece ter a conexão PDO.</li>";
    } else {
        echo "<li style='color:red'>AVISO: O arquivo existe mas parece estar vazio ou sem a lógica de conexão.</li>";
    }
    
    echo "<h3>2. Testando Inclusão e Conexão</h3>";
    try {
        include $config_file;
        if (isset($pdo) && $pdo instanceof PDO) {
            echo "<li style='color:green'>SUCESSO: Variável \$pdo carregada e conectada!</li>";
            
            $stmt = $pdo->query("SELECT version()");
            echo "<li>MySQL Versão: " . $stmt->fetchColumn() . "</li>";
        } else {
            echo "<li style='color:red'>ERRO: O arquivo foi incluído mas a variável \$pdo não foi criada.</li>";
        }
    } catch (Throwable $e) {
        echo "<li style='color:red'>ERRO FATAL NA CONEXÃO: " . $e->getMessage() . "</li>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<li style='color:red'>ARQUIVO NÃO ENCONTRADO! O arquivo <b>config/db_config.php</b> não existe no servidor.</li>";
    echo "<p>Isso explica a tela branca ou o redirecionamento para o setup.</p>";
}

echo "<hr>";
echo "<h4>Próximo Passo:</h4>";
echo "<li>Se o arquivo não existe, você precisa rodar o <b>setup.php</b> uma última vez para criá-lo.</li>";
echo "<li>Se ele existe mas dá erro, verifique se as credenciais (usuário/senha) no cPanel ainda são as mesmas.</li>";
?>
