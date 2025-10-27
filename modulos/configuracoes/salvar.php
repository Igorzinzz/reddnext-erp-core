<?php
include '../../core/init.php';
include '../../core/auth.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido. A requisição deve ser POST.');
    }

    // ==============================
    // Verifica ou cria o registro base
    // ==============================
    $stmt = $conn->query("SELECT id, logo FROM config_sistema LIMIT 1");
    $configAtual = $stmt->fetch(PDO::FETCH_ASSOC);
    $id = $configAtual['id'] ?? null;
    $logoAtual = $configAtual['logo'] ?? null;

    if (!$id) {
        $conn->exec("INSERT INTO config_sistema (nome_empresa) VALUES ('Nova Empresa')");
        $id = $conn->lastInsertId();
    }

    // ==============================
    // Normaliza e valida margem padrão
    // ==============================
    $margem_padrao = floatval($_POST['margem_padrao'] ?? 30);
    if ($margem_padrao < 0) $margem_padrao = 0;
    if ($margem_padrao > 999) $margem_padrao = 999;

    // ==============================
    // Upload e compressão automática da logo
    // ==============================
    $logoPath = null;
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $permitidos = ['png', 'jpg', 'jpeg', 'webp'];

        if (!in_array($ext, $permitidos)) {
            throw new Exception('Formato de imagem não permitido. Use PNG, JPG ou WEBP.');
        }

        $pastaDestino = __DIR__ . '/logo/';
        if (!is_dir($pastaDestino)) {
            mkdir($pastaDestino, 0775, true);
        }

        $novoNome = 'logo_' . date('Ymd_His') . '.webp';
        $destino = $pastaDestino . $novoNome;
        $tmp = $_FILES['logo']['tmp_name'];

        // Converte e comprime para WEBP
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $img = @imagecreatefromjpeg($tmp);
                break;
            case 'png':
                $img = @imagecreatefrompng($tmp);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                break;
            case 'webp':
                $img = @imagecreatefromwebp($tmp);
                break;
            default:
                $img = false;
        }

        if (!$img) {
            throw new Exception('Erro ao processar a imagem da logo.');
        }

        if (!imagewebp($img, $destino, 80)) {
            throw new Exception('Erro ao salvar logo comprimida.');
        }
        imagedestroy($img);

        // Caminho relativo
        $logoPath = 'logo/' . $novoNome;

        // Remove logo antiga se existir
        if (!empty($logoAtual) && file_exists(__DIR__ . '/' . $logoAtual)) {
            @unlink(__DIR__ . '/' . $logoAtual);
        }
    }

    // ==============================
    // Remoção manual da logo (checkbox)
    // ==============================
    if (isset($_POST['remover_logo']) && $_POST['remover_logo'] == '1') {
        if (!empty($logoAtual) && file_exists(__DIR__ . '/' . $logoAtual)) {
            @unlink(__DIR__ . '/' . $logoAtual);
        }
        $logoPath = ''; // será gravado NULL no banco
    }

    // ==============================
    // Atualiza as informações
    // ==============================
    $sql = "UPDATE config_sistema SET 
                nome_empresa   = :nome_empresa,
                cnpj           = :cnpj,
                telefone       = :telefone,
                email          = :email,
                endereco       = :endereco,
                cidade         = :cidade,
                uf             = :uf,
                timezone       = :timezone,
                margem_padrao  = :margem_padrao,
                atualizado_em  = NOW()";

    if ($logoPath !== null) {
        // Atualiza se nova logo ou se removida
        $sql .= ", logo = :logo";
    }

    $sql .= " WHERE id = :id";

    $stmt = $conn->prepare($sql);

    $params = [
        ':nome_empresa'  => trim($_POST['nome_empresa'] ?? ''),
        ':cnpj'          => trim($_POST['cnpj'] ?? ''),
        ':telefone'      => trim($_POST['telefone'] ?? ''),
        ':email'         => trim($_POST['email'] ?? ''),
        ':endereco'      => trim($_POST['endereco'] ?? ''),
        ':cidade'        => trim($_POST['cidade'] ?? ''),
        ':uf'            => strtoupper(trim($_POST['uf'] ?? '')),
        ':timezone'      => trim($_POST['timezone'] ?? 'America/Sao_Paulo'),
        ':margem_padrao' => $margem_padrao,
        ':id'            => $id
    ];

    if ($logoPath !== null) {
        $params[':logo'] = $logoPath ?: null;
    }

    $stmt->execute($params);

    // ==============================
    // Redireciona com sucesso
    // ==============================
    header("Location: index.php?ok=1&msg=" . urlencode('Configurações salvas com sucesso!'));
    exit;

} catch (Exception $e) {
    $msg = urlencode($e->getMessage());
    header("Location: index.php?erro=1&msg={$msg}");
    exit;
}
?>