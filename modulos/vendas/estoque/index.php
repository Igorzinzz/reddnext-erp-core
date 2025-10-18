<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

// Filtros
$busca = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT * FROM vendas_estoque WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND nome LIKE ?";
    $params[] = "%{$busca}%";
}
if ($status !== '') {
    $sql .= " AND ativo = ?";
    $params[] = ($status === 'ativo') ? 1 : 0;
}

$sql .= " ORDER BY ativo DESC, nome ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-box-seam me-2"></i>Estoque
        </h4>
        <a href="editar.php" class="btn btn-danger">
            <i class="bi bi-plus-circle me-2"></i>Novo Produto
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold text-muted small mb-1">Buscar Produto</label>
                <input type="text" name="busca" class="form-control" placeholder="Digite o nome do produto..."
                       value="<?= htmlspecialchars($busca) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-muted small mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                    <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>
            <div class="col-md-4 text-end">
                <button type="submit" class="btn btn-outline-danger px-4">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
                <a href="index.php" class="btn btn-light border ms-2">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- Alertas -->
    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['msg'] ?? 'Operação concluída com sucesso!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['msg'] ?? 'Erro ao processar.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => {
            const a = document.getElementById('alertMsg');
            if (a) a.classList.remove('show');
        }, 3000);
    </script>

    <!-- Tabela -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Produto</th>
                        <th>Preço Custo</th>
                        <th>Preço Venda</th>
                        <th>Lucro Unit.</th>
                        <th>Estoque Atual</th>
                        <th>Estoque Mínimo</th>
                        <th>Status</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($produtos) > 0): ?>
                        <?php foreach ($produtos as $p): 
                            $lucro = $p['preco_venda'] - $p['preco_custo'];
                            $baixo = $p['estoque_minimo'] > 0 && $p['estoque_atual'] < $p['estoque_minimo'];
                        ?>
                            <tr class="<?= $baixo ? 'table-warning' : '' ?>">
                                <td><?= $p['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($p['nome']) ?></td>
                                <td>R$ <?= number_format($p['preco_custo'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
                                <td>
                                    <?php if ($lucro >= 0): ?>
                                        <span class="text-success fw-semibold">+R$ <?= number_format($lucro, 2, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="text-danger fw-semibold">R$ <?= number_format($lucro, 2, ',', '.') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= number_format($p['estoque_atual'], 2, ',', '.') ?>
                                    <?php if ($baixo): ?>
                                        <span class="badge bg-warning text-dark ms-2">Baixo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($p['estoque_minimo'], 2, ',', '.') ?></td>
                                <td>
                                    <?php if ($p['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="excluir.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger"
                                       title="Desativar" onclick="return confirm('Desativar este produto?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>Nenhum produto encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endContent(); ?>