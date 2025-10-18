<?php
include '../../core/init.php';
include '../../core/auth.php';

$id = intval($_POST['id'] ?? 0);
$tipo = $_POST['tipo'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$valor = $_POST['valor'] ?? 0;
$data_lancamento = $_POST['data_lancamento'] ?? date('Y-m-d');
$data_vencimento = $_POST['data_vencimento'] ?? null;
$forma_pagamento = $_POST['forma_pagamento'] ?? '';
$conta = $_POST['conta'] ?? '';
$status = $_POST['status'] ?? 'pendente';
$chave_pix = $_POST['chave_pix'] ?? '';
$banco = $_POST['banco'] ?? '';
$agencia = $_POST['agencia'] ?? '';
$numero_conta = $_POST['numero_conta'] ?? '';
$favorecido = $_POST['favorecido'] ?? '';

$upload_dir = __DIR__ . '/../../uploads/financeiro/';
$upload_url = '/uploads/financeiro/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ============================
// Função: upload seguro com substituição
// ============================
function uploadArquivo($inputName, $prefix, $registroAntigo = null, $campo = '') {
    global $upload_dir, $upload_url;

    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return $registroAntigo[$campo] ?? null;
    }

    // Remove o arquivo anterior se existir
    if (!empty($registroAntigo[$campo])) {
        $caminhoAntigo = $_SERVER['DOCUMENT_ROOT'] . $registroAntigo[$campo];
        if (file_exists($caminhoAntigo)) {
            @unlink($caminhoAntigo);
        }
    }

    // Salva o novo arquivo
    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    $nomeArquivo = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $destino = $upload_dir . $nomeArquivo;

    if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $destino)) {
        return $upload_url . $nomeArquivo;
    }

    return $registroAntigo[$campo] ?? null;
}

// ============================
// Se for atualização, busca dados antigos
// ============================
$dadosAntigos = [];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM financeiro WHERE id = ?");
    $stmt->execute([$id]);
    $dadosAntigos = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ============================
// Faz upload dos anexos
// ============================
$anexo_nfe = uploadArquivo('anexo_nfe', 'nfe', $dadosAntigos, 'anexo_nfe');
$anexo_boleto = uploadArquivo('anexo_boleto', 'boleto', $dadosAntigos, 'anexo_boleto');
$anexo_comprovante = uploadArquivo('anexo_comprovante', 'comprovante', $dadosAntigos, 'anexo_comprovante');

// ============================
// Query dinâmica (INSERT ou UPDATE)
// ============================
if ($id > 0) {
    $sql = "UPDATE financeiro SET 
        tipo = :tipo,
        categoria = :categoria,
        descricao = :descricao,
        valor = :valor,
        data_lancamento = :data_lancamento,
        data_vencimento = :data_vencimento,
        forma_pagamento = :forma_pagamento,
        conta = :conta,
        status = :status,
        chave_pix = :chave_pix,
        banco = :banco,
        agencia = :agencia,
        numero_conta = :numero_conta,
        favorecido = :favorecido,
        anexo_boleto = :anexo_boleto,
        anexo_comprovante = :anexo_comprovante,
        anexo_nfe = :anexo_nfe
        WHERE id = :id";
} else {
    $sql = "INSERT INTO financeiro (
        tipo, categoria, descricao, valor, data_lancamento, data_vencimento,
        forma_pagamento, conta, status, chave_pix, banco, agencia, numero_conta,
        favorecido, anexo_boleto, anexo_comprovante, anexo_nfe
    ) VALUES (
        :tipo, :categoria, :descricao, :valor, :data_lancamento, :data_vencimento,
        :forma_pagamento, :conta, :status, :chave_pix, :banco, :agencia, :numero_conta,
        :favorecido, :anexo_boleto, :anexo_comprovante, :anexo_nfe
    )";
}

$stmt = $conn->prepare($sql);
$stmt->bindValue(':tipo', $tipo);
$stmt->bindValue(':categoria', $categoria);
$stmt->bindValue(':descricao', $descricao);
$stmt->bindValue(':valor', $valor);
$stmt->bindValue(':data_lancamento', $data_lancamento);
$stmt->bindValue(':data_vencimento', $data_vencimento);
$stmt->bindValue(':forma_pagamento', $forma_pagamento);
$stmt->bindValue(':conta', $conta);
$stmt->bindValue(':status', $status);
$stmt->bindValue(':chave_pix', $chave_pix);
$stmt->bindValue(':banco', $banco);
$stmt->bindValue(':agencia', $agencia);
$stmt->bindValue(':numero_conta', $numero_conta);
$stmt->bindValue(':favorecido', $favorecido);
$stmt->bindValue(':anexo_boleto', $anexo_boleto);
$stmt->bindValue(':anexo_comprovante', $anexo_comprovante);
$stmt->bindValue(':anexo_nfe', $anexo_nfe);

if ($id > 0) $stmt->bindValue(':id', $id);

// ============================
// Execução e redirecionamento
// ============================
try {
    $stmt->execute();
    header("Location: index.php?ok=1&msg=" . urlencode($id > 0 ? "Lançamento atualizado!" : "Lançamento cadastrado!"));
    exit;
} catch (Exception $e) {
    header("Location: index.php?erro=1&msg=" . urlencode("Erro ao salvar: " . $e->getMessage()));
    exit;
}