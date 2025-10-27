<?php
include '../../core/init.php';
include '../../core/auth.php';

try {
    if ($_SESSION['usuario']['nivel'] !== 'admin') {
        throw new Exception('Acesso negado.');
    }

    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nivel = $_POST['nivel'] ?? 'operador';
    $ativo = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;

    if (!$nome || !$email) {
        throw new Exception('Nome e e-mail são obrigatórios.');
    }

    // --- Validação e padronização de campos ---
    if (!in_array($nivel, ['admin', 'gestor', 'operador'])) {
        $nivel = 'operador';
    }
    if ($ativo !== 0 && $ativo !== 1) {
        $ativo = 1;
    }

    // ==========================
    // SEGURANÇAS ADICIONAIS
    // ==========================

    // Impede que o admin logado se desative ou rebaixe
    if ($id == $_SESSION['usuario']['id']) {
        if ($ativo == 0) {
            throw new Exception('Você não pode se desativar.');
        }
        if ($nivel !== $_SESSION['usuario']['nivel']) {
            throw new Exception('Você não pode alterar seu próprio nível de acesso.');
        }
    }

    // Impede desativar o último administrador ativo
    $totalAdmins = $conn->query("SELECT COUNT(*) FROM usuarios WHERE nivel='admin' AND ativo=1")->fetchColumn();
    if ($totalAdmins <= 1) {
        $stmtCheck = $conn->prepare("SELECT nivel, ativo FROM usuarios WHERE id=?");
        $stmtCheck->execute([$id]);
        $dadosAntigos = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($dadosAntigos && $dadosAntigos['nivel'] === 'admin' && $ativo == 0) {
            throw new Exception('Não é possível desativar o último administrador ativo do sistema.');
        }
        if ($dadosAntigos && $dadosAntigos['nivel'] === 'admin' && $nivel !== 'admin') {
            throw new Exception('Não é possível rebaixar o último administrador ativo do sistema.');
        }
    }

    // ==========================
    // INSERÇÃO / ATUALIZAÇÃO
    // ==========================
    if ($id > 0) {
        // Atualização
        if ($senha) {
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE usuarios 
                SET nome=?, email=?, senha=?, nivel=?, ativo=? 
                WHERE id=?");
            $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios 
                SET nome=?, email=?, nivel=?, ativo=? 
                WHERE id=?");
            $stmt->execute([$nome, $email, $nivel, $ativo, $id]);
        }
    } else {
        // Novo usuário
        if (!$senha) {
            throw new Exception('Senha é obrigatória para novos usuários.');
        }

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO usuarios 
            (nome, email, senha, nivel, ativo, criado_em) 
            VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo]);
    }

    header("Location: index.php?ok=1&msg=" . urlencode("Usuário salvo com sucesso!"));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>