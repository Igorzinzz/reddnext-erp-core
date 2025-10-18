<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $dados = [
        $_POST['nome_exibicao'],
        $_POST['banco'],
        $_POST['agencia'],
        $_POST['numero_conta'],
        $_POST['tipo'],
        $_POST['saldo_inicial'],
        $_POST['ativo']
    ];

    if ($id > 0) {
        $sql = "UPDATE financeiro_contas 
                SET nome_exibicao=?, banco=?, agencia=?, numero_conta=?, tipo=?, saldo_inicial=?, ativo=?, atualizado_em=NOW()
                WHERE id=?";
        $dados[] = $id;
    } else {
        $sql = "INSERT INTO financeiro_contas 
                (nome_exibicao, banco, agencia, numero_conta, tipo, saldo_inicial, ativo, criado_em)
                VALUES (?,?,?,?,?,?,?,NOW())";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($dados);

    header("Location: index.php?ok=1&msg=Conta+salva+com+sucesso");
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}