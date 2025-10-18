<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

if ($_SESSION['usuario']['nivel'] !== 'admin') {
    echo "<div class='alert alert-danger mt-4'>Acesso negado.</div>";
    endContent(); exit;
}

$stmt = $conn->query("SELECT * FROM usuarios ORDER BY id ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-people me-2"></i>Usuários</h4>

    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm mt-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Ação concluída com sucesso!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm mt-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Ocorreu um erro.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => {
            const alert = document.getElementById('alertMsg');
            if (alert) alert.classList.remove('show');
        }, 3000);
    </script>

    <div class="text-end mb-3">
        <a href="editar.php" class="btn btn-danger"><i class="bi bi-plus-circle me-2"></i>Novo Usuário</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th width="140">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['nome']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($u['nivel']) ?></span></td>
                            <td>
                                <?php if ($u['ativo']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="editar.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if ($u['ativo']): ?>
                                    <a href="excluir.php?id=<?= $u['id'] ?>&acao=desativar"
                                       class="btn btn-sm btn-outline-warning"
                                       onclick="return confirm('Deseja desativar este usuário?')">
                                       <i class="bi bi-person-x"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="excluir.php?id=<?= $u['id'] ?>&acao=excluir"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Excluir permanentemente este usuário? Esta ação não pode ser desfeita!')">
                                       <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endContent(); ?>