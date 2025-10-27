<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['xml']['tmp_name'])) {
        throw new Exception("Nenhum arquivo XML enviado.");
    }

    $fileTmp = $_FILES['xml']['tmp_name'];
    $fileName = basename($_FILES['xml']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'xml') throw new Exception("Envie um arquivo .xml válido.");

    $xml = @simplexml_load_file($fileTmp);
    if (!$xml) throw new Exception("Erro ao ler o XML.");

    $infNFe = $xml->NFe->infNFe ?? $xml->infNFe ?? null;
    if (!$infNFe) throw new Exception("Formato de NF-e não reconhecido.");

    // ==========================
    // Configuração global de margem
    // ==========================
    $config = $conn->query("SELECT margem_padrao FROM config_sistema LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $margem_global = floatval(str_replace(',', '.', $config['margem_padrao'] ?? '30'));

    // ==========================
    // Dados principais da NF
    // ==========================
    $numero_nfe = (string)($infNFe->ide->nNF ?? '');
    $chave_nfe  = (string)($infNFe['Id'] ?? '');
    $fornecedor_nome = (string)($infNFe->emit->xNome ?? '');
    $cnpj_fornecedor = (string)($infNFe->emit->CNPJ ?? '');
    $data_emissao = (string)($infNFe->ide->dhEmi ?? date('Y-m-d'));
    $valor_total = (float)($infNFe->total->ICMSTot->vNF ?? 0);
    if ($data_emissao) $data_emissao = date('Y-m-d', strtotime($data_emissao));

    // Evita duplicação
    $check = $conn->prepare("SELECT id FROM vendas_entradas WHERE chave_nfe = ? LIMIT 1");
    $check->execute([$chave_nfe]);
    if ($check->fetchColumn()) throw new Exception("Esta NF já foi importada anteriormente.");

    // Cria registro da entrada
    $stmt = $conn->prepare("
        INSERT INTO vendas_entradas 
        (numero_nfe, chave_nfe, fornecedor_nome, cnpj_fornecedor, data_emissao, valor_total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$numero_nfe, $chave_nfe, $fornecedor_nome, $cnpj_fornecedor, $data_emissao, $valor_total]);
    $entrada_id = $conn->lastInsertId();

    // Totais da nota
    $totalProdutos = 0;
    foreach ($infNFe->det as $det) $totalProdutos += (float)$det->prod->vProd;

    $freteTotal  = (float)($infNFe->total->ICMSTot->vFrete ?? 0);
    $seguroTotal = (float)($infNFe->total->ICMSTot->vSeg ?? 0);
    $outrasTotal = (float)($infNFe->total->ICMSTot->vOutro ?? 0);
    $descTotal   = (float)($infNFe->total->ICMSTot->vDesc ?? 0);

    $atualizar_precos = isset($_POST['atualizar_precos']);

    $stmtItem = $conn->prepare("
        INSERT INTO vendas_entradas_itens 
        (entrada_id, produto_id, nome, codigo_ean, quantidade, preco_unitario, 
         frete_rateado, seguro_rateado, outros_rateado, desconto_rateado, custo_final_unit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $importados = 0;

    // ==========================
    // Loop de itens da NF-e
    // ==========================
    foreach ($infNFe->det as $det) {
        $p = $det->prod;
        $nome = trim((string)$p->xProd);
        $codigo = trim((string)$p->cProd);
        $ean = trim((string)$p->cEAN);
        $qtd = (float)$p->qCom;
        $preco = (float)$p->vUnCom;
        $total_item = (float)$p->vProd;

        if (!$nome || $qtd <= 0) continue;

        // Unidade comercial (detecta KG / UN)
        $unidade = strtoupper(trim((string)$p->uCom));
        if (!in_array($unidade, ['UN', 'KG'])) $unidade = 'UN';

        // Rateio proporcional
        $prop = ($totalProdutos > 0) ? ($total_item / $totalProdutos) : 0;
        $frete = $freteTotal * $prop;
        $seg = $seguroTotal * $prop;
        $outros = $outrasTotal * $prop;
        $desc = $descTotal * $prop;

        // Custo final unitário
        $custo_unit = $preco + (($frete + $seg + $outros - $desc) / max(1, $qtd));

        // Busca produto existente
        $stmtFind = $conn->prepare("SELECT id, margem_padrao, preco_custo, estoque_atual FROM vendas_estoque WHERE codigo_ean = ? OR nome = ? LIMIT 1");
        $stmtFind->execute([$ean, $nome]);
        $prodExist = $stmtFind->fetch(PDO::FETCH_ASSOC);
        $produto_id = $prodExist['id'] ?? null;

        // Margem efetiva (normalizada)
        $margem_efetiva = isset($prodExist['margem_padrao']) && $prodExist['margem_padrao'] > 0 
            ? floatval(str_replace(',', '.', $prodExist['margem_padrao'])) 
            : floatval(str_replace(',', '.', $margem_global));

        // Preço sugerido com base na margem efetiva
        $preco_sugerido = round($custo_unit * (1 + ($margem_efetiva / 100)), 2);

        if ($produto_id) {
            // Produto já existe → aplica custo médio ponderado
            $estoque_antigo = floatval($prodExist['estoque_atual'] ?? 0);
            $custo_antigo = floatval($prodExist['preco_custo'] ?? 0);
            $custo_medio = ($estoque_antigo + $qtd > 0)
                ? ((($custo_antigo * $estoque_antigo) + ($custo_unit * $qtd)) / ($estoque_antigo + $qtd))
                : $custo_unit;

            if ($atualizar_precos) {
                $conn->prepare("
                    UPDATE vendas_estoque 
                    SET estoque_atual = COALESCE(estoque_atual,0) + ?, 
                        preco_custo = ?, 
                        preco_venda = ?, 
                        preco_sugerido = NULL, 
                        tipo_unidade = ?
                    WHERE id = ?
                ")->execute([$qtd, $custo_medio, round($custo_medio * (1 + ($margem_efetiva / 100)), 2), $unidade, $produto_id]);
            } else {
                $conn->prepare("
                    UPDATE vendas_estoque 
                    SET estoque_atual = COALESCE(estoque_atual,0) + ?, 
                        preco_custo = ?, 
                        preco_sugerido = ?, 
                        tipo_unidade = ?
                    WHERE id = ?
                ")->execute([$qtd, $custo_medio, round($custo_medio * (1 + ($margem_efetiva / 100)), 2), $unidade, $produto_id]);
            }
        } else {
            // Novo produto
            if ($atualizar_precos) {
                $conn->prepare("
                    INSERT INTO vendas_estoque 
                        (nome, codigo_ean, preco_custo, preco_venda, preco_sugerido, estoque_atual, tipo_unidade, ativo, margem_padrao)
                    VALUES (?, ?, ?, ?, NULL, ?, ?, 1, ?)
                ")->execute([$nome, $ean ?: $codigo, $custo_unit, $preco_sugerido, $qtd, $unidade, $margem_efetiva]);
            } else {
                $conn->prepare("
                    INSERT INTO vendas_estoque 
                        (nome, codigo_ean, preco_custo, preco_venda, preco_sugerido, estoque_atual, tipo_unidade, ativo, margem_padrao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                ")->execute([$nome, $ean ?: $codigo, $custo_unit, $custo_unit, $preco_sugerido, $qtd, $unidade, $margem_efetiva]);
            }

            $produto_id = $conn->lastInsertId();
        }

        // Registra item detalhado
        $stmtItem->execute([
            $entrada_id, $produto_id, $nome, $ean ?: $codigo, $qtd, $preco,
            $frete, $seg, $outros, $desc, $custo_unit
        ]);

        $importados++;
    }

    if ($importados === 0) throw new Exception("Nenhum item importado da nota.");

    // Verifica se há preços pendentes
    $stmtCheckSug = $conn->query("SELECT COUNT(*) FROM vendas_estoque WHERE preco_sugerido IS NOT NULL AND preco_sugerido <> preco_venda");
    $pendentes = $stmtCheckSug->fetchColumn();

    if ($pendentes > 0) {
        $msg = "NF-e importada com sucesso ({$importados} itens). Existem {$pendentes} produto(s) aguardando revisão de preço.";
    } else {
        $msg = $atualizar_precos
            ? "NF-e importada com sucesso ({$importados} itens atualizados automaticamente com margem aplicada e custo médio recalculado)."
            : "NF-e importada com sucesso ({$importados} itens com custo médio atualizado).";
    }

    header("Location: index.php?ok=1&msg=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>