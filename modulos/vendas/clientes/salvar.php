<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $uf = trim($_POST['uf'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($nome == '') throw new Exception('O nome do cliente é obrigatório.');

    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE clientes 
            SET nome=?, documento=?, telefone=?, email=?, endereco=?, cidade=?, uf=?, ativo=? 
            WHERE id=?
        ");
        $stmt->execute([$nome, $documento, $telefone, $email, $endereco, $cidade, $uf, $ativo, $id]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO clientes (nome, documento, telefone, email, endereco, cidade, uf, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $documento, $telefone, $email, $endereco, $cidade, $uf, $ativo]);
    }

    header('Location: index.php?ok=1&msg=' . urlencode('Cliente salvo com sucesso!'));
    exit;
} catch (Exception $e) {
    header('Location: index.php?erro=1&msg=' . urlencode($e->getMessage()));
    exit;
}
?>