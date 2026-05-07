<?php
/**
 * Script para Correção de Constraints - SGIM-VENDAS
 * Altera as chaves estrangeiras para permitir exclusão em cascata.
 */
require_once 'config/database.php';

try {
    echo "Iniciando ajuste de constraints...<br>";

    // --- TABELA PEDIDOS ---
    // Remover FK antiga de cliente_id e adicionar com CASCADE
    try {
        // Tentar identificar o nome da constraint (pedidos_ibfk_1 é comum)
        $pdo->exec("ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS pedidos_ibfk_1");
        $pdo->exec("ALTER TABLE pedidos ADD CONSTRAINT pedidos_ibfk_1 FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE");
        echo "OK: Constraint 'pedidos_ibfk_1' em 'pedidos' atualizada para CASCADE.<br>";
    } catch (Exception $e) {
        echo "Aviso: Não foi possível atualizar 'pedidos_ibfk_1'. Tentando nomes alternativos...<br>";
        // Alternativa se for outra constraint
        try {
            $pdo->exec("ALTER TABLE pedidos ADD FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE");
        } catch (Exception $ex) { echo "Erro ao ajustar pedidos: " . $ex->getMessage() . "<br>"; }
    }

    // --- TABELA PAGAMENTOS ---
    try {
        $pdo->exec("ALTER TABLE pagamentos DROP FOREIGN KEY IF EXISTS pagamentos_ibfk_1");
        $pdo->exec("ALTER TABLE pagamentos ADD CONSTRAINT pagamentos_ibfk_1 FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE");
        echo "OK: Constraint 'pagamentos_ibfk_1' em 'pagamentos' atualizada para CASCADE.<br>";
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE pagamentos ADD FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE");
        } catch (Exception $ex) { echo "Erro ao ajustar pagamentos: " . $ex->getMessage() . "<br>"; }
    }

    // --- TABELA LICENCAS ---
    try {
        $pdo->exec("ALTER TABLE licencas ADD FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE");
        $pdo->exec("ALTER TABLE licencas ADD FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE");
        echo "OK: Adicionado CASCADE em 'licencas'.<br>";
    } catch (Exception $e) {
        echo "Aviso: 'licencas' já possuía constraints ou erro ao adicionar: " . $e->getMessage() . "<br>";
    }

    echo "<b>Ajustes de Banco de Dados concluídos!</b> Tente excluir o cliente novamente.";

} catch (Exception $e) {
    echo "Erro Fatal: " . $e->getMessage();
}
