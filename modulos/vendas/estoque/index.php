<?php
include '../../../core/init.php';
include '../../../core/auth.php';
include '../../../core/layout.php';
startContent();

// ==========================
// Configurações globais (meta padrão)
// ==========================
$configuracoes = $conn->query("SELECT margem_padrao FROM config_sistema LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$margem_global = floatval($configuracoes['margem_padrao'] ?? 30);

// ==========================
// Filtros
// ==========================
$busca  = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? '';

$sql = "SELECT * FROM vendas_estoque WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND nome LIKE ?";
    $params[] = "%{$busca}%";
}
if ($status !== '') {
    $sql .= " AND ativo = ?";
    $params[] = ($status === 'ativo') ? 1 : 0;
}

$sql .= " ORDER BY ativo DESC, nome ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verifica se há produtos com diferença de preço sugerido
$stmtSug = $conn->query("
    SELECT COUNT(*) FROM vendas_estoque 
    WHERE preco_sugerido IS NOT NULL AND preco_sugerido <> preco_venda
");
$pendentesPreco = $stmtSug->fetchColumn();

// Mensagens
$msg  = urldecode($_GET['msg'] ?? '');
$ok   = isset($_GET['ok']);
$erro = isset($_GET['erro']);
?>

<div class="container-fluid mt-4">

    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <h4 class="fw-bold mb-0">
                <i class="bi bi-box-seam me-2"></i>Estoque
            </h4>

            <?php if ($pendentesPreco > 0): ?>
                <a href="revisar_precos.php" class="btn btn-warning btn-sm fw-semibold shadow-sm">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <?= $pendentesPreco ?> produto<?= $pendentesPreco > 1 ? 's' : '' ?> com preço sugerido
                </a>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalImportarXML">
                <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar NF-e (XML)
            </button>
            <a href="editar.php" class="btn btn-danger">
                <i class="bi bi-plus-circle me-2"></i>Novo Produto
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="card border-0 shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold text-muted small mb-1">Buscar Produto</label>
                <input type="text" name="busca" class="form-control" placeholder="Digite o nome do produto..."
                       value="<?= htmlspecialchars($busca) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold text-muted small mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                    <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>
            <div class="col-md-4 text-end">
                <button type="submit" class="btn btn-outline-danger px-4">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
                <a href="index.php" class="btn btn-light border ms-2" title="Limpar filtros">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- Alertas -->
    <?php if ($ok && $msg): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($erro && $msg): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => {
            const a = document.getElementById('alertMsg');
            if (a) a.classList.remove('show');
        }, 3500);
    </script>

    <!-- Tabela -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Produto</th>
                        <th>Unid.</th>
                        <th>Custo (R$)</th>
                        <th>Venda (R$)</th>
                        <th>Margem atual (%)</th>
                        <th>Estoque</th>
                        <th>Mínimo</th>
                        <th>Status</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($produtos): ?>
                        <?php foreach ($produtos as $p):
                            // Unidade
                            $un = strtoupper(trim($p['tipo_unidade'] ?? 'UN'));
                            if (!in_array($un, ['UN', 'KG'])) $un = 'UN';

                            // Margem ATUAL (real) com base em custo x venda
                            $margem_atual = ($p['preco_custo'] > 0)
                                ? (($p['preco_venda'] / $p['preco_custo']) - 1) * 100
                                : 0;
                            $margem_atual = round($margem_atual, 2);

                            // Meta de margem: primeiro a do produto (se >0), senão a global
                            $meta_produto = isset($p['margem_padrao']) ? floatval($p['margem_padrao']) : 0.0;
                            $meta_efetiva = ($meta_produto > 0) ? $meta_produto : $margem_global;

                            // Classe visual com base na meta efetiva
                            if ($margem_atual < 0) {
                                $cls = 'text-danger';
                            } elseif ($margem_atual < $meta_efetiva) {
                                $cls = 'text-warning';
                            } else {
                                $cls = 'text-success';
                            }

                            // Tooltip informando a meta usada (produto ou global)
                            $origem_meta = ($meta_produto > 0) ? 'meta do produto' : 'meta global';
                            $title_meta  = 'Meta: ' . number_format($meta_efetiva, 1, ',', '.') . '% (' . $origem_meta . ')';

                            // Estoque
                            $baixo = $p['estoque_minimo'] > 0 && $p['estoque_atual'] < $p['estoque_minimo'];
                            $estoque = ($un === 'KG')
                                ? number_format($p['estoque_atual'], 3, ',', '.') . ' kg'
                                : number_format($p['estoque_atual'], 0, ',', '.') . ' un';
                            $estoqueMin = ($un === 'KG')
                                ? number_format($p['estoque_minimo'], 3, ',', '.') . ' kg'
                                : number_format($p['estoque_minimo'], 0, ',', '.') . ' un';

                            // Verifica vínculos
                            $stmtVenda = $conn->prepare("SELECT COUNT(*) FROM vendas_itens WHERE produto_id = ?");
                            $stmtVenda->execute([$p['id']]);
                            $usadoVenda = $stmtVenda->fetchColumn();

                            $stmtEntrada = $conn->prepare("SELECT COUNT(*) FROM vendas_entradas_itens WHERE produto_id = ?");
                            $stmtEntrada->execute([$p['id']]);
                            $usadoEntrada = $stmtEntrada->fetchColumn();

                            $temVinculo = ($usadoVenda > 0 || $usadoEntrada > 0);
                        ?>
                            <tr class="<?= $baixo ? 'table-warning' : '' ?>">
                                <td><?= $p['id'] ?></td>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($p['nome']) ?>
                                    <?php if (!empty($p['preco_sugerido']) && $p['preco_sugerido'] != $p['preco_venda']): ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            <i class="bi bi-arrow-repeat me-1"></i>
                                            Sugerido: R$ <?= number_format($p['preco_sugerido'], 2, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= $un ?></span></td>
                                <td>R$ <?= number_format($p['preco_custo'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
                                <td class="<?= $cls ?> fw-semibold" title="<?= htmlspecialchars($title_meta) ?>">
                                    <?= number_format($margem_atual, 1, ',', '.') ?>%
                                </td>
                                <td>
                                    <?= $estoque ?>
                                    <?= $baixo ? ' <span class="badge bg-warning text-dark ms-2">Baixo</span>' : '' ?>
                                </td>
                                <td><?= $estoqueMin ?></td>
                                <td>
                                    <span class="badge <?= $p['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="editar.php?id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Editar produto">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($temVinculo): ?>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                title="Produto com vínculos — não pode ser excluído" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="excluir.php?id=<?= $p['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           title="Excluir definitivamente" 
                                           onclick="return confirm('Deseja realmente excluir este produto?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>Nenhum produto encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: Importar NF-e (XML) -->
<div class="modal fade" id="modalImportarXML" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content border-0 shadow" method="POST" action="importar_xml.php" enctype="multipart/form-data">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title fw-bold">
            <i class="bi bi-filetype-xml me-2"></i>Importar NF-e (XML)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Arquivo XML da NF-e</label>
          <input type="file" name="xml" class="form-control" accept=".xml" required>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="atualizar_precos" id="chkAtualizar" checked>
            <label class="form-check-label fw-semibold" for="chkAtualizar">
                Atualizar preços automaticamente conforme margem cadastrada
            </label>
        </div>

        <div class="alert alert-light border small">
          <div class="fw-semibold mb-1">O que será feito:</div>
          <ul class="mb-0 ps-3">
            <li>Criamos novos produtos quando não existirem;</li>
            <li>Atualizamos estoque e custo dos produtos existentes;</li>
            <li>Se marcado, recalculamos preços conforme a meta (produto ou global).</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">
          <i class="bi bi-upload me-2"></i>Importar
        </button>
      </div>
    </form>
  </div>
</div>

<?php endContent(); ?>