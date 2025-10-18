<?php
include '../../core/init.php';
include '../../core/auth.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: index.php?erro=1&msg=" . urlencode("Venda inválida."));
    exit;
}

try {
    $conn->beginTransaction();

    // ==========================
    // Verifica status da venda
    // ==========================
    $stmtVenda = $conn->prepare("SELECT status FROM vendas WHERE id = ?");
    $stmtVenda->execute([$id]);
    $status = $stmtVenda->fetchColumn();

    if (!$status) {
        throw new Exception("Venda não encontrada.");
    }

    if ($status !== 'cancelada') {
        throw new Exception("Somente vendas canceladas podem ser excluídas.");
    }

    // ==========================
    // Restaurar estoque dos itens
    // ==========================
    $stmtItens = $conn->prepare("SELECT produto_id, quantidade FROM vendas_itens WHERE venda_id = ?");
    $stmtItens->execute([$id]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itens as $item) {
        $stmtEstoque = $conn->prepare("
            UPDATE vendas_estoque 
            SET estoque_atual = estoque_atual + ?
            WHERE id = ?
        ");
        $stmtEstoque->execute([$item['quantidade'], $item['produto_id']]);
    }

    // ==========================
    // Excluir lançamentos financeiros relacionados
    // ==========================
    $stmtFinanceiro = $conn->prepare("DELETE FROM financeiro WHERE referencia_tipo = 'venda' AND referencia_id = ?");
    $stmtFinanceiro->execute([$id]);

    // ==========================
    // Excluir itens e a venda
    // ==========================
    $conn->prepare("DELETE FROM vendas_itens WHERE venda_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM vendas WHERE id = ?")->execute([$id]);

    $conn->commit();

    header("Location: index.php?ok=1&msg=" . urlencode("Venda cancelada excluída com sucesso!"));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: index.php?erro=1&msg=" . urlencode("Erro ao excluir venda: " . $e->getMessage()));
    exit;
}
?>