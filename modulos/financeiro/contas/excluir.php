<?php
include '../../../core/init.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $conn->prepare("UPDATE financeiro_contas SET ativo = 0, atualizado_em = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?ok=1&msg=Conta desativada com sucesso!");
    } catch (Exception $e) {
        header("Location: index.php?erro=1&msg=Erro ao desativar conta.");
    }
} else {
    header("Location: index.php?erro=1&msg=ID inválido.");
}
exit;