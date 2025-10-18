<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);
$categoria = [
    'nome' => '',
    'descricao' => ''
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM financeiro_categorias WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-layers me-2"></i><?= $id ? 'Editar Categoria' : 'Nova Categoria' ?></h4>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <form action="salvar.php" method="POST" class="card shadow-sm border-0 p-4">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nome da Categoria</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($categoria['nome']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Descrição</label>
                <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($categoria['descricao']) ?>">
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-danger px-4"><i class="bi bi-save me-2"></i>Salvar Categoria</button>
        </div>
    </form>
</div>

<?php endContent(); ?>