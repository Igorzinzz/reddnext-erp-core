<?php
include '../../../core/init.php';

$id = intval($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE financeiro_categorias SET nome=?, descricao=?, atualizado_em=NOW() WHERE id=?");
    $ok = $stmt->execute([$nome, $descricao, $id]);
} else {
    $stmt = $conn->prepare("INSERT INTO financeiro_categorias (nome, descricao) VALUES (?, ?)");
    $ok = $stmt->execute([$nome, $descricao]);
}

header("Location: index.php?" . ($ok ? "ok=1&msg=Categoria salva com sucesso!" : "erro=1&msg=Erro ao salvar categoria."));
exit;