<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$stmt = $conn->query("SELECT * FROM financeiro_categorias ORDER BY ativo DESC, nome ASC");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-layers me-2"></i>Categorias Financeiras</h4>
        <a href="editar.php" class="btn btn-danger"><i class="bi bi-plus-circle me-2"></i>Nova Categoria</a>
    </div>

    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_GET['msg'] ?? 'Operação concluída!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_GET['msg'] ?? 'Erro na operação.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => {
            const alert = document.getElementById('alertMsg');
            if (alert) alert.classList.remove('show');
        }, 3000);
    </script>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Descrição</th>
                        <th>Status</th>
                        <th width="130">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorias as $c): ?>
                        <tr class="<?= !$c['ativo'] ? 'table-light' : '' ?>">
                            <td><?= $c['id'] ?></td>
                            <td><?= htmlspecialchars($c['nome']) ?></td>
                            <td><?= htmlspecialchars($c['descricao'] ?? '-') ?></td>
                            <td>
                                <?php if ($c['ativo']): ?>
                                    <span class="badge bg-success">Ativa</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($c['ativo']): ?>
                                        <a href="excluir.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Desativar esta categoria?')" title="Desativar">
                                            <i class="bi bi-person-dash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="ativar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Ativar esta categoria?')" title="Ativar">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endContent(); ?>