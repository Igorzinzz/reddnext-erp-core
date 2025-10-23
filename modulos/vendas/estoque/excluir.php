<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("Produto inválido.");
    }

    // Verifica se o produto existe
    $stmt = $conn->prepare("SELECT nome FROM vendas_estoque WHERE id = ?");
    $stmt->execute([$id]);
    $produtoNome = $stmt->fetchColumn();
    if (!$produtoNome) {
        throw new Exception("Produto não encontrado.");
    }

    // Verifica se o produto possui vínculos com vendas
    $stmtVenda = $conn->prepare("SELECT COUNT(*) FROM vendas_itens WHERE produto_id = ?");
    $stmtVenda->execute([$id]);
    $usadoEmVendas = $stmtVenda->fetchColumn();

    // Verifica se o produto possui vínculos com notas fiscais (entradas)
    $stmtEntrada = $conn->prepare("SELECT COUNT(*) FROM vendas_entradas_itens WHERE produto_id = ?");
    $stmtEntrada->execute([$id]);
    $usadoEmEntradas = $stmtEntrada->fetchColumn();

    if ($usadoEmVendas > 0 || $usadoEmEntradas > 0) {
        // Produto com vínculos — apenas desativa
        $conn->prepare("UPDATE vendas_estoque SET ativo = 0 WHERE id = ?")->execute([$id]);
        $msg = "O produto '{$produtoNome}' possui vínculos no sistema e foi apenas desativado.";
        header("Location: index.php?ok=1&msg=" . urlencode($msg));
        exit;
    }

    // Produto sem vínculos — exclusão definitiva
    $conn->prepare("DELETE FROM vendas_estoque WHERE id = ?")->execute([$id]);
    $msg = "Produto '{$produtoNome}' excluído definitivamente.";
    header("Location: index.php?ok=1&msg=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>