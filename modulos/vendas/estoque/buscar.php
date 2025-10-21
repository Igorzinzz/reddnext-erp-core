<?php
include '../../../core/init.php';
include '../../../core/auth.php';

// Termo digitado
$termo = trim($_GET['term'] ?? '');
if ($termo === '') {
    echo json_encode([]);
    exit;
}

// Busca produtos ativos
$stmt = $conn->prepare("
    SELECT id, nome, preco_venda, estoque_atual, tipo_unidade, peso_variavel
    FROM vendas_estoque 
    WHERE ativo = 1 
      AND nome LIKE ? 
    ORDER BY nome ASC 
    LIMIT 15
");
$stmt->execute(["%{$termo}%"]);

$result = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $unidade = $p['tipo_unidade'] ?: 'UN';
    $estoqueTxt = $unidade === 'KG'
        ? number_format($p['estoque_atual'], 3, ',', '.') . ' kg'
        : intval($p['estoque_atual']) . ' un';

    $text = "{$p['nome']} — R$ " . number_format($p['preco_venda'], 2, ',', '.') . " ({$estoqueTxt})";

    $result[] = [
        'id' => $p['id'],
        'text' => $text,
        'preco' => $p['preco_venda'],
        'estoque' => $p['estoque_atual'],
        'tipo_unidade' => $unidade,
        'peso_variavel' => (int)$p['peso_variavel']
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);