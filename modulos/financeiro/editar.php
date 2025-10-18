<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

$id = intval($_GET['id'] ?? 0);

// ============================
// Buscar contas ativas
// ============================
$stmtContas = $conn->query("
    SELECT id, nome_exibicao, banco, agencia, numero_conta 
    FROM financeiro_contas 
    WHERE ativo = 1 
    ORDER BY nome_exibicao ASC
");
$contas = $stmtContas->fetchAll(PDO::FETCH_ASSOC);

// ============================
// Buscar categorias
// ============================
if ($conn->query("SHOW TABLES LIKE 'financeiro_categorias'")->rowCount() > 0) {
    $stmtCat = $conn->query("SELECT id, nome FROM financeiro_categorias WHERE ativo = 1 ORDER BY nome ASC");
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} else {
    $categorias = [];
}

// ============================
// Bloquear se não houver contas/categorias
// ============================
if (count($contas) === 0 || count($categorias) === 0) {
    echo '<div class="container py-5 text-center">';
    echo '<div class="alert alert-warning shadow-sm p-4">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    if (count($contas) === 0 && count($categorias) === 0) {
        echo '⚠️ Você precisa cadastrar ao menos <strong>uma conta bancária ativa</strong> e <strong>uma categoria ativa</strong>.';
    } elseif (count($contas) === 0) {
        echo '⚠️ Você precisa cadastrar ao menos <strong>uma conta bancária ativa</strong>.';
    } else {
        echo '⚠️ Você precisa cadastrar ao menos <strong>uma categoria ativa</strong>.';
    }
    echo '<hr>';
    echo '<a href="../financeiro/contas/" class="btn btn-danger me-2"><i class="bi bi-bank2 me-1"></i> Gerenciar Contas</a>';
    echo '<a href="../financeiro/categorias/" class="btn btn-outline-danger"><i class="bi bi-layers me-1"></i> Gerenciar Categorias</a>';
    echo '</div></div>';
    endContent();
    exit;
}

