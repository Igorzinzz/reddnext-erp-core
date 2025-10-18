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
        'valor_total' => 0
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
                <label class="form-label fw-semibold">
                    <i class="bi bi-person me-1"></i>Cliente
                </label>
                <select name="cliente_id" id="clienteSelect" class="form-select" required>
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
                <label class="form-label fw-semibold">
                    <i class="bi bi-calendar-date me-1"></i>Data
                </label>
                <input type="date" name="data_venda" class="form-control" value="<?= $venda['data_venda'] ?>" required>
            </div>

            <!-- Forma de Pagamento -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    <i class="bi bi-credit-card me-1"></i>Forma de Pagamento
                </label>
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
                <label class="form-label fw-semibold">
                    <i class="bi bi-flag me-1"></i>Status
                </label>
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
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:50%">Produto</th>
                        <th style="width:15%">Quantidade</th>
                        <th style="width:20%">Valor Unitário</th>
                        <th style="width:10%">Ações</th>
                    </tr>
                </thead>
                <tbody id="itensContainer">
                    <?php foreach ($itens as $i => $item): ?>
                        <tr class="item-linha">
                            <td>
                                <select name="produtos[<?= $i ?>][id]" class="form-select produtoSelect" required>
                                    <?php
                                    $stmtP = $conn->prepare("SELECT nome FROM vendas_estoque WHERE id = ?");
                                    $stmtP->execute([$item['produto_id']]);
                                    $nomeProd = $stmtP->fetchColumn();
                                    ?>
                                    <option value="<?= $item['produto_id'] ?>" selected><?= htmlspecialchars($nomeProd) ?></option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="1" min="1" name="produtos[<?= $i ?>][qtd]"
                                       value="<?= $item['quantidade'] ?>" class="form-control qtdInput" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="produtos[<?= $i ?>][valor]"
                                       value="<?= $item['valor_unitario'] ?>" class="form-control valorInput" required>
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

        <div class="row justify-content-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Valor Total</label>
                <input type="text" name="valor_total" id="valorTotal"
                       class="form-control fw-bold text-danger"
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

    // === CLIENTE (busca AJAX) ===
    $('#clienteSelect').select2({
        placeholder: "Digite o nome do cliente...",
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

    // === PRODUTOS (busca AJAX) ===
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
            linha.find('.valorInput').val(parseFloat(data.preco).toFixed(2));
            atualizarTotal();
        });
    }

    ativarSelect2Produtos();

    // === Adicionar novo item ===
    $("#addItem").on("click", function() {
        const index = container.find(".item-linha").length;
        const html = `
        <tr class="item-linha">
            <td><select name="produtos[${index}][id]" class="form-select produtoSelect" required></select></td>
            <td><input type="number" step="1" min="1" name="produtos[${index}][qtd]" class="form-control qtdInput" value="1" required></td>
            <td><input type="number" step="0.01" name="produtos[${index}][valor]" class="form-control valorInput" required></td>
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
        $("#valorTotal").val(total.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));
    }

    container.on("input", ".qtdInput, .valorInput", atualizarTotal);
});
</script>

<?php endContent(); ?>