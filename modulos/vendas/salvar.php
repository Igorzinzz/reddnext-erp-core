<?php
include '../../core/init.php';
include '../../core/auth.php';

try {
    $conn->beginTransaction();

    $id = intval($_POST['id'] ?? 0);
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $data_venda = $_POST['data_venda'] ?? date('Y-m-d');
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    $status = $_POST['status'] ?? 'pendente';
    $valor_total = 0.0;
    $produtos = $_POST['produtos'] ?? [];

    // ==========================
    // Validação básica
    // ==========================
    if ($cliente_id <= 0) {
        throw new Exception("Cliente é obrigatório.");
    }
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
    // Restaurar estoque e apagar itens antigos (somente se NÃO era cancelada)
    // ==========================
    if ($id > 0 && $status_antigo !== 'cancelada') {
        $stmtOld = $conn->prepare("SELECT produto_id, quantidade FROM vendas_itens WHERE venda_id = ?");
        $stmtOld->execute([$id]);
        foreach ($stmtOld->fetchAll(PDO::FETCH_ASSOC) as $old) {
            $stmtRestore = $conn->prepare("
                UPDATE vendas_estoque 
                SET estoque_atual = estoque_atual + ? 
                WHERE id = ?
            ");
            $stmtRestore->execute([$old['quantidade'], $old['produto_id']]);
        }
        $conn->prepare("DELETE FROM vendas_itens WHERE venda_id = ?")->execute([$id]);
    } else if ($id > 0) {
        // apenas limpa os itens se era cancelada (sem alterar estoque)
        $conn->prepare("DELETE FROM vendas_itens WHERE venda_id = ?")->execute([$id]);
    }

    // ==========================
    // Criar ou atualizar venda
    // ==========================
    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE vendas 
            SET cliente_id = ?, data_venda = ?, forma_pagamento = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$cliente_id, $data_venda, $forma_pagamento, $status, $id]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO vendas (cliente_id, data_venda, forma_pagamento, status, valor_total)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$cliente_id, $data_venda, $forma_pagamento, $status]);
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
        $quantidade = floatval($item['qtd']);
        $valor = floatval($item['valor']);

        if ($produto_id && $quantidade > 0 && $valor > 0) {
            $stmtItem->execute([$id, $produto_id, $quantidade, $valor]);
            $valor_total += $quantidade * $valor;

            // Debita estoque SOMENTE se a venda não for cancelada
            if ($status !== 'cancelada') {
                $stmtEstoque = $conn->prepare("
                    UPDATE vendas_estoque 
                    SET estoque_atual = GREATEST(estoque_atual - ?, 0)
                    WHERE id = ?
                ");
                $stmtEstoque->execute([$quantidade, $produto_id]);
            }
        }
    }

    // Atualiza o total da venda
    $stmtTotal = $conn->prepare("UPDATE vendas SET valor_total = ? WHERE id = ?");
    $stmtTotal->execute([$valor_total, $id]);

    // ==========================
    // Integração com módulo Financeiro
    // ==========================
    $stmtCliente = $conn->prepare("SELECT nome FROM clientes WHERE id = ?");
    $stmtCliente->execute([$cliente_id]);
    $clienteNome = $stmtCliente->fetchColumn() ?: 'Cliente não identificado';
    $descricao = "Venda #{$id} - {$clienteNome}";

    // Verifica se já existe lançamento financeiro vinculado
    $stmtCheck = $conn->prepare("SELECT id FROM financeiro WHERE referencia_tipo = 'venda' AND referencia_id = ?");
    $stmtCheck->execute([$id]);
    $idFin = $stmtCheck->fetchColumn();

    // Criar ou atualizar financeiro conforme status
    if ($status === 'pago') {
        if ($idFin) {
            $stmtUpdate = $conn->prepare("
                UPDATE financeiro 
                SET valor = ?, forma_pagamento = ?, status = 'pago', atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$valor_total, $forma_pagamento, $idFin]);
        } else {
            $stmtInsert = $conn->prepare("
                INSERT INTO financeiro (
                    tipo, categoria, descricao, valor, data_lancamento, data_vencimento,
                    forma_pagamento, conta, status, referencia_tipo, referencia_id, criado_em
                ) VALUES (
                    'receita', 'Vendas', ?, ?, ?, ?, ?, 'Caixa Principal', 'pago', 'venda', ?, NOW()
                )
            ");
            $stmtInsert->execute([$descricao, $valor_total, $data_venda, $data_venda, $forma_pagamento, $id]);
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