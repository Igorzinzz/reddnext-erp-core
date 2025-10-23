<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

// ==========================
// Busca produtos com preço sugerido
// ==========================
$stmt = $conn->query("
    SELECT id, nome, codigo_ean, preco_custo, preco_venda, preco_sugerido, margem_padrao, estoque_atual, tipo_unidade
    FROM vendas_estoque
    WHERE preco_sugerido IS NOT NULL
    ORDER BY nome ASC
");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-cash-coin me-2"></i>Revisar Preços Sugeridos
        </h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Voltar ao Estoque
        </a>
    </div>

    <?php if (count($produtos) === 0): ?>
        <div class="alert alert-light border text-center py-4">
            <i class="bi bi-check-circle text-success fs-4 mb-2 d-block"></i>
            <h6 class="fw-bold mb-1">Nenhum preço sugerido pendente!</h6>
            <p class="text-muted mb-0">Todos os produtos estão com preços atualizados.</p>
        </div>
    <?php else: ?>
        <form method="post" action="revisar_precos_aplicar.php" onsubmit="return confirm('Aplicar os preços sugeridos selecionados?')">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Produto</th>
                                <th>EAN</th>
                                <th>Custo (R$)</th>
                                <th>Venda Atual (R$)</th>
                                <th class="text-warning">Sugerido (R$)</th>
                                <th>Margem (%)</th>
                                <th>Estoque</th>
                                <th style="width:80px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="checkAll" title="Selecionar todos">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $p): 
                                // Unidade padronizada
                                $un = strtoupper(trim($p['tipo_unidade'] ?? 'UN'));
                                if (!in_array($un, ['UN', 'KG'])) $un = 'UN';

                                // Margem baseada no sugerido
                                $margemCalc = ($p['preco_custo'] > 0)
                                    ? (($p['preco_sugerido'] / $p['preco_custo'] - 1) * 100)
                                    : 0;
                                $margemCalc = round($margemCalc, 2);

                                // Classes visuais
                                $clsMargem = $margemCalc < 0 ? 'text-danger' : ($margemCalc < 10 ? 'text-warning' : 'text-success');

                                // Estoque formatado conforme unidade
                                $estoqueFmt = ($un === 'KG')
                                    ? number_format($p['estoque_atual'], 3, ',', '.') . ' kg'
                                    : number_format($p['estoque_atual'], 0, ',', '.') . ' un';
                            ?>
                                <tr>
                                    <td><?= $p['id'] ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['nome']) ?></td>
                                    <td><?= htmlspecialchars($p['codigo_ean'] ?: '—') ?></td>
                                    <td>R$ <?= number_format($p['preco_custo'], 2, ',', '.') ?></td>
                                    <td class="text-muted">
                                        <?= $p['preco_venda'] > 0 
                                            ? 'R$ ' . number_format($p['preco_venda'], 2, ',', '.') 
                                            : '<em>—</em>' ?>
                                    </td>
                                    <td class="fw-bold text-warning">
                                        R$ <?= number_format($p['preco_sugerido'], 2, ',', '.') ?>
                                    </td>
                                    <td class="<?= $clsMargem ?> fw-semibold">
                                        <?= number_format($margemCalc, 1, ',', '.') ?>%
                                    </td>
                                    <td><?= $estoqueFmt ?></td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" name="ids[]" value="<?= $p['id'] ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        <?= count($produtos) ?> produto(s) com preço sugerido pendente
                    </span>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">
                        <i class="bi bi-check2-circle me-2"></i>Aplicar Selecionados
                    </button>
                </div>
            </div>
        </form>

        <script>
        document.getElementById('checkAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        </script>
    <?php endif; ?>
</div>

<?php endContent(); ?>