<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);
$conta = [
    'nome_exibicao' => '',
    'banco' => '',
    'agencia' => '',
    'numero_conta' => '',
    'tipo' => 'corrente',
    'saldo_inicial' => '0.00',
    'ativo' => 1
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM financeiro_contas WHERE id = ?");
    $stmt->execute([$id]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-bank2 me-2"></i><?= $id ? 'Editar Conta' : 'Nova Conta Bancária' ?></h4>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <form action="salvar.php" method="POST" class="card shadow-sm border-0 p-4">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nome de Exibição</label>
                <input type="text" name="nome_exibicao" class="form-control" required value="<?= htmlspecialchars($conta['nome_exibicao']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Banco</label>
                <input type="text" name="banco" class="form-control" required value="<?= htmlspecialchars($conta['banco']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Agência</label>
                <input type="text" name="agencia" class="form-control" value="<?= htmlspecialchars($conta['agencia']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Número da Conta</label>
                <input type="text" name="numero_conta" class="form-control" value="<?= htmlspecialchars($conta['numero_conta']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="corrente" <?= $conta['tipo']=='corrente'?'selected':'' ?>>Corrente</option>
                    <option value="poupanca" <?= $conta['tipo']=='poupanca'?'selected':'' ?>>Poupança</option>
                    <option value="pj" <?= $conta['tipo']=='pj'?'selected':'' ?>>PJ</option>
                    <option value="outro" <?= $conta['tipo']=='outro'?'selected':'' ?>>Outro</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Saldo Inicial</label>
                <input type="number" step="0.01" name="saldo_inicial" class="form-control" value="<?= htmlspecialchars($conta['saldo_inicial']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="ativo" class="form-select">
                    <option value="1" <?= $conta['ativo']?'selected':'' ?>>Ativa</option>
                    <option value="0" <?= !$conta['ativo']?'selected':'' ?>>Inativa</option>
                </select>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-danger px-4"><i class="bi bi-save me-2"></i>Salvar Conta</button>
        </div>
    </form>
</div>

<?php endContent(); ?>