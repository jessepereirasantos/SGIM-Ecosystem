<?php
require_once 'config/database.php';
try {
    // 1. Tabela de Usuários (Unificada para Admin e Cliente)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        nivel ENUM('admin', 'cliente') DEFAULT 'cliente',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabela 'usuarios' verificada/criada.<br>";

    // 2. Ajustes na Tabela Clientes
    $columns = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'usuario_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN usuario_id INT AFTER id, ADD FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE");
        echo "Coluna 'usuario_id' adicionada em 'clientes'.<br>";
    }

    $columns = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'referral_code'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN referral_code VARCHAR(20) UNIQUE AFTER documento");
        $pdo->exec("ALTER TABLE clientes ADD COLUMN bonus_acumulado DECIMAL(10,2) DEFAULT 0.00 AFTER referral_code");
        echo "Colunas de indicação adicionadas em 'clientes'.<br>";
    }

    // 3. Tabela de Indicações (Afiliados)
    $pdo->exec("CREATE TABLE IF NOT EXISTS indicacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referente_id INT NOT NULL,
        indicado_id INT NOT NULL,
        status ENUM('pendente', 'convertido', 'cancelado') DEFAULT 'pendente',
        bonus_pago DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (referente_id) REFERENCES clientes(id),
        FOREIGN KEY (indicado_id) REFERENCES clientes(id)
    )");
    echo "Tabela 'indicacoes' verificada/criada.<br>";

    // 4. Ajustes na Tabela Licenças
    $columns = $pdo->query("SHOW COLUMNS FROM licencas LIKE 'pedido_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE licencas ADD COLUMN pedido_id INT AFTER cliente_id");
        echo "Coluna 'pedido_id' adicionada em 'licencas'.<br>";
    }

    // 5. Tabela de Cupons (Reforço)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NOT NULL UNIQUE,
        valor DECIMAL(10,2) NOT NULL,
        tipo ENUM('porcentagem', 'fixo') DEFAULT 'porcentagem',
        limite_usos INT DEFAULT 0,
        usos_atuais INT DEFAULT 0,
        validade DATE DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabela 'cupons' verificada.<br>";

    // 6. Tabela Vendas (Pedidos)
    $columns = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'payment_id'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE vendas ADD COLUMN payment_id VARCHAR(100) AFTER valor_desconto");
        echo "Coluna 'payment_id' adicionada em 'vendas'.<br>";
    }
    
    $columns = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'valor_desconto'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE vendas ADD COLUMN valor_desconto DECIMAL(10,2) DEFAULT 0.00 AFTER valor");
        echo "Coluna 'valor_desconto' adicionada em 'vendas'.<br>";
    }

    // 7. Criar Admin Padrão (Se não existir)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $admin_email = 'admin@sgim.com';
        $admin_pass = password_hash('sgim2026', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES ('Administrador', ?, ?, 'admin')")
            ->execute([$admin_email, $admin_pass]);
        echo "Usuário administrador padrão criado (admin@sgim.com / sgim2026).<br>";
    }

    echo "<b>Processo concluído com sucesso!</b>";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
