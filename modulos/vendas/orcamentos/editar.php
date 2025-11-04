<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);

// ==========================
// Carrega orçamento existente (com cliente JOIN)
// ==========================
if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT o.*, c.nome AS cliente_nome
        FROM vendas_orcamentos o
        LEFT JOIN clientes c ON c.id = o.cliente_id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        echo "<div class='alert alert-danger mt-4'>Orçamento não encontrado.</div>";
        endContent(); exit;
    }

    $stmtItens = $conn->prepare("
        SELECT i.*, p.nome, p.tipo_unidade 
        FROM vendas_orcamentos_itens i
        LEFT JOIN vendas_estoque p ON p.id = i.produto_id
        WHERE i.orcamento_id = ?
    ");
    $stmtItens->execute([$id]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
} else {
    $orcamento = [
        'cliente_id' => '',
        'cliente_nome' => '',
        'validade' => date('Y-m-d', strtotime('+7 days')),
        'desconto' => 0,
        'tipo_desconto' => '%',
        'observacoes' => '',
        'total' => 0
    ];
    $itens = [];
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-text me-2"></i><?= $id ? 'Editar Orçamento' : 'Novo Orçamento' ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <form action="salvar.php" method="POST" class="card shadow-sm border-0 p-4" id="formOrcamento">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-4 mb-4">
            <!-- CLIENTE -->
            <div class="col-md-6">
                <label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Cliente</label>
                <select name="cliente_id" id="clienteSelect" class="form-select" required>
                    <?php if (!empty($orcamento['cliente_id'])): ?>
                        <option value="<?= $orcamento['cliente_id'] ?>" selected>
                            <?= htmlspecialchars($orcamento['cliente_nome']) ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-calendar me-1"></i>Validade</label>
                <input type="date" name="validade" class="form-control"
                       value="<?= htmlspecialchars($orcamento['validade']) ?>">
            </div>

            <!-- DESCONTO -->
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-tag me-1"></i>Desconto</label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" name="desconto" id="desconto"
                           class="form-control text-end"
                           value="<?= htmlspecialchars($orcamento['desconto']) ?>">
                    <select name="tipo_desconto" id="tipoDesconto" class="form-select" style="max-width:80px;">
                        <option value="%" <?= ($orcamento['tipo_desconto'] ?? '%') === '%' ? 'selected' : '' ?>>%</option>
                        <option value="R$" <?= ($orcamento['tipo_desconto'] ?? '') === 'R$' ? 'selected' : '' ?>>R$</option>
                    </select>
                </div>
            </div>
        </div>

        <hr>

        <h5 class="fw-bold mb-3"><i class="bi bi-box-seam me-2"></i>Produtos</h5>

        <div class="table-responsive">
            <table class="table align-middle table-hover" id="tabelaProdutos">
                <thead class="table-light">
                    <tr>
                        <th style="width:50%">Produto</th>
                        <th style="width:15%">Qtd</th>
                        <th style="width:20%">Preço (R$)</th>
                        <th style="width:15%">Subtotal</th>
                        <th class="text-center" style="width:5%">Ações</th>
                    </tr>
                </thead>
                <tbody id="itensContainer">
                    <?php foreach ($itens as $i => $item): ?>
                        <tr class="item-linha">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <select name="produtos[<?= $i ?>][id]" class="form-select produtoSelect" required>
                                        <option value="<?= $item['produto_id'] ?>" selected><?= htmlspecialchars($item['nome']) ?></option>
                                    </select>
                                    <span class="badge bg-light text-dark border unidadeBadge"><?= $item['tipo_unidade'] ?? 'UN' ?></span>
                                </div>
                            </td>
                            <td>
                                <input type="number"
                                       step="<?= ($item['tipo_unidade'] ?? 'UN') === 'KG' ? '0.001' : '1' ?>"
                                       min="<?= ($item['tipo_unidade'] ?? 'UN') === 'KG' ? '0.001' : '1' ?>"
                                       name="produtos[<?= $i ?>][qtd]"
                                       value="<?= $item['quantidade'] ?>"
                                       class="form-control qtdInput text-center"
                                       required>
                            </td>
                            <td><input type="number" step="0.01" name="produtos[<?= $i ?>][valor]" class="form-control valorInput text-center" value="<?= $item['preco_unitario'] ?>"></td>
                            <td><input type="text" class="form-control subtotal text-end" readonly></td>
                            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm removerItem"><i class="bi bi-x"></i></button></td>
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

        <div class="mb-3">
            <label class="form-label fw-semibold">Observações</label>
            <textarea name="observacoes" rows="3" class="form-control"><?= htmlspecialchars($orcamento['observacoes']) ?></textarea>
        </div>

        <div class="text-end">
            <h5 class="fw-bold">Total: R$ <span id="totalGeral">0,00</span></h5>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-danger px-4">
                <i class="bi bi-save me-2"></i>Salvar Orçamento
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function(){
    const container = $("#itensContainer");

    // === CLIENTE (Select2) ===
    $('#clienteSelect').select2({
        placeholder: "Selecione um cliente...",
        ajax: {
            url: "../clientes/buscar.php",
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

    // === PRODUTOS (Select2) ===
    function ativarSelect2Produtos(){
        $('.produtoSelect').select2({
            placeholder: "Digite o nome do produto...",
            ajax: {
                url: "../estoque/buscar.php",
                dataType: 'json',
                delay: 250,
                data: params => ({ term: params.term }),
                processResults: data => ({ results: data }),
                cache: true
            },
            theme: "bootstrap4",
            width: '100%',
            minimumInputLength: 2
        }).on('select2:select', function(e){
            const data = e.params.data;
            const linha = $(this).closest('tr');
            const unidade = data.tipo_unidade || 'UN';
            linha.find('.unidadeBadge').text(unidade);
            const qtdInput = linha.find('.qtdInput');
            qtdInput.attr('step', unidade === 'KG' ? '0.001' : '1');
            qtdInput.attr('min', unidade === 'KG' ? '0.001' : '1');
            qtdInput.val(unidade === 'KG' ? '0.001' : '1');
            linha.find('.valorInput').val(parseFloat(data.preco).toFixed(2));
            atualizarTotais();
        });
    }

    ativarSelect2Produtos();

    // === ADICIONAR ITEM ===
    $("#addItem").on("click", function(){
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
            <td><input type="number" step="0.01" name="produtos[${index}][valor]" class="form-control valorInput text-center" value="0.00" required></td>
            <td><input type="text" class="form-control subtotal text-end" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm removerItem"><i class="bi bi-x"></i></button></td>
        </tr>`;
        container.append(html);
        ativarSelect2Produtos();
    });

    // === REMOVER ITEM ===
    container.on("click", ".removerItem", function(){
        $(this).closest("tr").remove();
        atualizarTotais();
    });

    // === ATUALIZAR TOTAL ===
    function atualizarTotais(){
        let total = 0;
        $(".item-linha").each(function(){
            const qtd = parseFloat($(this).find(".qtdInput").val()) || 0;
            const valor = parseFloat($(this).find(".valorInput").val()) || 0;
            const subtotal = qtd * valor;
            $(this).find(".subtotal").val(formatarValor(subtotal));
            total += subtotal;
        });

        const desconto = parseFloat($("#desconto").val()) || 0;
        const tipo = $("#tipoDesconto").val();

        if (desconto > 0) {
            if (tipo === "%") total -= (total * desconto / 100);
            else total -= desconto;
        }

        $("#totalGeral").text(formatarValor(total));
    }

    function formatarValor(valor) {
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    container.on("input", ".qtdInput, .valorInput", atualizarTotais);
    $("#desconto, #tipoDesconto").on("input change", atualizarTotais);
    atualizarTotais();
});
</script>

<?php endContent(); ?>