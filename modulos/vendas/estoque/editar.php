<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vendas_estoque WHERE id = ?");
    $stmt->execute([$id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $produto = [
        'nome' => '',
        'codigo_ean' => '',
        'preco_custo' => '',
        'preco_venda' => '',
        'estoque_atual' => '',
        'estoque_minimo' => '',
        'ativo' => 1,
        'imagem_url' => '',
        'tipo_unidade' => 'UN',
        'peso_variavel' => 0
    ];
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-box-seam me-2"></i><?= $id ? 'Editar Produto' : 'Novo Produto' ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <form action="salvar.php" method="POST" enctype="multipart/form-data" class="card shadow-sm border-0 p-4">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="remover_imagem" id="remover_imagem" value="0">

        <div class="row g-4">
            <!-- Nome -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nome do Produto</label>
                <input type="text" name="nome" class="form-control" required
                       placeholder="Ex: Suco de Uva 500ml"
                       value="<?= htmlspecialchars($produto['nome']) ?>">
            </div>

            <!-- EAN -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Código EAN</label>
                <input type="text" name="codigo_ean" class="form-control" 
                       placeholder="Ex: 7891234567890"
                       pattern="[0-9]{8,13}"
                       title="Somente números (8 a 13 dígitos)"
                       value="<?= htmlspecialchars($produto['codigo_ean']) ?>">
                <div class="form-text text-muted">Código de barras (EAN-8 ou EAN-13)</div>
            </div>

            <!-- Tipo de Unidade -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tipo de Unidade</label>
                <select name="tipo_unidade" id="tipo_unidade" class="form-select" required>
                    <option value="UN" <?= $produto['tipo_unidade'] === 'UN' ? 'selected' : '' ?>>Unidade (UN)</option>
                    <option value="KG" <?= $produto['tipo_unidade'] === 'KG' ? 'selected' : '' ?>>Quilograma (KG)</option>
                </select>
                <div class="form-text text-muted">Define como o produto é vendido</div>
            </div>

            <!-- Preço de Venda -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Preço de Venda (R$)</label>
                <input type="number" step="0.01" name="preco_venda" class="form-control" required
                       value="<?= htmlspecialchars($produto['preco_venda']) ?>">
                <div class="form-text text-muted">Preço por unidade ou por kg</div>
            </div>

            <!-- Preço de Custo -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Preço de Custo (R$)</label>
                <input type="number" step="0.01" name="preco_custo" class="form-control" required
                       value="<?= htmlspecialchars($produto['preco_custo']) ?>">
            </div>

            <!-- Estoques -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Estoque Atual</label>
                <input type="number" name="estoque_atual" id="estoque_atual" class="form-control" required
                       value="<?= htmlspecialchars($produto['estoque_atual']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Estoque Mínimo</label>
                <input type="number" name="estoque_minimo" id="estoque_minimo" class="form-control"
                       value="<?= htmlspecialchars($produto['estoque_minimo']) ?>">
            </div>

            <!-- Peso Variável -->
            <div class="col-md-3">
                <label class="form-label fw-semibold d-block">Produto de Peso Variável</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="peso_variavel" value="1"
                           <?= !empty($produto['peso_variavel']) ? 'checked' : '' ?>>
                    <label class="form-check-label">Sim (preço calculado pelo peso)</label>
                </div>
                <div class="form-text text-muted">Usado para carnes, frutas, frios etc.</div>
            </div>

            <!-- Imagem -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Imagem do Produto</label>
                <input type="file" name="imagem" id="imagem" accept="image/*" class="form-control">
                <div class="form-text text-muted">Opcional — formatos: JPG, PNG, WEBP</div>

                <div class="mt-3" id="previewBox" style="display: <?= !empty($produto['imagem_url']) ? 'block' : 'none' ?>;">
                    <img id="previewImg"
                         src="<?= htmlspecialchars($produto['imagem_url'] ?? '') ?>"
                         alt="Pré-visualização"
                         class="img-thumbnail"
                         style="max-width:150px; max-height:150px; object-fit:contain;">
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoverImg">
                            <i class="bi bi-trash"></i> Remover Imagem
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ativo -->
        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" name="ativo" value="1"
                   <?= $produto['ativo'] ? 'checked' : '' ?>>
            <label class="form-check-label">Produto Ativo</label>
        </div>

        <!-- Botão -->
        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-danger px-4">
                <i class="bi bi-save me-2"></i>Salvar Produto
            </button>
        </div>
    </form>
</div>

<script>
// --- Pré-visualização instantânea ---
document.getElementById("imagem")?.addEventListener("change", function (e) {
    const file = e.target.files[0];
    const previewBox = document.getElementById("previewBox");
    const previewImg = document.getElementById("previewImg");

    if (!file) {
        previewBox.style.display = "none";
        previewImg.src = "";
        return;
    }

    const reader = new FileReader();
    reader.onload = function (ev) {
        previewImg.src = ev.target.result;
        previewBox.style.display = "block";
        document.getElementById("remover_imagem").value = "0";
    };
    reader.readAsDataURL(file);
});

// --- Remover imagem atual ---
document.getElementById("btnRemoverImg")?.addEventListener("click", () => {
    if (confirm("Remover a imagem atual do produto?")) {
        document.getElementById("previewImg").src = "";
        document.getElementById("previewBox").style.display = "none";
        document.getElementById("remover_imagem").value = "1";
    }
});

// --- Regras de unidade (UN vs KG) ---
document.addEventListener('DOMContentLoaded', () => {
  const tipo = document.getElementById('tipo_unidade');
  const estoqueInputs = [document.getElementById('estoque_atual'), document.getElementById('estoque_minimo')];

  function aplicarRestricao() {
    const isKg = tipo.value === 'KG';
    estoqueInputs.forEach(input => {
      input.step = isKg ? "0.001" : "1";
      input.min = "0";
      input.value = isKg
        ? parseFloat(input.value || 0).toFixed(3)
        : Math.floor(parseFloat(input.value || 0));
    });
  }

  tipo.addEventListener('change', aplicarRestricao);
  aplicarRestricao(); // aplica ao carregar
});
</script>

<?php endContent(); ?>