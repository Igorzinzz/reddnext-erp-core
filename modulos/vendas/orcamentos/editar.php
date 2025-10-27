<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);

// ==========================
// Carrega orçamento existente (se houver)
// ==========================
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vendas_orcamentos WHERE id = ?");
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
        'cliente_nome' => '',
        'cliente_telefone' => '',
        'validade' => date('Y-m-d', strtotime('+7 days')),
        'desconto' => 0,
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
            <div class="col-md-5">
                <label class="form-label fw-semibold"><i class="bi bi-person me-1"></i>Cliente</label>
                <input type="text" name="cliente_nome" class="form-control" placeholder="Nome do cliente" required
                       value="<?= htmlspecialchars($orcamento['cliente_nome']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-telephone me-1"></i>Telefone</label>
                <input type="text" name="cliente_telefone" class="form-control" placeholder="(00) 00000-0000"
                       value="<?= htmlspecialchars($orcamento['cliente_telefone']) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="bi bi-calendar me-1"></i>Validade</label>
                <input type="date" name="validade" class="form-control"
                       value="<?= htmlspecialchars($orcamento['validade']) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="bi bi-percent me-1"></i>Desconto (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="desconto" id="desconto" class="form-control text-end"
                       value="<?= htmlspecialchars($orcamento['desconto']) ?>">
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

    // === ATIVAR SELECT2 ===
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
            $(this).find(".subtotal").val(subtotal.toFixed(2).replace('.',','));
            total += subtotal;
        });
        const desconto = parseFloat($("#desconto").val()) || 0;
        if(desconto > 0) total -= (total * desconto / 100);
        $("#totalGeral").text(total.toFixed(2).replace('.',','));
    }

    container.on("input", ".qtdInput, .valorInput", atualizarTotais);
    $("#desconto").on("input", atualizarTotais);
    atualizarTotais();
});
</script>

<?php endContent(); ?>