// ============================
// Dados do Lançamento
// ============================
$lancamento = [
    'tipo' => 'despesa',
    'categoria' => '',
    'descricao' => '',
    'valor' => '',
    'data_lancamento' => date('Y-m-d'),
    'data_vencimento' => date('Y-m-d'),
    'forma_pagamento' => '',
    'conta' => '',
    'status' => 'pendente',
    'referencia_tipo' => '',
    'referencia_id' => '',
    'chave_pix' => '',
    'banco' => '',
    'agencia' => '',
    'numero_conta' => '',
    'favorecido' => '',
    'anexo_boleto' => '',
    'anexo_comprovante' => '',
    'anexo_nfe' => ''
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM financeiro WHERE id = ?");
    $stmt->execute([$id]);
    $lancamento = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================
// Bloqueio se for lançamento automático (ex: venda)
// ============================
$bloqueado = !empty($lancamento['referencia_tipo']);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-wallet2 me-2"></i>
            <?= $id ? 'Editar Lançamento' : 'Novo Lançamento' ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($bloqueado): ?>
        <div class="alert alert-warning shadow-sm">
            <i class="bi bi-lock-fill me-2"></i>
            Este lançamento foi gerado automaticamente pelo módulo 
            <strong><?= ucfirst($lancamento['referencia_tipo']) ?></strong> 
            (referência #<?= htmlspecialchars($lancamento['referencia_id']) ?>)
            e não pode ser editado manualmente.
            <a href="../<?= $lancamento['referencia_tipo'] ?>/editar.php?id=<?= $lancamento['referencia_id'] ?>" class="btn btn-sm btn-outline-primary ms-2">
                <i class="bi bi-link-45deg"></i> Ver <?= ucfirst($lancamento['referencia_tipo']) ?>
            </a>
        </div>
    <?php endif; ?>

    <form action="<?= $bloqueado ? '#' : 'salvar.php' ?>" 
          method="POST" 
          enctype="multipart/form-data" 
          class="card shadow-sm border-0 p-4">
        <input type="hidden" name="id" value="<?= $id ?>">

        <fieldset <?= $bloqueado ? 'disabled' : '' ?>>
            <div class="row g-4">
                <!-- Tipo -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-tag me-1"></i>Tipo</label>
                    <select name="tipo" id="tipo" class="form-select" required>
                        <option value="receita" <?= $lancamento['tipo']=='receita'?'selected':'' ?>>Receita</option>
                        <option value="despesa" <?= $lancamento['tipo']=='despesa'?'selected':'' ?>>Despesa</option>
                    </select>
                </div>

                <!-- Categoria -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-layers me-1"></i>Categoria</label>
                    <select name="categoria" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= htmlspecialchars($c['nome']) ?>"
                                <?= $lancamento['categoria'] == $c['nome'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Conta -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-bank2 me-1"></i>Conta Bancária</label>
                    <select name="conta" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach ($contas as $ct): ?>
                            <option value="<?= htmlspecialchars($ct['nome_exibicao']) ?>"
                                <?= $lancamento['conta'] == $ct['nome_exibicao'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ct['nome_exibicao']) ?> — <?= htmlspecialchars($ct['banco']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-flag me-1"></i>Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="pendente" <?= $lancamento['status']=='pendente'?'selected':'' ?>>Pendente</option>
                        <option value="pago" <?= $lancamento['status']=='pago'?'selected':'' ?>>Pago</option>
                        <option value="cancelado" <?= $lancamento['status']=='cancelado'?'selected':'' ?>>Cancelado</option>
                    </select>
                </div>

                <!-- Descrição -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold"><i class="bi bi-card-text me-1"></i>Descrição</label>
                    <input type="text" name="descricao" class="form-control" 
                           value="<?= htmlspecialchars($lancamento['descricao']) ?>" required>
                </div>

                <!-- Valor -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-cash-stack me-1"></i>Valor</label>
                    <input type="number" step="0.01" name="valor" class="form-control" 
                           value="<?= htmlspecialchars($lancamento['valor']) ?>" required>
                </div>

                <!-- Datas -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-calendar-date me-1"></i>Data de Lançamento</label>
                    <input type="date" name="data_lancamento" class="form-control" 
                           value="<?= htmlspecialchars($lancamento['data_lancamento']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-calendar-event me-1"></i>Vencimento</label>
                    <input type="date" name="data_vencimento" class="form-control" 
                           value="<?= htmlspecialchars($lancamento['data_vencimento']) ?>">
                </div>

                <!-- Forma de Pagamento -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-credit-card me-1"></i>Forma de Pagamento</label>
                    <select name="forma_pagamento" id="formaPagamento" class="form-select">
                        <option value="">Selecione</option>
                        <option value="Pix" <?= $lancamento['forma_pagamento']=='Pix'?'selected':'' ?>>Pix</option>
                        <option value="Boleto" <?= $lancamento['forma_pagamento']=='Boleto'?'selected':'' ?>>Boleto</option>
                        <option value="Cartão" <?= $lancamento['forma_pagamento']=='Cartão'?'selected':'' ?>>Cartão</option>
                        <option value="Transferência" <?= $lancamento['forma_pagamento']=='Transferência'?'selected':'' ?>>Transferência</option>
                        <option value="Dinheiro" <?= $lancamento['forma_pagamento']=='Dinheiro'?'selected':'' ?>>Dinheiro</option>
                    </select>
                </div>

                <!-- Campos Dinâmicos (Pix / Boleto / Transferência / NF-e / Comprovante) -->
                <!-- mantém os mesmos blocos do seu código atual -->
                <!-- (não alterei o restante para preservar o layout e scripts) -->

            </div>
        </fieldset>

        <?php if (!$bloqueado): ?>
            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-danger px-4">
                    <i class="bi bi-save me-2"></i>Salvar Lançamento
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
// mantém o mesmo script de exibição dinâmica dos campos
const tipo = document.getElementById('tipo');
const forma = document.getElementById('formaPagamento');
const status = document.getElementById('status');
const pix = document.getElementById('pixFields');
const boleto = document.getElementById('boletoFields');
const transf = document.getElementById('transferenciaFields');
const comprovante = document.getElementById('comprovanteField');

function atualizarCampos() {
    const isDespesa = tipo.value === 'despesa';
    const pagamento = forma.value;
    const pago = status.value === 'pago';
    [pix, boleto, transf].forEach(div => div.classList.add('d-none'));
    if (isDespesa) {
        if (pagamento === 'Pix') pix.classList.remove('d-none');
        if (pagamento === 'Boleto') boleto.classList.remove('d-none');
        if (pagamento === 'Transferência') transf.classList.remove('d-none');
    }
    comprovante.classList.toggle('d-none', !pago);
}
[tipo, forma, status].forEach(el => el && el.addEventListener('change', atualizarCampos));
window.addEventListener('DOMContentLoaded', atualizarCampos);
</script>

<?php endContent(); ?>