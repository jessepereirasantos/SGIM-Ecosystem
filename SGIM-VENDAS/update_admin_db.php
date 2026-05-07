<?php
require_once 'config/database.php';
try {
    // 1. Tabela de Notificações para o Administrador
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notificacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        icone VARCHAR(50) DEFAULT 'info',
        visto TINYINT DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabela 'admin_notificacoes' verificada/criada!<br>";

    // 2. Tabela de Configurações Globais (Fonte de Verdade)
    $pdo->exec("CREATE TABLE IF NOT EXISTS sistema_config (
        chave VARCHAR(50) PRIMARY KEY,
        valor TEXT,
        ultima_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Inicializar Configurações se não existirem
    $pdo->exec("INSERT IGNORE INTO sistema_config (chave, valor) VALUES ('ultima_versao', '1.1.0')");
    $pdo->exec("INSERT IGNORE INTO sistema_config (chave, valor) VALUES ('changelog_json', '{}')");
    $pdo->exec("INSERT IGNORE INTO sistema_config (chave, valor) VALUES ('download_url', 'downloads/sgim_master.zip')");

    echo "Tabela 'sistema_config' verificada/criada!<br>";
    
    // Inserir notificação de boas-vindas se estiver vazia
    $count = $pdo->query("SELECT COUNT(*) FROM admin_notificacoes")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO admin_notificacoes (titulo, mensagem, icone) VALUES ('Sistema Sincronizado', 'O painel administrativo agora suporta notificações e API autoritativa.', 'check_circle')");
    }

    echo "<b>Migração concluída com sucesso!</b>";
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage();
}
?>
