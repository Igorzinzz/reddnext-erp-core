<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

// Filtros
$busca = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT * FROM clientes WHERE 1=1";
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
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-person-lines-fill me-2"></i>Clientes
        </h4>
        <a href="editar.php" class="btn btn-danger">
            <i class="bi bi-plus-circle me-2"></i>Novo Cliente
        </a>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold text-muted small mb-1">Buscar Cliente</label>
                <input type="text" name="busca" class="form-control" placeholder="Digite o nome..."
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

    <!-- ALERTAS -->
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

    <!-- TABELA -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Documento</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Cidade / UF</th>
                        <th>Status</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td><?= $c['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($c['nome']) ?></td>
                                <td><?= htmlspecialchars($c['documento']) ?></td>
                                <td><?= htmlspecialchars($c['telefone']) ?></td>
                                <td><?= htmlspecialchars($c['email']) ?></td>
                                <td><?= htmlspecialchars($c['cidade']) ?> / <?= htmlspecialchars($c['uf']) ?></td>
                                <td>
                                    <?php if ($c['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="excluir.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                                       title="Desativar" onclick="return confirm('Desativar este cliente?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>Nenhum cliente encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endContent(); ?>