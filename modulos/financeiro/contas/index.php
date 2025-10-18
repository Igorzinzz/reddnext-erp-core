<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

$stmt = $conn->query("SELECT * FROM financeiro_contas ORDER BY ativo DESC, nome_exibicao ASC");
$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-bank2 me-2"></i>Contas Bancárias</h4>
        <a href="editar.php" class="btn btn-danger"><i class="bi bi-plus-circle me-2"></i>Nova Conta</a>
    </div>

    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm mt-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Operação concluída com sucesso!') ?>
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

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Banco</th>
                        <th>Nome / Conta</th>
                        <th>Agência</th>
                        <th>Saldo Inicial</th>
                        <th>Status</th>
                        <th width="130">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contas as $c): ?>
                        <tr class="<?= !$c['ativo'] ? 'table-light' : '' ?>">
                            <td><?= $c['id'] ?></td>
                            <td><?= htmlspecialchars($c['banco']) ?></td>
                            <td>
                                <?= htmlspecialchars($c['nome_exibicao']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($c['numero_conta']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($c['agencia']) ?></td>
                            <td>R$ <?= number_format($c['saldo_inicial'], 2, ',', '.') ?></td>
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
                                        <a href="excluir.php?id=<?= $c['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Deseja desativar esta conta?')"
                                           title="Desativar">
                                           <i class="bi bi-person-dash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="ativar.php?id=<?= $c['id'] ?>" 
                                           class="btn btn-sm btn-outline-success"
                                           onclick="return confirm('Deseja reativar esta conta?')"
                                           title="Ativar">
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