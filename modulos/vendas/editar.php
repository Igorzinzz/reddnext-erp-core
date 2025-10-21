<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);

// =====================
// Buscar dados da venda
// =====================
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vendas WHERE id = ?");
    $stmt->execute([$id]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtItens = $conn->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
    $stmtItens->execute([$id]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
} else {
    $venda = [
        'cliente_id' => '',
        'data_venda' => date('Y-m-d'),
        'forma_pagamento' => '',
        'status' => 'pendente',
        'valor_total' => 0,
        'desconto' => 0,
        'acrescimo' => 0
    ];
    $itens = [];
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-cart3 me-2"></i><?= $id ? 'Editar Venda' : 'Nova Venda' ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <form action="salvar.php" method="POST" class="card shadow-sm border-0 p-4">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-4 mb-4">
            <!-- Cliente -->
            <div class="col-md-4">
                <label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Cliente</label>
                <select name="cliente_id" id="clienteSelect" class="form-select">
                    <option value="">Sem cliente</option>
                    <?php if (!empty($venda['cliente_id'])): ?>
                        <?php
                        $stmtCli = $conn->prepare("SELECT nome FROM clientes WHERE id = ?");
                        $stmtCli->execute([$venda['cliente_id']]);
                        $clienteNome = $stmtCli->fetchColumn();
                        ?>
                        <?php if ($clienteNome): ?>
                            <option value="<?= $venda['cliente_id'] ?>" selected><?= htmlspecialchars($clienteNome) ?></option>
                        <?php endif; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Data -->
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-calendar-date me-1"></i>Data</label>
                <input type="date" name="data_venda" class="form-control" value="<?= $venda['data_venda'] ?>" required>
            </div>

            <!-- Forma de Pagamento -->
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-credit-card me-1"></i>Forma de Pagamento</label>
                <select name="forma_pagamento" class="form-select" required>
                    <option value="">Selecione</option>
                    <option value="Pix" <?= $venda['forma_pagamento']=='Pix'?'selected':'' ?>>Pix</option>
                    <option value="Cartão" <?= $venda['forma_pagamento']=='Cartão'?'selected':'' ?>>Cartão</option>
                    <option value="Dinheiro" <?= $venda['forma_pagamento']=='Dinheiro'?'selected':'' ?>>Dinheiro</option>
                    <option value="Transferência" <?= $venda['forma_pagamento']=='Transferência'?'selected':'' ?>>Transferência</option>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="bi bi-flag me-1"></i>Status</label>
                <select name="status" class="form-select" required>
                    <option value="pendente" <?= $venda['status']=='pendente'?'selected':'' ?>>Pendente</option>
                    <option value="pago" <?= $venda['status']=='pago'?'selected':'' ?>>Pago</option>
                    <option value="cancelada" <?= $venda['status']=='cancelada'?'selected':'' ?>>Cancelado</option>
                </select>
            </div>
        </div>

        <hr>

        <h5 class="fw-bold mb-3"><i class="bi bi-box-seam me-2"></i>Produtos</h5>

        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th style="width:50%">Produto</th>
                        <th style="width:20%">Quantidade</th>
                        <th style="width:20%">Valor Unitário</th>
                        <th style="width:10%" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody id="itensContainer">
                    <?php foreach ($itens as $i => $item): ?>
                        <?php
                        $stmtP = $conn->prepare("SELECT nome, tipo_unidade FROM vendas_estoque WHERE id = ?");
                        $stmtP->execute([$item['produto_id']]);
                        $p = $stmtP->fetch(PDO::FETCH_ASSOC);
                        $unidade = $p['tipo_unidade'] ?? 'UN';
                        ?>
                        <tr class="item-linha">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <select name="produtos[<?= $i ?>][id]" class="form-select produtoSelect" required>
                                        <option value="<?= $item['produto_id'] ?>" selected><?= htmlspecialchars($p['nome']) ?></option>
                                    </select>
                                    <span class="badge bg-light text-dark border unidadeBadge"><?= $unidade ?></span>
                                </div>
                            </td>
                            <td>
                                <input type="number"
                                       step="<?= $unidade == 'KG' ? '0.001' : '1' ?>"
                                       min="<?= $unidade == 'KG' ? '0.001' : '1' ?>"
                                       name="produtos[<?= $i ?>][qtd]"
                                       value="<?= $item['quantidade'] ?>"
                                       class="form-control qtdInput text-center"
                                       required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="produtos[<?= $i ?>][valor]"
                                       value="<?= $item['valor_unitario'] ?>"
                                       class="form-control valorInput text-center" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm removerItem">
                                    <i class="bi bi-x"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="addItem">
                <i class="bi bi-plus-circle"></i> Adicionar Produto
            </button>
        </div>

        <hr class="my-4">

        <!-- Ajustes -->
        <div class="row justify-content-end mb-3">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Tipo de Ajuste</label>
                <select name="ajuste_tipo" id="ajusteTipo" class="form-select">
                    <option value="desconto" <?= ($venda['acrescimo'] <= 0) ? 'selected' : '' ?>>Desconto</option>
                    <option value="acrescimo" <?= ($venda['acrescimo'] > 0) ? 'selected' : '' ?>>Acréscimo</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Valor do Ajuste (R$)</label>
                <input type="number" step="0.01" name="ajuste_valor" id="ajusteValor" class="form-control text-end"
                       value="<?= number_format(max($venda['desconto'], $venda['acrescimo']), 2, '.', '') ?>">
            </div>
        </div>

        <!-- Totais -->
        <div class="row justify-content-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Valor Total</label>
                <input type="text" name="valor_total" id="valorTotal"
                       class="form-control fw-bold text-danger text-end"
                       readonly value="<?= number_format($venda['valor_total'], 2, ',', '.') ?>">
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-danger px-4">
                <i class="bi bi-save me-2"></i>Salvar Venda
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    const container = $("#itensContainer");

    // === CLIENTE ===
    $('#clienteSelect').select2({
        placeholder: "Digite o nome do cliente...",
        allowClear: true,
        ajax: {
            url: "clientes/buscar.php",
            dataType: 'json',
            delay: 250,
            data: params => ({ term: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        theme: "bootstrap4",
        width: '100%',
        minimumInputLength: 2
    });

    // === PRODUTOS ===
    function ativarSelect2Produtos() {
        $('.produtoSelect').select2({
            placeholder: "Digite o nome do produto...",
            ajax: {
                url: "estoque/buscar.php",
                dataType: 'json',
                delay: 250,
                data: params => ({ term: params.term }),
                processResults: data => ({ results: data }),
                cache: true
            },
            theme: "bootstrap4",
            width: '100%',
            minimumInputLength: 2
        }).on('select2:select', function(e) {
            const data = e.params.data;
            const linha = $(this).closest('tr');
            const unidade = data.tipo_unidade || 'UN';
            linha.find('.unidadeBadge').text(unidade);
            const inputQtd = linha.find('.qtdInput');
            inputQtd.attr('step', unidade === 'KG' ? '0.001' : '1');
            inputQtd.attr('min', unidade === 'KG' ? '0.001' : '1');
            inputQtd.val(unidade === 'KG' ? '0.001' : '1');
            linha.find('.valorInput').val(parseFloat(data.preco).toFixed(2));
            atualizarTotal();
        });
    }

    ativarSelect2Produtos();

    // === Adicionar item ===
    $("#addItem").on("click", function() {
        const index = container.find(".item-linha").length;
        const html = `
        <tr class="item-linha">
            <td>
                <div class="d-flex align-items-center gap-2">
                    <select name="produtos[${index}][id]" class="form-select produtoSelect" required></select>
                    <span class="badge bg-light text-dark border unidadeBadge">UN</span>
                </div>
            </td>
            <td><input type="number" step="1" min="1" name="produtos[${index}][qtd]" value="1" class="form-control qtdInput text-center" required></td>
            <td><input type="number" step="0.01" name="produtos[${index}][valor]" class="form-control valorInput text-center" required></td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm removerItem"><i class="bi bi-x"></i></button></td>
        </tr>`;
        container.append(html);
        ativarSelect2Produtos();
    });

    // === Remover item ===
    container.on("click", ".removerItem", function() {
        $(this).closest(".item-linha").remove();
        atualizarTotal();
    });

    // === Atualizar total ===
    function atualizarTotal() {
        let total = 0;
        $(".item-linha").each(function() {
            const qtd = parseFloat($(this).find(".qtdInput").val()) || 0;
            const valor = parseFloat($(this).find(".valorInput").val()) || 0;
            total += qtd * valor;
        });

        const ajusteTipo = $("#ajusteTipo").val();
        const ajusteValor = parseFloat($("#ajusteValor").val()) || 0;

        if (ajusteTipo === 'desconto') total -= ajusteValor;
        else total += ajusteValor;

        if (total < 0) total = 0;

        $("#valorTotal").val(total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
    }

    container.on("input", ".qtdInput, .valorInput", atualizarTotal);
    $("#ajusteValor, #ajusteTipo").on("input change", atualizarTotal);
});
</script>

<?php endContent(); ?>