<?php
include '../../core/init.php';
include '../../core/auth.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido. A requisição deve ser POST.');
    }

    if (empty($_POST)) {
        throw new Exception('Nenhum dado foi enviado pelo formulário.');
    }

    // --- Verifica se há registro existente ---
    $stmt = $conn->query("SELECT id FROM config_sistema LIMIT 1");
    $id = $stmt->fetchColumn();

    if (!$id) {
        $conn->exec("INSERT INTO config_sistema (nome_empresa) VALUES ('Nova Empresa')");
        $id = $conn->lastInsertId();
    }

    // --- Upload da logo ---
    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $permitidos = ['png', 'jpg', 'jpeg', 'webp'];

        if (!in_array($ext, $permitidos)) {
            throw new Exception('Formato de imagem não permitido.');
        }

        $pastaDestino = __DIR__ . '/logo/';
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0775, true);
        }

        $logo = 'logo_' . time() . '.' . $ext;
        $destino = $pastaDestino . $logo;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $destino)) {
            throw new Exception('Falha ao salvar a logo.');
        }
    }

    // --- Monta SQL de atualização ---
    $sql = "UPDATE config_sistema SET 
                nome_empresa = :nome_empresa,
                cnpj = :cnpj,
                telefone = :telefone,
                email = :email,
                endereco = :endereco,
                cidade = :cidade,
                uf = :uf,
                timezone = :timezone,
                tema = :tema,
                atualizado_em = NOW()";

    if ($logo) {
        $sql .= ", logo = :logo";
    }

    $sql .= " WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $params = [
        ':nome_empresa' => $_POST['nome_empresa'] ?? '',
        ':cnpj'         => $_POST['cnpj'] ?? '',
        ':telefone'     => $_POST['telefone'] ?? '',
        ':email'        => $_POST['email'] ?? '',
        ':endereco'     => $_POST['endereco'] ?? '',
        ':cidade'       => $_POST['cidade'] ?? '',
        ':uf'           => $_POST['uf'] ?? '',
        ':timezone'     => $_POST['timezone'] ?? 'America/Sao_Paulo',
        ':tema'         => $_POST['tema'] ?? 'claro',
        ':id'           => $id
    ];

    if ($logo) {
        $params[':logo'] = $logo;
    }

    $stmt->execute($params);

    // --- Se chegar aqui, deu tudo certo ---
    header("Location: index.php?ok=1&msg=Configurações+salvas+com+sucesso");
    exit;

} catch (Exception $e) {
    // Redireciona com erro amigável
    $msg = urlencode($e->getMessage());
    header("Location: index.php?erro=1&msg={$msg}");
    exit;
}