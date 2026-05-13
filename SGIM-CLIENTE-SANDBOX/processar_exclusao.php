<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if ($type && $id) {
    try {
    require_once 'src/bootstrap.php';
    $auditModel = new \App\Models\AuditModel($pdo);

    if ($type === 'departamento') {
        $stmt = $pdo->prepare("UPDATE departamentos SET deleted_at = NOW() WHERE id = ?");
        if ($stmt->execute([$id])) {
            $auditModel->log('departamentos', $id, 'exclusao_logica');
        }
    } elseif ($type === 'cargo') {
        $stmt = $pdo->prepare("UPDATE cargos SET deleted_at = NOW() WHERE id = ?");
        if ($stmt->execute([$id])) {
            $auditModel->log('cargos', $id, 'exclusao_logica');
        }
    } elseif ($type === 'transacao') {
        $stmt = $pdo->prepare("UPDATE financeiro_transacoes SET deleted_at = NOW() WHERE id = ?");
        if ($stmt->execute([$id])) {
            $auditModel->log('financeiro_transacoes', $id, 'exclusao_logica');
        }
    } elseif ($type === 'congregacao') {
        $stmt = $pdo->prepare("UPDATE congregacoes SET deleted_at = NOW() WHERE id = ?");
        if ($stmt->execute([$id])) {
            $auditModel->log('congregacoes', $id, 'exclusao_logica');
        }
    }
    $redirect = ($type === 'congregacao') ? 'congregacoes.php' : 'departamentos.php';
    header('Location: ' . $redirect . '?sucesso=1');
    } catch (Throwable $e) {
        $redirect = ($type === 'congregacao') ? 'congregacoes.php' : 'departamentos.php';
        if (!headers_sent()) {
            header('Location: ' . $redirect . '?erro=1&msg=' . urlencode("Erro ao excluir. O item pode estar em uso por outros cadastros. Detalhe: " . $e->getMessage()));
        } else {
            echo "Erro fatal ao excluir: " . $e->getMessage();
        }
    }
} else {
    header('Location: dashboard.php');
}
exit;
