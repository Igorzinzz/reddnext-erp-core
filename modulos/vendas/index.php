<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';

/**
 * ----------------------------
 *  Montagem de Filtros (Base)
 * ----------------------------
 * - Mantemos total compatibilidade com os filtros existentes:
 *   cliente (LIKE), periodo (YYYY-MM), status (=)
 * - Usamos dois conjuntos:
 *   - Base (cliente + periodo): para cards/indicadores
 *   - Full (cliente + periodo + status): para a listagem/export
 */

$whereBase = [];
$paramsBase = [];

if (!empty($_GET['cliente'])) {
    $whereBase[] = "c.nome LIKE ?";
    $paramsBase[] = "%" . $_GET['cliente'] . "%";
}

if (!empty($_GET['periodo'])) {
    $whereBase[] = "DATE_FORMAT(v.data_venda, '%Y-%m') = ?";
    $paramsBase[] = $_GET['periodo']; // yyyy-mm
}

$whereFull = $whereBase;
$paramsFull = $paramsBase;

if (!empty($_GET['status'])) {
    $whereFull[] = "v.status = ?";
    $paramsFull[] = $_GET['status'];
}

$whereSQLBase = $whereBase ? "WHERE " . implode(" AND ", $whereBase) : "";
$whereSQLFull = $whereFull ? "WHERE " . implode(" AND ", $whereFull) : "";

/**
 * -----------------------------------
 *  Exportação CSV (respeita filtros)
 * -----------------------------------
 */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtExp = $conn->prepare("
        SELECT v.id, v.data_venda, c.nome AS cliente, v.valor_total, v.forma_pagamento, v.status
        FROM vendas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        $whereSQLFull
        ORDER BY v.data_venda DESC
    ");
    $stmtExp->execute($paramsFull);
    $rows = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    // Cabeçalhos para download
    $filename = "vendas_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    // BOM para Excel
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    // Cabeçalho
    fputcsv($out, ['ID', 'Data', 'Cliente', 'Valor Total', 'Forma Pagamento', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            date('d/m/Y', strtotime($r['data_venda'])),
            $r['cliente'] ?: 'Sem cliente',
            number_format((float)$r['valor_total'], 2, ',', '.'),
            $r['forma_pagamento'] ?: '—',
            ucfirst($r['status'] ?: 'Desconhecido'),
        ]);
    }
    fclose($out);
    exit;
}

/**
 * --------------------------------------
 *  Indicadores (cards) - visão gerencial
 * --------------------------------------
 * - Totais e ticket médio sobre o conjunto base (cliente + periodo)
 * - Pagos/Pendentes sempre respeitando os filtros base (e status específicos)
 */

