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

    // --- NOVO: valida e padroniza nível e ativo ---
    if (!in_array($nivel, ['admin', 'gestor', 'operador'])) {
        $nivel = 'operador';
    }
    if ($ativo !== 0 && $ativo !== 1) {
        $ativo = 1;
    }

    if ($id > 0) {
        // UPDATE existente
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
        // INSERT novo usuário
        if (!$senha) {
            throw new Exception('Senha é obrigatória para novos usuários.');
        }

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO usuarios 
            (nome, email, senha, nivel, ativo, criado_em) 
            VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo]);
    }

    header("Location: index.php?ok=1&msg=Usuário+salvo+com+sucesso");
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}