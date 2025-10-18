<?php
include '../../../core/init.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("UPDATE financeiro_categorias SET ativo = 0, atualizado_em = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}
header("Location: index.php?ok=1&msg=Categoria desativada com sucesso!");
exit;