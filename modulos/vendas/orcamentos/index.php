<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

// ==========================
// Fuso horário da aplicação
// ==========================
$cfg = $conn->query("SELECT timezone FROM config_sistema LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$timezone = $cfg['timezone'] ?? 'America/Sao_Paulo';

// ==========================
// Filtros
// ==========================
$busca = trim($_GET['busca'] ?? '');
$periodo = trim($_GET['periodo'] ?? '');

$where = [];
$params = [];

if ($busca !== '') {
    $where[] = "cliente_nome LIKE ?";
    $params[] = "%{$busca}%";
}

if ($periodo !== '') {
    $where[] = "DATE_FORMAT(criado_em, '%Y-%m') = ?";
    $params[] = $periodo;
}

$sql = "SELECT * FROM vendas_orcamentos";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY criado_em DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg  = urldecode($_GET['msg'] ?? '');
$ok   = isset($_GET['ok']);
$erro = isset($_GET['erro']);
?>

<div class="container-fluid mt-4">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <h4 class="fw-bold mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>Orçamentos
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="editar.php" class="btn btn-danger">
                <i class="bi bi-plus-circle me-2"></i>Novo Orçamento
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold text-muted small mb-1">Cliente</label>
                <input type="text" name="busca" class="form-control" placeholder="Nome do cliente..."
                       value="<?= htmlspecialchars($busca) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-muted small mb-1">Período</label>
                <input type="month" name="periodo" class="form-control"
                       value="<?= htmlspecialchars($periodo) ?>">
            </div>
            <div class="col-md-4 text-end">
                <button type="submit" class="btn btn-outline-danger px-4">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
                <a href="index.php" class="btn btn-light border ms-2" title="Limpar filtros">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- Alertas -->
    <?php if ($ok && $msg): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($erro && $msg): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => {
            const a = document.getElementById('alertMsg');
            if (a) a.classList.remove('show');
        }, 3500);
    </script>

    <!-- Tabela -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Telefone</th>
                        <th>Validade</th>
                        <th>Total (R$)</th>
                        <th>Desconto</th>
                        <th>Criado em</th>
                        <th width="180">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orcamentos): ?>
                        <?php foreach ($orcamentos as $o):
                            $data = '-';
                            if (!empty($o['criado_em'])) {
                                try {
                                    $dt = new DateTime($o['criado_em']);
                                    $dt->setTimezone(new DateTimeZone($timezone));
                                    $data = $dt->format('d/m/Y H:i');
                                } catch (Exception $e) {
                                    $data = date('d/m/Y H:i', strtotime($o['criado_em']));
                                }
                            }
                            $validade = $o['validade'] ? date('d/m/Y', strtotime($o['validade'])) : '-';
                        ?>
                            <tr>
                                <td class="fw-semibold"><?= $o['id'] ?></td>
                                <td><?= htmlspecialchars($o['cliente_nome']) ?></td>
                                <td><?= htmlspecialchars($o['cliente_telefone']) ?></td>
                                <td><?= $validade ?></td>
                                <td>R$ <?= number_format($o['total'], 2, ',', '.') ?></td>
                                <td><?= $o['desconto'] > 0 ? number_format($o['desconto'], 2, ',', '.') . '%' : '-' ?></td>
                                <td><?= $data ?></td>
                                <td class="d-flex gap-1">
                                    <a href="editar.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="gerar_pdf.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-success" title="Gerar PDF" target="_blank">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>

                                    <!-- NOVO: botão modal “Gerar Venda” -->
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Gerar Venda (em breve)"
                                            data-bs-toggle="modal" data-bs-target="#modalEmBreve">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>

                                    <a href="excluir.php?id=<?= $o['id'] ?>"
                                       onclick="return confirm('Deseja realmente excluir este orçamento?')"
                                       class="btn btn-sm btn-outline-secondary" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>Nenhum orçamento encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL EM BREVE -->
<div class="modal fade" id="modalEmBreve" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-lightning-charge-fill me-2"></i>Em breve: Gerar Venda</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
        <i class="bi bi-cart-plus text-danger display-4 mb-3"></i>
        <h5 class="fw-bold mb-2">Conversão direta de Orçamento em Venda</h5>
        <p class="text-muted mb-3">
          Em breve você poderá transformar um orçamento em venda com apenas <strong>um clique</strong>!<br>
          Estoque, totais e margens serão atualizados automaticamente.
        </p>
        <span class="badge bg-light text-dark border">🚧 Em desenvolvimento</span>
      </div>
      <div class="modal-footer bg-light border-0 d-flex justify-content-center">
        <button type="button" class="btn btn-outline-danger px-4 fw-semibold" data-bs-dismiss="modal">
          Entendido
        </button>
      </div>
    </div>
  </div>
</div>

<?php endContent(); ?>