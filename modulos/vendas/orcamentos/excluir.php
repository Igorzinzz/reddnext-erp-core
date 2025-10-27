<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    // ==========================
    // Validação de requisição
    // ==========================
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método inválido.');
    }

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('ID inválido para exclusão.');
    }

    // ==========================
    // Verifica se o orçamento existe
    // ==========================
    $stmt = $conn->prepare("SELECT id FROM vendas_orcamentos WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('Orçamento não encontrado.');
    }

    // ==========================
    // Exclui o orçamento e itens relacionados
    // ==========================
    $conn->beginTransaction();

    $conn->prepare("DELETE FROM vendas_orcamentos_itens WHERE orcamento_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM vendas_orcamentos WHERE id = ?")->execute([$id]);

    $conn->commit();

    // ==========================
    // Retorno
    // ==========================
    header("Location: index.php?ok=1&msg=" . urlencode("Orçamento excluído com sucesso!"));
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>