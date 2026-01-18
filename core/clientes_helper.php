<?php
/**
 * ------------------------------------------
 * clientes_helper.php
 * ------------------------------------------
 * Resolve cliente de forma inteligente:
 *  - Se CPF/CNPJ for informado, tenta localizar.
 *  - Se não existir, cria automaticamente.
 *  - Se só tiver nome, tenta pelo nome.
 *  - Retorna sempre ['cliente_id', 'cliente_nome'].
 */

function resolverClientePorCpf(PDO $conn, ?string $nome, ?string $cpfCnpj): array {
    $nome    = trim($nome ?? '');
    $cpfCnpj = preg_replace('/\D+/', '', $cpfCnpj ?? ''); // remove tudo que não for número

    // 1️⃣ Tenta localizar por CPF/CNPJ
    if ($cpfCnpj !== '') {
        $stmt = $conn->prepare("SELECT id, nome FROM clientes WHERE documento = ? LIMIT 1");
        $stmt->execute([$cpfCnpj]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            return [
                'cliente_id'   => (int)$found['id'],
                'cliente_nome' => $found['nome']
            ];
        }

        // Não encontrado → cria novo
        $novoNome = $nome !== '' ? $nome : "Cliente {$cpfCnpj}";
        $stmt = $conn->prepare("
            INSERT INTO clientes (nome, documento, ativo, criado_em)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([$novoNome, $cpfCnpj]);
        $novoId = (int)$conn->lastInsertId();

        return [
            'cliente_id'   => $novoId,
            'cliente_nome' => $novoNome
        ];
    }

    // 2️⃣ Se não tem CPF, tenta localizar por nome
    if ($nome !== '') {
        $stmt = $conn->prepare("SELECT id, nome FROM clientes WHERE nome = ? LIMIT 1");
        $stmt->execute([$nome]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            return [
                'cliente_id'   => (int)$found['id'],
                'cliente_nome' => $found['nome']
            ];
        }

        // Não encontrado → cria novo
        $stmt = $conn->prepare("
            INSERT INTO clientes (nome, ativo, criado_em)
            VALUES (?, 1, NOW())
        ");
        $stmt->execute([$nome]);
        $novoId = (int)$conn->lastInsertId();

        return [
            'cliente_id'   => $novoId,
            'cliente_nome' => $nome
        ];
    }

    // 3️⃣ Nenhum dado → retorna genérico
    return [
        'cliente_id'   => null,
        'cliente_nome' => 'Consumidor Final'
    ];
}
?>