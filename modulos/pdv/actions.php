<?php
include '../../core/init.php';
include '../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

$DEBITAR_ESTOQUE = true;
$STATUS_PADRAO   = 'pago';

$input  = file_get_contents('php://input');
$json   = json_decode($input, true) ?? [];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'search_products':  search_products($conn, $json); break;
        case 'product_by_id':    product_by_id($conn, $json);   break;
        case 'finalize_sale':    finalize_sale($conn, $json, $DEBITAR_ESTOQUE, $STATUS_PADRAO); break;
        case 'ultimas_vendas':   ultimas_vendas($conn);         break;
        default:
            echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro interno: '.$e->getMessage()]);
}


// ================================
// FUNÇÕES
// ================================

// ---------- Buscar produtos ----------
function search_products(PDO $conn, array $json): void {
    $q = trim($json['q'] ?? '');
    $params = [];
    $sql = "
        SELECT 
            p.id,
            p.nome,
            p.codigo_ean,
            p.preco_custo,
            p.preco_venda,
            p.estoque_atual,
            p.estoque_minimo,
            p.imagem_url
        FROM vendas_estoque p
        WHERE p.ativo = 1
    ";
    if ($q !== '') {
        $sql .= " AND (p.nome LIKE ? OR p.codigo_ean LIKE ? OR p.id LIKE ?)";
        $params = ["%{$q}%", "%{$q}%", "%{$q}%"];
    }
    $sql .= " ORDER BY p.nome ASC LIMIT 80";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $data]);
}


// ---------- Buscar produto por ID ----------
function product_by_id(PDO $conn, array $json): void {
    $id = (int)($json['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
        return;
    }
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.nome,
            p.preco_venda AS preco,
            p.preco_custo,
            p.estoque_atual,
            p.estoque_minimo,
            p.imagem_url
        FROM vendas_estoque p
        WHERE p.id = ? AND p.ativo = 1
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row
        ? json_encode(['ok' => true, 'data' => $row])
        : json_encode(['ok' => false, 'msg' => 'Produto não encontrado']);
}


// ---------- Finalizar venda ----------
function finalize_sale(PDO $conn, array $json, bool $debitar, string $status): void {

    $clienteNome = trim($json['cliente'] ?? '') ?: null;
    $forma       = trim($json['forma_pagamento'] ?? 'Dinheiro');
    $obs         = trim($json['observacoes'] ?? '');
    $itens       = $json['itens'] ?? [];

    // Ajuste unificado (desconto ou acréscimo)
    $ajuste_valor = (float)($json['ajuste_valor'] ?? 0);
    $ajuste_tipo  = trim($json['ajuste_tipo'] ?? 'desconto');

    if (empty($itens)) {
        echo json_encode(['ok' => false, 'msg' => 'Carrinho vazio.']);
        return;
    }

    // Localiza ou cria cliente
    $clienteId = null;
    if ($clienteNome) {
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE nome = ? LIMIT 1");
        $stmt->execute([$clienteNome]);
        $cid = $stmt->fetchColumn();
        if ($cid) $clienteId = (int)$cid;
        else {
            $stmt = $conn->prepare("INSERT INTO clientes (nome, criado_em) VALUES (?, NOW())");
            $stmt->execute([$clienteNome]);
            $clienteId = (int)$conn->lastInsertId();
        }
    }

    // Calcula total
    $total = 0;
    foreach ($itens as $i) {
        $q = (float)($i['qtd'] ?? 0);
        $p = (float)($i['preco_unit'] ?? 0);
        $total += $q * $p;
    }
    if ($total <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Valor total inválido.']);
        return;
    }

    // Aplica ajuste
    $total_final = $ajuste_tipo === 'acrescimo'
        ? $total + $ajuste_valor
        : max(0, $total - $ajuste_valor);

    // Transação
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
            INSERT INTO vendas 
            (cliente_id, data_venda, valor_total, desconto, acrescimo, forma_pagamento, status, criado_em, observacoes)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $clienteId,
            $total_final,
            $ajuste_tipo === 'desconto' ? $ajuste_valor : 0,
            $ajuste_tipo === 'acrescimo' ? $ajuste_valor : 0,
            $forma,
            $status,
            $obs
        ]);
        $vendaId = (int)$conn->lastInsertId();

        // Itens
        $stmtItem = $conn->prepare("
            INSERT INTO vendas_itens (venda_id, produto_id, quantidade, valor_unitario)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($itens as $i) {
            $pid = (int)$i['produto_id'];
            $q   = (float)$i['qtd'];
            $p   = (float)$i['preco_unit'];
            if ($pid <= 0 || $q <= 0) continue;
            $stmtItem->execute([$vendaId, $pid, $q, $p]);

            if ($debitar) {
                $stmt2 = $conn->prepare("
                    UPDATE vendas_estoque 
                    SET estoque_atual = GREATEST(0, COALESCE(estoque_atual,0) - ?) 
                    WHERE id = ?
                ");
                $stmt2->execute([$q, $pid]);
            }
        }

        $conn->commit();
        echo json_encode(['ok' => true, 'sale_id' => $vendaId]);

    } catch (Throwable $e) {
        $conn->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
}


// ---------- Histórico de últimas vendas ----------
function ultimas_vendas(PDO $conn): void {
    $stmt = $conn->query("
        SELECT 
            v.id,
            COALESCE(c.nome, 'Sem cliente') AS cliente,
            DATE_FORMAT(v.data_venda, '%d/%m/%Y %H:%i') AS data,
            v.valor_total AS total
        FROM vendas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        ORDER BY v.id DESC
        LIMIT 10
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $data]);
}