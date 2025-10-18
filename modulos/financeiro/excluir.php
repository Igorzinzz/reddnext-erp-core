<?php
include '../../core/init.php';
include '../../core/auth.php';

try {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido.");

    $stmt = $conn->prepare("DELETE FROM financeiro WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: index.php?ok=1&msg=Lançamento+excluído+com+sucesso");
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}