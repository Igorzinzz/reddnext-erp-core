<?php
include '../../../core/init.php';
include '../../../core/auth.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("UPDATE vendas_estoque SET ativo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?ok=1&msg=" . urlencode("Produto desativado com sucesso."));
    exit;
}
header("Location: index.php?erro=1&msg=" . urlencode("Produto não encontrado."));
exit;
?>