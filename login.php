<?php
include __DIR__ . '/core/init.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($senha, $user['senha'])) {
            // Login OK
            $_SESSION['usuario'] = [
                'id'    => $user['id'],
                'nome'  => $user['nome'],
                'nivel' => $user['nivel'],
                'email' => $user['email'],
                'ativo' => $user['ativo']
            ];
            header('Location: modulos/dashboard/');
            exit;
        } else {
            $erro = "Usuário ou senha inválidos.";
        }
    } catch (Exception $e) {
        $erro = "Erro ao conectar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
    <div class="card p-4 shadow-sm text-center" style="max-width: 380px; width: 100%;">
        
        <!-- 🔹 Logo padrão -->
        <div class="mb-3">
            <img src="/assets/img/logo.png" 
                 alt="Logo do Sistema" 
                 style="max-width:150px; height:auto;">
        </div>

        <?php if (!empty($erro)): ?>
            <p class="text-danger"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>

        <form method="POST" autocomplete="off" class="text-start">
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label>Senha</label>
                <input type="password" name="senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-danger w-100">Entrar</button>
        </form>
    </div>
</body>
</html>