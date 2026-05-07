<?php
/**
 * ULTRA FIX V3 - Reparo Estrutural do Banco de Dados
 * SGIM-VENDAS (Executando na Raiz)
 */
require_once 'config/db.php';

echo "<h1>🛠️ Iniciando Reparo Estrutural (V3)</h1>";

try {
    // 1. Tabela CLIENTES: Adicionar usuario_id se não existir
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'usuario_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN usuario_id INT AFTER id");
        echo "✅ Coluna 'usuario_id' adicionada em 'clientes'.<br>";
    }

    // 2. Tabela LICENCAS: Adicionar pedido_id se não existir
    $stmt = $pdo->query("SHOW COLUMNS FROM licencas LIKE 'pedido_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE licencas ADD COLUMN pedido_id INT AFTER cliente_id");
        echo "✅ Coluna 'pedido_id' adicionada em 'licencas'.<br>";
    }

    // 3. Tabela VENDAS: Garantir existência e colunas
    $hasVendas = $pdo->query("SHOW TABLES LIKE 'vendas'")->fetch();
    if (!$hasVendas) {
        $pdo->exec("CREATE TABLE vendas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT,
            cliente_id INT,
            cliente_nome VARCHAR(255),
            valor DECIMAL(10,2),
            status VARCHAR(50) DEFAULT 'PENDENTE',
            payment_id VARCHAR(100),
            data_venda DATETIME
        )");
        echo "✅ Tabela 'vendas' criada.<br>";
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'pedido_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE vendas ADD COLUMN pedido_id INT FIRST");
            echo "✅ Coluna 'pedido_id' adicionada em 'vendas'.<br>";
        }
    }

    // 4. Tabela PEDIDOS: Garantir status e payment_id
    $stmt = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'payment_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE pedidos ADD COLUMN payment_id VARCHAR(100)");
        echo "✅ Coluna 'payment_id' adicionada em 'pedidos'.<br>";
    }

    // 5. Vincular Clientes Órfãos (pelo e-mail)
    $stmt = $pdo->query("SELECT id, email FROM usuarios WHERE nivel = 'cliente'");
    $usuarios = $stmt->fetchAll();
    foreach ($usuarios as $u) {
        $stmt = $pdo->prepare("UPDATE clientes SET usuario_id = ? WHERE email = ? AND (usuario_id IS NULL OR usuario_id = 0)");
        $stmt->execute([$u['id'], $u['email']]);
    }
    echo "✅ Sincronização de Vínculos Usuário/Cliente concluída.<br>";

    // 6. Tabela PAGAMENTOS: Garantir pedido_id
    $hasPag = $pdo->query("SHOW TABLES LIKE 'pagamentos'")->fetch();
    if ($hasPag) {
        $stmt = $pdo->query("SHOW COLUMNS FROM pagamentos LIKE 'pedido_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE pagamentos ADD COLUMN pedido_id INT AFTER id");
            echo "✅ Coluna 'pedido_id' adicionada em 'pagamentos'.<br>";
        }
    }

    // 7. Tabela ACTIVATION_REQUESTS: Garantir existência
    $hasActivation = $pdo->query("SHOW TABLES LIKE 'activation_requests'")->fetch();
    if (!$hasActivation) {
        $pdo->exec("CREATE TABLE activation_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            telefone VARCHAR(20),
            license_key VARCHAR(64) NOT NULL,
            domain VARCHAR(255) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✅ Tabela 'activation_requests' criada.<br>";
    }

    echo "<h2>🎉 Reparo Concluído com Sucesso!</h2>";
    echo "<p>Agora você pode testar o checkout e o dashboard novamente.</p>";
    echo "<a href='index.php'>Voltar ao Início</a> | <a href='admin.php'>Ir para Dashboard Admin</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ Erro: " . $e->getMessage() . "</h3>";
}
?>
