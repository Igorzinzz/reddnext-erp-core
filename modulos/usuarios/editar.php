<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

if ($_SESSION['usuario']['nivel'] !== 'admin') {
    echo "<div class='alert alert-danger mt-4'>Acesso negado.</div>";
    endContent(); exit;
}

$id = intval($_GET['id'] ?? 0);
$usuario = [
    'nome' => '',
    'email' => '',
    'nivel' => 'operador',
    'ativo' => 1
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <h4 class="fw-bold mb-4">
        <i class="bi bi-person-fill-gear me-2"></i>
        <?= $id ? 'Editar Usuário' : 'Novo Usuário' ?>
    </h4>

    <form action="salvar.php" method="POST" class="card shadow-sm bg-white p-4">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($usuario['nome']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($usuario['email']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" placeholder="••••••">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nível</label>
                <select name="nivel" class="form-select">
                    <option value="admin" <?= $usuario['nivel'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    <option value="gestor" <?= $usuario['nivel'] === 'gestor' ? 'selected' : '' ?>>Gestor</option>
                    <option value="operador" <?= $usuario['nivel'] === 'operador' ? 'selected' : '' ?>>Operador</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Ativo</label>
                <select name="ativo" class="form-select">
                    <option value="1" <?= $usuario['ativo'] ? 'selected' : '' ?>>Sim</option>
                    <option value="0" <?= !$usuario['ativo'] ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
        </div>

        <div class="mt-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-danger px-4" type="submit"><i class="bi bi-save me-2"></i>Salvar</button>
        </div>
    </form>
</div>

<?php endContent(); ?>