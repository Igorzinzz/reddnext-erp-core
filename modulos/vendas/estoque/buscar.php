<?php
include '../../../core/init.php';
include '../../../core/auth.php';

// Termo digitado
$termo = trim($_GET['term'] ?? '');
if ($termo === '') {
    echo json_encode([]);
    exit;
}

// Busca produtos ativos por nome
$stmt = $conn->prepare("
    SELECT id, nome, preco_venda, estoque_atual 
    FROM vendas_estoque 
    WHERE ativo = 1 
      AND nome LIKE ? 
    ORDER BY nome ASC 
    LIMIT 10
");
$stmt->execute(["%{$termo}%"]);

$result = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $text = "{$p['nome']} — R$ " . number_format($p['preco_venda'], 2, ',', '.') . " (Estoque: {$p['estoque_atual']})";
    $result[] = [
        'id' => $p['id'],
        'text' => $text,
        'preco' => $p['preco_venda'],
        'estoque' => $p['estoque_atual']
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);