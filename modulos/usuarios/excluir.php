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

    // Impede desativar ou excluir a si mesmo
    if ($id == $_SESSION['usuario']['id']) {
        throw new Exception('Você não pode desativar ou excluir a si mesmo.');
    }

    // Verifica se é o último usuário ativo
    $totalAtivos = $conn->query("SELECT COUNT(*) FROM usuarios WHERE ativo=1")->fetchColumn();
    if ($totalAtivos <= 1) {
        throw new Exception('Não é possível desativar o último usuário ativo do sistema.');
    }

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
?>