<?php
/**
 * Script de Migração Final - SGIM-VENDAS
 * Alinha o banco de dados 100% com o Backup Validado.
 */
require_once 'config/database.php';

try {
    // 1. Renomear vendas para pedidos (se existir)
    $pdo->exec("RENAME TABLE vendas TO pedidos");
    echo "Tabela 'vendas' renomeada para 'pedidos'.<br>";
} catch (Exception $e) {
    echo "Aviso: Tabela 'vendas' não encontrada ou já renomeada.<br>";
}

try {
    // 2. Criar Tabela Pagamentos (Backup Style)
    $pdo->exec("CREATE TABLE IF NOT EXISTS pagamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        mercadopago_id VARCHAR(100) NOT NULL,
        status ENUM('PENDENTE', 'APROVADO', 'CANCELADO', 'PAGO', 'CONCLUIDO') DEFAULT 'PENDENTE',
        qr_code TEXT,
        qr_code_base64 LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
    )");
    echo "Tabela 'pagamentos' criada/verificada.<br>";

    // 3. Atualizar Tabela Pedidos (Adicionar colunas se faltarem)
    $pdo->exec("ALTER TABLE pedidos MODIFY COLUMN status ENUM('PENDENTE', 'APROVADO', 'CANCELADO', 'PAGO', 'CONCLUIDO') DEFAULT 'PENDENTE'");
    
    // Adicionar payment_id se não existir em pedidos
    $stmt = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'payment_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE pedidos ADD COLUMN payment_id VARCHAR(100) AFTER status");
        echo "Coluna 'payment_id' adicionada em 'pedidos'.<br>";
    }

    // 4. Atualizar Tabela Licenças (Garantir pedido_id)
    $stmt = $pdo->query("SHOW COLUMNS FROM licencas LIKE 'pedido_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE licencas ADD COLUMN pedido_id INT AFTER cliente_id");
        $pdo->exec("ALTER TABLE licencas ADD FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE");
        echo "Coluna 'pedido_id' adicionada em 'licencas'.<br>";
    }

    // 5. Atualizar Tabela Cupons (Backup Style)
    $pdo->exec("ALTER TABLE cupons DROP COLUMN IF EXISTS usos_atuais");
    $pdo->exec("ALTER TABLE cupons DROP COLUMN IF EXISTS limite_usos");
    
    $stmt = $pdo->query("SHOW COLUMNS FROM cupons LIKE 'usos_realizados'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE cupons ADD COLUMN usos_realizados INT DEFAULT 0, ADD COLUMN limite_uso INT DEFAULT 0");
    }

    echo "<b>Migração de Banco de Dados concluída com sucesso!</b>";

} catch (Exception $e) {
    die("Erro na migração: " . $e->getMessage());
}
