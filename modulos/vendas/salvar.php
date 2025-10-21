<?php
include '../../core/init.php';
include '../../core/auth.php';

try {
    $conn->beginTransaction();

    $id = intval($_POST['id'] ?? 0);
    $cliente_id = intval($_POST['cliente_id'] ?? 0) ?: null; // cliente opcional
    $data_venda = $_POST['data_venda'] ?? date('Y-m-d');
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    $status = $_POST['status'] ?? 'pendente';
    $ajuste_tipo = $_POST['ajuste_tipo'] ?? 'desconto';
    $ajuste_valor = floatval($_POST['ajuste_valor'] ?? 0);
    $valor_total = 0.0;
    $produtos = $_POST['produtos'] ?? [];

    // ==========================
    // Validação básica
    // ==========================
    if (empty($produtos)) {
        throw new Exception("Adicione ao menos um produto.");
    }

    // ==========================
    // Buscar status anterior (se edição)
    // ==========================
    $status_antigo = null;
    if ($id > 0) {
        $stmtStatus = $conn->prepare("SELECT status FROM vendas WHERE id = ?");
        $stmtStatus->execute([$id]);
        $status_antigo = $stmtStatus->fetchColumn();
    }

    // ==========================
    // Restaurar estoque e apagar itens antigos (se não cancelada)
    // ==========================
    if ($id > 0 && $status_antigo !== 'cancelada') {
        $stmtOld = $conn->prepare("SELECT produto_id, quantidade FROM vendas_itens WHERE venda_id = ?");
        $stmtOld->execute([$id]);
        foreach ($stmtOld->fetchAll(PDO::FETCH_ASSOC) as $old) {
            $stmtRestore = $conn->prepare("
                UPDATE vendas_estoque 
                SET estoque_atual = COALESCE(estoque_atual,0) + ? 
                WHERE id = ?
            ");
            $stmtRestore->execute([$old['quantidade'], $old['produto_id']]);
        }
        $conn->prepare("DELETE FROM vendas_itens WHERE venda_id = ?")->execute([$id]);
    } elseif ($id > 0) {
        $conn->prepare("DELETE FROM vendas_itens WHERE venda_id = ?")->execute([$id]);
    }

    // ==========================
    // Inserir ou atualizar venda
    // ==========================
    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE vendas 
            SET cliente_id = ?, data_venda = ?, forma_pagamento = ?, status = ?,
                desconto = ?, acrescimo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $cliente_id, $data_venda, $forma_pagamento, $status,
            $ajuste_tipo === 'desconto' ? $ajuste_valor : 0,
            $ajuste_tipo === 'acrescimo' ? $ajuste_valor : 0,
            $id
        ]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO vendas 
            (cliente_id, data_venda, forma_pagamento, status, desconto, acrescimo, valor_total)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $cliente_id, $data_venda, $forma_pagamento, $status,
            $ajuste_tipo === 'desconto' ? $ajuste_valor : 0,
            $ajuste_tipo === 'acrescimo' ? $ajuste_valor : 0
        ]);
        $id = $conn->lastInsertId();
    }

    // ==========================
    // Inserir itens e atualizar estoque
    // ==========================
    $stmtItem = $conn->prepare("
        INSERT INTO vendas_itens (venda_id, produto_id, quantidade, valor_unitario)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($produtos as $item) {
        $produto_id = intval($item['id']);
        $quantidade = round(floatval($item['qtd']), 3); // precisão 3 casas para KG
        $valor = round(floatval($item['valor']), 2);

        if ($produto_id && $quantidade > 0 && $valor > 0) {
            $stmtItem->execute([$id, $produto_id, $quantidade, $valor]);
            $valor_total += $quantidade * $valor;

            // Debita estoque (somente se não cancelada)
            if ($status !== 'cancelada') {
                $stmtEstoque = $conn->prepare("
                    UPDATE vendas_estoque 
                    SET estoque_atual = GREATEST(COALESCE(estoque_atual,0) - ?, 0)
                    WHERE id = ?
                ");
                $stmtEstoque->execute([$quantidade, $produto_id]);
            }
        }
    }

    // ==========================
    // Aplica desconto ou acréscimo
    // ==========================
    $valor_final = $valor_total;
    if ($ajuste_tipo === 'desconto') $valor_final -= $ajuste_valor;
    if ($ajuste_tipo === 'acrescimo') $valor_final += $ajuste_valor;
    if ($valor_final < 0) $valor_final = 0;

    // ==========================
    // Atualiza total da venda
    // ==========================
    $stmtTotal = $conn->prepare("UPDATE vendas SET valor_total = ? WHERE id = ?");
    $stmtTotal->execute([$valor_final, $id]);

    // ==========================
    // Integração Financeira
    // ==========================
    $clienteNome = 'Cliente não identificado';
    if ($cliente_id) {
        $stmtCliente = $conn->prepare("SELECT nome FROM clientes WHERE id = ?");
        $stmtCliente->execute([$cliente_id]);
        $clienteNome = $stmtCliente->fetchColumn() ?: $clienteNome;
    }

    $descricao = "Venda #{$id} - {$clienteNome}";

    $stmtCheck = $conn->prepare("SELECT id FROM financeiro WHERE referencia_tipo = 'venda' AND referencia_id = ?");
    $stmtCheck->execute([$id]);
    $idFin = $stmtCheck->fetchColumn();

    if ($status === 'pago') {
        if ($idFin) {
            $stmtUpdate = $conn->prepare("
                UPDATE financeiro 
                SET valor = ?, forma_pagamento = ?, status = 'pago', atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$valor_final, $forma_pagamento, $idFin]);
        } else {
            $stmtInsert = $conn->prepare("
                INSERT INTO financeiro (
                    tipo, categoria, descricao, valor, data_lancamento, data_vencimento,
                    forma_pagamento, conta, status, referencia_tipo, referencia_id, criado_em
                ) VALUES (
                    'receita', 'Vendas', ?, ?, ?, ?, ?, 'Caixa Principal', 'pago', 'venda', ?, NOW()
                )
            ");
            $stmtInsert->execute([$descricao, $valor_final, $data_venda, $data_venda, $forma_pagamento, $id]);
        }
    } elseif (in_array($status, ['pendente', 'cancelada'])) {
        if ($idFin) {
            $conn->prepare("DELETE FROM financeiro WHERE id = ?")->execute([$idFin]);
        }
    }

    // ==========================
    // Finaliza transação
    // ==========================
    $conn->commit();

    header("Location: index.php?ok=1&msg=" . urlencode("Venda salva com sucesso!"));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: index.php?erro=1&msg=" . urlencode("Erro ao salvar venda: " . $e->getMessage()));
    exit;
}
?>