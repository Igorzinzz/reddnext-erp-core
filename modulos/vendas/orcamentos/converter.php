<?php
include '../../../core/init.php';
include '../../../core/auth.php';

// ==========================
// ID do orçamento
// ==========================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Orçamento inválido.");
}

// ==========================
// Busca o orçamento
// ==========================
$stmt = $conn->prepare("SELECT * FROM vendas_orcamentos WHERE id = ?");
$stmt->execute([$id]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) {
    die("Orçamento não encontrado.");
}

// ==========================
// Verifica cliente vinculado
// ==========================
if (empty($orc['cliente_id'])) {
    die("⚠️ Este orçamento não possui um cliente vinculado. Cadastre ou selecione um cliente antes de converter.");
}

// ==========================
// Busca os itens do orçamento
// ==========================
$stmtItens = $conn->prepare("SELECT * FROM vendas_orcamentos_itens WHERE orcamento_id = ?");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

if (!$itens) {
    die("Nenhum item encontrado neste orçamento.");
}

// ==========================
// Inicia transação
// ==========================
$conn->beginTransaction();

try {
    // ==========================
    // Recalcula total BRUTO a partir dos itens
    // ==========================
    $totalBruto = 0.0;
    foreach ($itens as $it) {
        $q = (float)($it['quantidade'] ?? 0);
        $v = (float)($it['preco_unitario'] ?? 0);
        $totalBruto += $q * $v;
    }

    // Tipo e valor de desconto do orçamento
    $tipoDesconto   = $orc['tipo_desconto'] ?? '%';   // '%' ou 'R$'
    $valorDesconto  = (float)($orc['desconto'] ?? 0); // pode ser % ou R$

    // Converte para R$ corretamente
    if ($tipoDesconto === 'R$') {
        $descontoReais = $valorDesconto;
    } else {
        // desconto em % aplicado sobre o BRUTO
        $descontoReais = $totalBruto * ($valorDesconto / 100);
    }

    // Valor final líquido da venda
    $valorLiquido = max($totalBruto - $descontoReais, 0);

    // ==========================
    // Cria a venda vinculada ao cliente
    // ==========================
    $stmtVenda = $conn->prepare("
        INSERT INTO vendas (
            cliente_id, data_venda, valor_total, desconto, tipo_desconto,
            forma_pagamento, status, observacoes
        ) VALUES (?, CURDATE(), ?, ?, ?, NULL, 'pendente', ?)
    ");
    $stmtVenda->execute([
        $orc['cliente_id'],
        $valorLiquido,       // agora salva o total LÍQUIDO (bruto - desconto)
        $descontoReais,      // valor absoluto do desconto
        $tipoDesconto,       // '%' ou 'R$'
        $orc['observacoes'] ?? ''
    ]);
    $vendaId = $conn->lastInsertId();

    // ==========================
    // Insere itens e atualiza estoque
    // ==========================
    foreach ($itens as $item) {
        $tipo       = $item['tipo'] ?? 'produto';
        $produtoId  = $item['produto_id'] ?? null;
        $qtd        = (float)$item['quantidade'];
        $valor      = (float)$item['preco_unitario'];

        if ($tipo === 'servico') {
            // Serviço — não afeta estoque
            $conn->prepare("
                INSERT INTO vendas_itens (venda_id, produto_id, quantidade, valor_unitario)
                VALUES (?, 0, 1, ?)
            ")->execute([$vendaId, $valor]);
        } else {
            // Produto — insere item e dá baixa no estoque
            $conn->prepare("
                INSERT INTO vendas_itens (venda_id, produto_id, quantidade, valor_unitario)
                VALUES (?, ?, ?, ?)
            ")->execute([$vendaId, $produtoId, $qtd, $valor]);

            $conn->prepare("
                UPDATE vendas_estoque
                   SET estoque_atual = GREATEST(COALESCE(estoque_atual,0) - ?, 0)
                 WHERE id = ?
            ")->execute([$qtd, $produtoId]);
        }
    }

    // ==========================
    // Atualiza status do orçamento
    // ==========================
    $conn->prepare("UPDATE vendas_orcamentos SET status = 'convertido' WHERE id = ?")->execute([$id]);

    $conn->commit();

    // Redireciona para a edição da venda criada
    header("Location: ../../vendas/editar.php?id=" . $vendaId);
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    echo "Erro ao converter orçamento: " . $e->getMessage();
}