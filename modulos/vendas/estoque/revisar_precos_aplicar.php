<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    // ==========================
    // Validação inicial
    // ==========================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
        throw new Exception("Nenhum produto selecionado para atualização.");
    }

    $ids = array_map('intval', $_POST['ids']);
    $in = implode(',', array_fill(0, count($ids), '?'));

    // ==========================
    // Atualiza preços e recalcula margens
    // ==========================
    $sql = "
        UPDATE vendas_estoque
        SET 
            preco_venda = preco_sugerido,
            margem_padrao = CASE 
                WHEN preco_custo > 0 THEN ROUND(((preco_sugerido / preco_custo) - 1) * 100, 2)
                ELSE margem_padrao 
            END,
            preco_sugerido = NULL
        WHERE id IN ($in)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);

    // ==========================
    // Verificação pós-execução
    // ==========================
    if ($stmt->rowCount() === 0) {
        throw new Exception("Nenhum preço foi atualizado. Verifique se os produtos ainda possuem preço sugerido.");
    }

    header("Location: revisar_precos.php?ok=1&msg=" . urlencode("Preços aplicados com sucesso!"));
    exit;

} catch (Throwable $e) {
    header("Location: revisar_precos.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>