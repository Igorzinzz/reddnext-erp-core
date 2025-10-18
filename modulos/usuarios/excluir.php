<?php
include '../../core/init.php';
include '../../core/auth.php';

try {
    if ($_SESSION['usuario']['nivel'] !== 'admin') {
        throw new Exception('Acesso negado.');
    }

    $id = intval($_GET['id'] ?? 0);
    $acao = $_GET['acao'] ?? 'desativar'; // padrão: desativar

    if ($id <= 0) throw new Exception('ID inválido.');

    if ($acao === 'excluir') {
        // Exclusão definitiva
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Usuário excluído permanentemente.";
    } else {
        // Desativar usuário (padrão)
        $stmt = $conn->prepare("UPDATE usuarios SET ativo=0 WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Usuário desativado com sucesso.";
    }

    header("Location: index.php?ok=1&msg=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}