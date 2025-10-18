<?php
include '../../../core/init.php';
include '../../../core/auth.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE clientes SET ativo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: index.php?ok=1&msg=' . urlencode('Cliente desativado com sucesso!'));
} else {
    header('Location: index.php?erro=1&msg=' . urlencode('Cliente inválido.'));
}
exit;
?>