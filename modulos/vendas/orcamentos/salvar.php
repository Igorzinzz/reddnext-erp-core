<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.');
    }

    $id = intval($_POST['id'] ?? 0);
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $cliente_nome = trim($_POST['cliente_nome'] ?? '');
    $validade = !empty($_POST['validade']) ? $_POST['validade'] : null;
    $desconto = floatval($_POST['desconto'] ?? 0);
    $tipo_desconto = $_POST['tipo_desconto'] ?? '%';
    $observacoes = trim($_POST['observacoes'] ?? '');

    $produtos = $_POST['produtos'] ?? [];

    // ============================
    // Validações
    // ============================
    if ($cliente_id <= 0) {
        throw new Exception("Selecione um cliente válido.");
    }
    if (empty($produtos)) {
        throw new Exception("Adicione pelo menos um produto ao orçamento.");
    }

    // ============================
    // Cálculo de total
    // ============================
    $total = 0;
    foreach ($produtos as $p) {
        $qtd = floatval($p['qtd'] ?? 0);
        $valor = floatval($p['valor'] ?? 0);
        if ($qtd <= 0 || $valor <= 0) {
            throw new Exception("Quantidade e valor devem ser maiores que zero.");
        }
        $total += $qtd * $valor;
    }

    // Aplica desconto conforme tipo
    if ($desconto > 0) {
        if ($tipo_desconto === '%') {
            $total -= ($total * ($desconto / 100));
        } else {
            $total -= $desconto;
        }
    }

    if ($total < 0) $total = 0;

    // ============================
    // Inserção ou atualização
    // ============================
    if ($id > 0) {
        // Atualiza orçamento existente
        $stmt = $conn->prepare("
            UPDATE vendas_orcamentos 
               SET cliente_id = ?, cliente_nome = ?, validade = ?, 
                   desconto = ?, tipo_desconto = ?, total = ?, observacoes = ?, atualizado_em = NOW()
             WHERE id = ?
        ");
        $stmt->execute([
            $cliente_id, $cliente_nome, $validade,
            $desconto, $tipo_desconto, $total, $observacoes, $id
        ]);

        // Remove itens antigos
        $conn->prepare("DELETE FROM vendas_orcamentos_itens WHERE orcamento_id = ?")->execute([$id]);
    } else {
        // Novo orçamento
        $stmt = $conn->prepare("
            INSERT INTO vendas_orcamentos 
                (cliente_id, cliente_nome, validade, desconto, tipo_desconto, total, observacoes, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $cliente_id, $cliente_nome, $validade,
            $desconto, $tipo_desconto, $total, $observacoes
        ]);
        $id = $conn->lastInsertId();
    }

    // ============================
    // Itens do orçamento
    // ============================
    $stmtItem = $conn->prepare("
        INSERT INTO vendas_orcamentos_itens 
            (orcamento_id, produto_id, quantidade, preco_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($produtos as $p) {
        $produto_id = intval($p['id']);
        $qtd = floatval($p['qtd']);
        $preco = floatval($p['valor']);
        $subtotal = $qtd * $preco;

        $stmtItem->execute([$id, $produto_id, $qtd, $preco, $subtotal]);
    }

    // ============================
    // Redireciona com sucesso
    // ============================
    header("Location: index.php?ok=1&msg=" . urlencode("Orçamento salvo com sucesso!"));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>