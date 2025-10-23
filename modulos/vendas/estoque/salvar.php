<?php
include '../../../core/init.php';
include '../../../core/auth.php';

try {
    $id = intval($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $codigo_ean = trim($_POST['codigo_ean'] ?? '');
    $preco_custo = floatval($_POST['preco_custo'] ?? 0);
    $preco_venda = floatval($_POST['preco_venda'] ?? 0);
    $margem_padrao = floatval($_POST['margem_padrao'] ?? 30);
    $tipo_unidade = strtoupper(trim($_POST['tipo_unidade'] ?? 'UN'));
    $peso_variavel = isset($_POST['peso_variavel']) ? 1 : 0;
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $remover_imagem = intval($_POST['remover_imagem'] ?? 0);
    $imagem_url = null;

    // ==========================
    // Validação básica
    // ==========================
    if (empty($nome)) throw new Exception("O nome do produto é obrigatório.");
    if ($preco_venda <= 0) throw new Exception("O preço de venda deve ser maior que zero.");
    if (!in_array($tipo_unidade, ['UN', 'KG'])) $tipo_unidade = 'UN';
    if ($margem_padrao < 0) $margem_padrao = 0;

    // Estoques com tratamento de unidade
    $estoque_atual = floatval($_POST['estoque_atual'] ?? 0);
    $estoque_minimo = floatval($_POST['estoque_minimo'] ?? 0);

    if ($tipo_unidade === 'KG') {
        $estoque_atual = round($estoque_atual, 3);
        $estoque_minimo = round($estoque_minimo, 3);
    } else {
        if (fmod($estoque_atual, 1) != 0 || fmod($estoque_minimo, 1) != 0) {
            throw new Exception("Produtos em unidade (UN) não podem ter estoque fracionado.");
        }
        $estoque_atual = intval($estoque_atual);
        $estoque_minimo = intval($estoque_minimo);
    }

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
    // LIMPEZA AUTOMÁTICA DE PREÇO SUGERIDO
    // ==========================
    if ($id > 0) {
        $stmtCheck = $conn->prepare("SELECT preco_sugerido, preco_venda FROM vendas_estoque WHERE id = ?");
        $stmtCheck->execute([$id]);
        $dados_antigos = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($dados_antigos && $dados_antigos['preco_sugerido'] !== null) {
            $dif = abs(floatval($preco_venda) - floatval($dados_antigos['preco_sugerido']));
            // Se o preço foi alterado manualmente, limpar preco_sugerido
            if ($dif > 0.009) {
                $conn->prepare("UPDATE vendas_estoque SET preco_sugerido = NULL WHERE id = ?")->execute([$id]);
            }
        }
    }

    // ==========================
    // Inserção ou atualização
    // ==========================
    if ($id > 0) {
        // Atualização
        if ($remover_imagem === 1) {
            $stmt = $conn->prepare("
                UPDATE vendas_estoque 
                SET nome=?, codigo_ean=?, preco_custo=?, preco_venda=?, margem_padrao=?, 
                    estoque_atual=?, estoque_minimo=?, tipo_unidade=?, peso_variavel=?, ativo=?, imagem_url=NULL 
                WHERE id=?
            ");
            $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $margem_padrao,
                            $estoque_atual, $estoque_minimo, $tipo_unidade, $peso_variavel, $ativo, $id]);
        } elseif ($imagem_url) {
            $stmt = $conn->prepare("
                UPDATE vendas_estoque 
                SET nome=?, codigo_ean=?, preco_custo=?, preco_venda=?, margem_padrao=?, 
                    estoque_atual=?, estoque_minimo=?, tipo_unidade=?, peso_variavel=?, ativo=?, imagem_url=? 
                WHERE id=?
            ");
            $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $margem_padrao,
                            $estoque_atual, $estoque_minimo, $tipo_unidade, $peso_variavel, $ativo, $imagem_url, $id]);
        } else {
            $stmt = $conn->prepare("
                UPDATE vendas_estoque 
                SET nome=?, codigo_ean=?, preco_custo=?, preco_venda=?, margem_padrao=?, 
                    estoque_atual=?, estoque_minimo=?, tipo_unidade=?, peso_variavel=?, ativo=? 
                WHERE id=?
            ");
            $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $margem_padrao,
                            $estoque_atual, $estoque_minimo, $tipo_unidade, $peso_variavel, $ativo, $id]);
        }
    } else {
        // Inserção
        $stmt = $conn->prepare("
            INSERT INTO vendas_estoque 
            (nome, codigo_ean, preco_custo, preco_venda, margem_padrao, estoque_atual, estoque_minimo, 
             tipo_unidade, peso_variavel, ativo, imagem_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $codigo_ean, $preco_custo, $preco_venda, $margem_padrao,
                        $estoque_atual, $estoque_minimo, $tipo_unidade, $peso_variavel, $ativo, $imagem_url]);
    }

    header("Location: index.php?ok=1&msg=" . urlencode("Produto salvo com sucesso!"));
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode($e->getMessage()));
    exit;
}
?>