<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $codigo_ean = trim($_POST['codigo_ean'] ?? '');
    $preco_custo = floatval($_POST['preco_custo'] ?? 0);
    $preco_venda = floatval($_POST['preco_venda'] ?? 0);
    $estoque_atual = floatval($_POST['estoque_atual'] ?? 0);
    $estoque_minimo = floatval($_POST['estoque_minimo'] ?? 0);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $remover_imagem = intval($_POST['remover_imagem'] ?? 0);
    $imagem_url = null;

    if (empty($nome)) throw new Exception("O nome do produto é obrigatório.");
    if ($preco_venda <= 0) throw new Exception("O preço de venda deve ser maior que zero.");

    // ==========================
    // Upload e compressão automática
    // ==========================
    if (!empty($_FILES['imagem']['name'])) {
        $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $permitidas)) {
            throw new Exception("Formato de imagem inválido. Use JPG, PNG ou WEBP.");
        }

        $dir = __DIR__ . '/../../../uploads/produtos/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $novo_nome = uniqid('prod_') . '.webp';
        $destino = $dir . $novo_nome;
        $tmp = $_FILES['imagem']['tmp_name'];

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
                throw new Exception("Tipo de imagem não suportado.");
        }

        if (!$img) throw new Exception("Erro ao processar a imagem. Verifique o arquivo enviado.");

        if (!imagewebp($img, $destino, 80)) {
            throw new Exception("Erro ao salvar imagem comprimida.");
        }
        imagedestroy($img);

        $base = str_replace('/modulos', '', rtrim($config['base_url'], '/'));
        $imagem_url = $base . '/uploads/produtos/' . $novo_nome;
    }

    // ==========================
    // Inserção ou atualização
    // ==========================
    if ($id > 0) {
        // Atualização
        if ($remover_imagem === 1) {
            $stmt = $conn->prepare("
                UPDATE vendas_estoque 
                SET nome=?, codigo_ean=?, preco_custo=?, preco_venda=?, estoque_atual=?, estoque_minimo=?, ativo=?, imagem_url=NULL 
                WHERE id=?
            ");
            $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $estoque_atual, $estoque_minimo, $ativo, $id]);
        } elseif ($imagem_url) {
            $stmt = $conn->prepare("
                UPDATE vendas_estoque 
                SET nome=?, codigo_ean=?, preco_custo=?, preco_venda=?, estoque_atual=?, estoque_minimo=?, ativo=?, imagem_url=? 
                WHERE id=?
            ");
            $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $estoque_atual, $estoque_minimo, $ativo, $imagem_url, $id]);
        } else {
            $stmt = $conn->prepare("
                UPDATE vendas_estoque 
                SET nome=?, codigo_ean=?, preco_custo=?, preco_venda=?, estoque_atual=?, estoque_minimo=?, ativo=? 
                WHERE id=?
            ");
            $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $estoque_atual, $estoque_minimo, $ativo, $id]);
        }
    } else {
        // Inserção
        $stmt = $conn->prepare("
            INSERT INTO vendas_estoque 
            (nome, codigo_ean, preco_custo, preco_venda, estoque_atual, estoque_minimo, ativo, imagem_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $estoque_atual, $estoque_minimo, $ativo, $imagem_url]);
    }

    header("Location: index.php?ok=1&msg=" . urlencode("Produto salvo com sucesso!"));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>