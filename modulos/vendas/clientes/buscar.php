<?php
include '../../../core/init.php';

$term = $_GET['term'] ?? '';

$stmt = $conn->prepare("
    SELECT id, nome
    FROM clientes
    WHERE nome LIKE ? AND ativo = 1
    ORDER BY nome ASC
    LIMIT 20
");
$stmt->execute(["%$term%"]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resultados = [];
foreach ($clientes as $c) {
    $resultados[] = [
        'id' => $c['id'],
        'text' => $c['nome']
    ];
}

echo json_encode($resultados);