// Total de vendas (soma e quantidade) – base
$stmtTotal = $conn->prepare("
    SELECT 
        COUNT(*) AS qtd,
        COALESCE(SUM(v.valor_total), 0) AS total
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    $whereSQLBase
");
$stmtTotal->execute($paramsBase);
$totais = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$qtdTotal = (int)($totais['qtd'] ?? 0);
$valorTotal = (float)($totais['total'] ?? 0);

// Total recebido (pago) – base + status = pago
$wherePago = $whereBase;
$paramsPago = $paramsBase;
$wherePago[] = "v.status = 'pago'";
$whereSQLPago = "WHERE " . implode(" AND ", $wherePago);

$stmtPago = $conn->prepare("
    SELECT COALESCE(SUM(v.valor_total), 0) AS total 
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    $whereSQLPago
");
$stmtPago->execute($paramsPago);
$valorPago = (float)($stmtPago->fetchColumn() ?: 0);

// Total pendente – base + status = pendente
$wherePend = $whereBase;
$paramsPend = $paramsBase;
$wherePend[] = "v.status = 'pendente'";
$whereSQLPend = "WHERE " . implode(" AND ", $wherePend);

$stmtPend = $conn->prepare("
    SELECT COALESCE(SUM(v.valor_total), 0) AS total 
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    $whereSQLPend
");
$stmtPend->execute($paramsPend);
$valorPendente = (float)($stmtPend->fetchColumn() ?: 0);

// Ticket médio – base (desconsidera canceladas)
$whereTicket = $whereBase;
$paramsTicket = $paramsBase;
$whereTicket[] = "v.status <> 'cancelada'";
$whereSQLTicket = "WHERE " . implode(" AND ", $whereTicket);

$stmtTicket = $conn->prepare("
    SELECT AVG(v.valor_total) AS ticket 
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    $whereSQLTicket
");
$stmtTicket->execute($paramsTicket);
$ticketMedio = (float)($stmtTicket->fetchColumn() ?: 0);

/**
 * --------------------------
 *  Buscar vendas (listagem)
 * --------------------------
 */
$stmt = $conn->prepare("
    SELECT v.*, c.nome AS cliente_nome
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    $whereSQLFull
    ORDER BY v.data_venda DESC
");
$stmt->execute($paramsFull);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

startContent();
?>

<div class="container-fluid mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-cart3 me-2"></i>Vendas</h4>
        <div class="d-flex gap-2">
            <a href="editar.php" class="btn btn-danger">
                <i class="bi bi-plus-circle me-2"></i>Nova Venda
            </a>
            <?php
                $qs = $_GET;
                $qs['export'] = 'csv';
                $csvUrl = '?' . http_build_query($qs);
            ?>
            <a href="<?= htmlspecialchars($csvUrl) ?>" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Exportar CSV (respeita os filtros)">
                <i class="bi bi-download me-2"></i>Exportar CSV
            </a>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Venda salva com sucesso!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Erro ao processar a venda.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cards Resumo -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Total de Vendas</span>
                        <i class="bi bi-graph-up-arrow opacity-50"></i>
                    </div>
                    <div class="mt-2 h5 mb-1">R$ <?= number_format($valorTotal, 2, ',', '.') ?></div>
                    <div class="text-muted small"><?= $qtdTotal ?> registro(s)</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Recebido (Pago)</span>
                        <i class="bi bi-check2-circle opacity-50"></i>
                    </div>
                    <div class="mt-2 h5 mb-1">R$ <?= number_format($valorPago, 2, ',', '.') ?></div>
                    <div class="text-success small d-flex align-items-center">
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill me-2">Pago</span>
                        dentro dos filtros
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Em Aberto (Pendente)</span>
                        <i class="bi bi-hourglass-split opacity-50"></i>
                    </div>
                    <div class="mt-2 h5 mb-1">R$ <?= number_format($valorPendente, 2, ',', '.') ?></div>
                    <div class="text-warning small d-flex align-items-center">
                        <span class="badge bg-warning text-dark rounded-pill me-2">Pendente</span>
                        dentro dos filtros
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Ticket Médio</span>
                        <i class="bi bi-cash-stack opacity-50"></i>
                    </div>
                    <div class="mt-2 h5 mb-1">R$ <?= number_format($ticketMedio, 2, ',', '.') ?></div>
                    <div class="text-muted small">Exclui canceladas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Cliente</label>
                    <input type="text" name="cliente" value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>"
                           class="form-control" placeholder="Buscar cliente...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Período</label>
                    <input type="month" name="periodo" value="<?= htmlspecialchars($_GET['periodo'] ?? '') ?>"
                           class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente" <?= ($_GET['status'] ?? '') === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= ($_GET['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="cancelada" <?= ($_GET['status'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-danger">
                        <i class="bi bi-funnel me-2"></i>Filtrar
                    </button>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 justify-content-between">
                    <div class="d-flex flex-wrap gap-2">
                        <?php
                            // Chips rápidos de status (preservando cliente/periodo)
                            $baseQS = $_GET;
                            unset($baseQS['status'], $baseQS['export']);
                            $urlTodos = '?' . http_build_query($baseQS);
                            $qsPago = $baseQS; $qsPago['status'] = 'pago';
                            $qsPend = $baseQS; $qsPend['status'] = 'pendente';
                            $qsCanc = $baseQS; $qsCanc['status'] = 'cancelada';
                        ?>
                        <a href="<?= htmlspecialchars($urlTodos) ?>" class="btn btn-sm <?= empty($_GET['status']) ? 'btn-danger' : 'btn-outline-secondary' ?>">Todos</a>
                        <a href="?<?= htmlspecialchars(http_build_query($qsPago)) ?>" class="btn btn-sm <?= (($_GET['status'] ?? '') === 'pago') ? 'btn-danger' : 'btn-outline-secondary' ?>">Pagos</a>
                        <a href="?<?= htmlspecialchars(http_build_query($qsPend)) ?>" class="btn btn-sm <?= (($_GET['status'] ?? '') === 'pendente') ? 'btn-danger' : 'btn-outline-secondary' ?>">Pendentes</a>
                        <a href="?<?= htmlspecialchars(http_build_query($qsCanc)) ?>" class="btn btn-sm <?= (($_GET['status'] ?? '') === 'cancelada') ? 'btn-danger' : 'btn-outline-secondary' ?>">Canceladas</a>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="vendas.php" class="btn btn-light border" data-bs-toggle="tooltip" title="Limpar todos os filtros">
                            <i class="bi bi-eraser me-2"></i>Limpar filtros
                        </a>
                        <a href="<?= htmlspecialchars($csvUrl) ?>" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Exportar CSV (respeita os filtros)">
                            <i class="bi bi-download me-2"></i>Exportar CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Vendas -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">ID</th>
                            <th class="text-nowrap">Data</th>
                            <th>Cliente</th>
                            <th class="text-end text-nowrap">Valor Total</th>
                            <th class="text-nowrap">Forma</th>
                            <th class="text-nowrap">Status</th>
                            <th width="140" class="text-nowrap">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vendas) > 0): ?>
                            <?php foreach ($vendas as $v): ?>
                                <?php
                                    $status = $v['status'] ?? '';
                                    $badge = '<span class="badge bg-light text-dark"><i class="bi bi-question-circle me-1"></i>Desconhecido</span>';
                                    if ($status === 'pendente') {
                                        $badge = '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>';
                                    } elseif ($status === 'pago') {
                                        $badge = '<span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Pago</span>';
                                    } elseif ($status === 'cancelada') {
                                        $badge = '<span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Cancelada</span>';
                                    }
                                ?>
                                <tr>
                                    <td class="text-nowrap">#<?= (int)$v['id'] ?></td>
                                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($v['data_venda'])) ?></td>
                                    <td><?= htmlspecialchars($v['cliente_nome'] ?: 'Sem cliente') ?></td>
                                    <td class="text-end text-nowrap">R$ <?= number_format((float)$v['valor_total'], 2, ',', '.') ?></td>
                                    <td class="text-nowrap"><?= htmlspecialchars($v['forma_pagamento'] ?: '—') ?></td>
                                    <td class="text-nowrap"><?= $badge ?></td>
                                    <td class="text-nowrap">
                                        <!-- Visualizar (modal) -->
                                        <button 
                                            class="btn btn-sm btn-outline-secondary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDetalhes<?= (int)$v['id'] ?>"
                                            data-bs-toggle-tooltip
                                            title="Visualizar detalhes">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Editar -->
                                        <a href="editar.php?id=<?= (int)$v['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary me-1" 
                                           data-bs-toggle="tooltip" title="Editar venda">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Excluir -->
                                        <a href="excluir.php?id=<?= (int)$v['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Excluir esta venda?')"
                                           data-bs-toggle="tooltip" title="Excluir venda">
                                           <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Modal Detalhes -->
                                <div class="modal fade" id="modalDetalhes<?= (int)$v['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Venda #<?= (int)$v['id'] ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="d-flex justify-content-between">
                                                    <div class="text-muted small">Data</div>
                                                    <div class="fw-semibold"><?= date('d/m/Y', strtotime($v['data_venda'])) ?></div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between">
                                                    <div class="text-muted small">Cliente</div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($v['cliente_nome'] ?: 'Sem cliente') ?></div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between">
                                                    <div class="text-muted small">Forma de Pagamento</div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($v['forma_pagamento'] ?: '—') ?></div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between">
                                                    <div class="text-muted small">Status</div>
                                                    <div class="fw-semibold"><?= strip_tags($badge) ?></div>
                                                </div>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between">
                                                    <div class="text-muted small">Valor Total</div>
                                                    <div class="fw-bold">R$ <?= number_format((float)$v['valor_total'], 2, ',', '.') ?></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="editar.php?id=<?= (int)$v['id'] ?>" class="btn btn-primary">
                                                    <i class="bi bi-pencil me-2"></i>Editar
                                                </a>
                                                <button class="btn btn-light border" data-bs-dismiss="modal">Fechar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-info-circle me-2"></i>Nenhuma venda encontrada.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-hide alertas
setTimeout(() => {
    const alert = document.getElementById('alertMsg');
    if (alert) alert.classList.remove('show');
}, 3000);

// Tooltips
document.addEventListener('DOMContentLoaded', () => {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
});
</script>

<?php endContent(); ?>