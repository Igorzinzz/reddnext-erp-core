<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);

// Buscar dados do cliente se estiver editando
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $cliente = [
        'nome' => '',
        'documento' => '',
        'telefone' => '',
        'email' => '',
        'endereco' => '',
        'cidade' => '',
        'uf' => '',
        'ativo' => 1
    ];
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-person-lines-fill me-2"></i>
            <?= $id ? 'Editar Cliente' : 'Novo Cliente' ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <form action="salvar.php" method="POST" class="card shadow-sm border-0 p-4">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-4">

            <!-- Nome -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nome do Cliente</label>
                <input type="text" name="nome" class="form-control" required
                       value="<?= htmlspecialchars($cliente['nome']) ?>">
            </div>

            <!-- Documento -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">CPF / CNPJ</label>
                <input type="text" name="documento" class="form-control"
                       value="<?= htmlspecialchars($cliente['documento']) ?>" placeholder="Somente números">
            </div>

            <!-- Telefone -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Telefone</label>
                <input type="text" name="telefone" class="form-control"
                       value="<?= htmlspecialchars($cliente['telefone']) ?>" placeholder="(99) 99999-9999">
            </div>

            <!-- E-mail -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">E-mail</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($cliente['email']) ?>" placeholder="cliente@email.com">
            </div>

            <!-- Endereço -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Endereço</label>
                <input type="text" name="endereco" class="form-control"
                       value="<?= htmlspecialchars($cliente['endereco']) ?>" placeholder="Rua, número, bairro...">
            </div>

            <!-- Cidade -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Cidade</label>
                <input type="text" name="cidade" class="form-control"
                       value="<?= htmlspecialchars($cliente['cidade']) ?>">
            </div>

            <!-- UF -->
            <div class="col-md-2">
                <label class="form-label fw-semibold">UF</label>
                <select name="uf" class="form-select">
                    <option value="">Selecione</option>
                    <?php
                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                    foreach ($ufs as $uf) {
                        $sel = ($cliente['uf'] == $uf) ? 'selected' : '';
                        echo "<option value='{$uf}' {$sel}>{$uf}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-check form-switch mt-4">
            <input class="form-check-input" type="checkbox" name="ativo" value="1" <?= $cliente['ativo'] ? 'checked' : '' ?>>
            <label class="form-check-label">Cliente Ativo</label>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-danger px-4">
                <i class="bi bi-save me-2"></i>Salvar Cliente
            </button>
        </div>
    </form>
</div>

<?php endContent(); ?>