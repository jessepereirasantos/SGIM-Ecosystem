<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS sistema_versoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        versao VARCHAR(20) NOT NULL UNIQUE,
        canal ENUM('stable', 'beta') DEFAULT 'stable',
        path_zip VARCHAR(255) NOT NULL,
        checksum_sha256 VARCHAR(64) NOT NULL,
        changelog JSON,
        migrations JSON,
        data_lancamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Tabela sistema_versoes criada com sucesso.\n";
    
    // Inserir uma versão inicial para teste se não existir
    $stmt = $pdo->prepare("SELECT id FROM sistema_versoes WHERE versao = '1.2.0'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $changelog = json_encode([
            'novidades' => ['Novo motor OTA v4.0', 'Rollback automático'],
            'melhorias' => ['Segurança de download', 'Validação SHA256'],
            'correcoes' => ['Correção na detecção de domínio']
        ]);
        $migrations = json_encode([
            "ALTER TABLE configuracoes ADD COLUMN last_ota_sync TIMESTAMP NULL;"
        ]);
        
        $sqlInsert = "INSERT INTO sistema_versoes (versao, canal, path_zip, checksum_sha256, changelog, migrations) 
                      VALUES ('1.2.0', 'stable', 'updates/sgim_v1.2.0.zip', 'SIMULATED_HASH', :changelog, :migrations)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute(['changelog' => $changelog, 'migrations' => $migrations]);
        echo "Versão de teste 1.2.0 inserida.\n";
    }

} catch (PDOException $e) {
    die("Erro ao criar tabela: " . $e->getMessage());
}
?>
