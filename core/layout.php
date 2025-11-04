<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/auth.php';

$config = include __DIR__ . '/config.php';
$current = $_SERVER['REQUEST_URI'];

// 🔹 Detecta automaticamente o arquivo versao.txt (na raiz do ERP)
$versaoFile = __DIR__ . '/../versao.txt';
if (!file_exists($versaoFile)) {
    $versaoFile = __DIR__ . '/versao.txt';
}

// 🔹 Lê a versão e data da última modificação
$versaoSistema = file_exists($versaoFile)
    ? trim(file_get_contents($versaoFile))
    : 'v0.0.0';

$dataVersao = file_exists($versaoFile)
    ? date('d/m/Y H:i', filemtime($versaoFile))
    : '-';

// 🔔 Notificações (não quebra nada: usa somente vendas_estoque)
$notifs = [];
try {
    // Estoque baixo
    $stmtLow = $conn->query("
        SELECT id, nome 
        FROM vendas_estoque 
        WHERE estoque_minimo > 0 AND estoque_atual < estoque_minimo
        ORDER BY (estoque_minimo - estoque_atual) DESC
        LIMIT 5
    ");
    $lowItems = $stmtLow ? $stmtLow->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($lowItems as $it) {
        $notifs[] = [
            'icon' => 'bi-exclamation-circle text-danger',
            'texto' => 'Estoque baixo: ' . $it['nome'],
            'href' => $config['base_url'] . 'vendas/estoque/?status=ativo'
        ];
    }

    // Preço sugerido pendente
    $pend = $conn->query("
        SELECT COUNT(*) 
        FROM vendas_estoque 
        WHERE preco_sugerido IS NOT NULL AND preco_sugerido <> preco_venda
    ");
    $pendCount = $pend ? (int)$pend->fetchColumn() : 0;
    if ($pendCount > 0) {
        $notifs[] = [
            'icon' => 'bi-arrow-repeat text-warning',
            'texto' => $pendCount . ' produto(s) com preço sugerido',
            'href'  => $config['base_url'] . 'vendas/estoque/revisar_precos.php'
        ];
    }
} catch (Throwable $e) {
    // silencioso: não quebra o layout se não houver tabela/colunas
}
$notifCount = count($notifs);

// Funções de layout
function startContent() { echo '<main class="content px-4 py-3">'; }
function endContent() { echo '</main>'; include __DIR__ . '/footer.php'; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $config['app_name'] ?> - Painel</title>

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.6.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<!-- CSS do sistema -->
<link rel="stylesheet" href="<?= $config['base_url'] ?>css/style.css">

<style>
    #sidebar {
    background-color: #fff;
    border-right: 1px solid #e0e0e0;
    width: 250px;
    min-height: 100vh;
    height: 100vh;
    transition: width 0.3s ease;
    overflow-y: auto;
    overflow-x: hidden;
    flex-shrink: 0;
    }
    
    #sidebar a {
        color: #333 !important;
        display: flex;
        align-items: center;
        padding: 10px 16px;
        border-radius: 6px;
        margin-bottom: 4px;
        font-weight: 500;
        text-decoration: none !important;
        transition: all 0.25s ease;
    }
    
    #sidebar a:hover {
        background-color: #dc3545;
        color: #fff !important;
        transform: translateX(10px);
        transition: transform 0.25s ease, background-color 0.25s ease;
    }
    
    #sidebar a.active {
        background-color: #dc3545;
        color: #fff !important;
    }
    
    #sidebar i {
        width: 22px;
        text-align: center;
        margin-right: 8px;
        font-size: 1rem;
    }

    .submenu a {
        font-size: 0.95rem;
        padding-left: 35px;
    }
    .toggle-arrow {
        margin-left: auto;
        cursor: pointer;
        color: #999;
        transition: transform 0.2s ease;
    }
    .toggle-arrow.rotate {
        transform: rotate(180deg);
        color: #dc3545;
    }
    header {
        background-color: #ffffff !important;
        border-bottom: 1px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    #menu-toggle {
        border-color: #ccc;
        color: #333;
    }
    #menu-toggle:hover {
        border-color: #dc3545;
        color: #dc3545;
    }
    #page-content-wrapper {
        flex-grow: 1;
        background-color: #f5f6fa;
    }
    footer {
        background-color: #fff;
        text-align: center;
        font-size: 0.85rem;
        color: #777;
        padding: 10px;
        border-top: 1px solid #eee;
    }
    .select2-container--bootstrap4 .select2-selection {
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        padding: 0.375rem 0.75rem;
        height: auto !important;
        min-height: 38px;
    }
    .select2-container--bootstrap4 .select2-selection__arrow {
        top: 6px !important;
        right: 10px !important;
    }
    .select2-container--bootstrap4 .select2-selection__rendered {
        padding-left: 0 !important;
    }
    .btn-pdv {
        background: linear-gradient(90deg, #dc3545 0%, #c82333 100%);
        color: #fff !important;
        font-weight: 600;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.4);
        transition: all 0.25s ease;
    }
    .btn-pdv:hover {
        background: linear-gradient(90deg, #c82333 0%, #a71d2a 100%);
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(220, 53, 69, 0.5);
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

</head>

<body>
<div class="d-flex" id="wrapper">

    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header text-center py-4 border-bottom">

            <!-- 🔹 Logo padrão -->
            <div class="mb-2">
                <img src="/assets/img/logo.png" 
                     alt="Logo do Sistema" 
                     style="max-width:140px; height:auto;">
            </div>

            <small class="text-muted d-block">
                <?= htmlspecialchars($versaoSistema) ?><br>
                <span style="font-size:11px;">Atualizado em <?= htmlspecialchars($dataVersao) ?></span>
            </small>
        </div>

        <ul class="list-unstyled px-3 mt-3">
            <!-- DASHBOARD -->
            <li>
                <a href="<?= $config['base_url'] ?>dashboard/"
                   class="<?= str_contains($current, '/dashboard/') ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>

            <!-- FINANCEIRO -->
            <li class="mt-2">
                <div class="d-flex align-items-center">
                    <a href="<?= $config['base_url'] ?>financeiro/"
                       class="flex-grow-1 <?= (
                           str_contains($current, '/financeiro/') &&
                           !str_contains($current, '/financeiro/contas/') &&
                           !str_contains($current, '/financeiro/categorias/')
                       ) ? 'active' : '' ?>">
                        <i class="bi bi-cash-stack me-2"></i> Financeiro
                    </a>
                    <i class="bi bi-chevron-down toggle-arrow <?= str_contains($current, '/financeiro/') ? 'rotate' : '' ?>"
                       data-bs-toggle="collapse"
                       data-bs-target="#financeiroSub"
                       aria-expanded="<?= str_contains($current, '/financeiro/') ? 'true' : 'false' ?>"
                       aria-controls="financeiroSub"></i>
                </div>

                <ul class="collapse submenu <?= str_contains($current, '/financeiro/') ? 'show' : '' ?>" id="financeiroSub">
                    <li>
                        <a href="<?= $config['base_url'] ?>financeiro/contas/"
                           class="<?= str_contains($current, '/financeiro/contas/') ? 'active' : '' ?>">
                           <i class="bi bi-bank me-2"></i> Contas Bancárias
                        </a>
                    </li>
                    <li>
                        <a href="<?= $config['base_url'] ?>financeiro/categorias/"
                           class="<?= str_contains($current, '/financeiro/categorias/') ? 'active' : '' ?>">
                           <i class="bi bi-tags me-2"></i> Categorias
                        </a>
                    </li>
                </ul>
            </li>

            <!-- VENDAS -->
            <li class="mt-2">
                <div class="d-flex align-items-center">
                    <a href="<?= $config['base_url'] ?>vendas/"
                       class="flex-grow-1 <?= (
                           str_contains($current, '/vendas/') &&
                           !str_contains($current, '/vendas/clientes/') &&
                           !str_contains($current, '/vendas/estoque/') &&
                           !str_contains($current, '/vendas/orcamentos/')
                       ) ? 'active' : '' ?>">
                        <i class="bi bi-cart3 me-2"></i> Vendas
                    </a>
                    <i class="bi bi-chevron-down toggle-arrow <?= str_contains($current, '/vendas/') ? 'rotate' : '' ?>"
                       data-bs-toggle="collapse"
                       data-bs-target="#vendasSub"
                       aria-expanded="<?= str_contains($current, '/vendas/') ? 'true' : 'false' ?>"
                       aria-controls="vendasSub"></i>
                </div>
            
                <ul class="collapse submenu <?= str_contains($current, '/vendas/') ? 'show' : '' ?>" id="vendasSub">
                    <li>
                        <a href="<?= $config['base_url'] ?>vendas/clientes/"
                           class="<?= str_contains($current, '/vendas/clientes/') ? 'active' : '' ?>">
                           <i class="bi bi-person-lines-fill me-2"></i> Clientes
                        </a>
                    </li>
                    <li>
                        <a href="<?= $config['base_url'] ?>vendas/estoque/"
                           class="<?= str_contains($current, '/vendas/estoque/') ? 'active' : '' ?>">
                           <i class="bi bi-box-seam me-2"></i> Estoque
                        </a>
                    </li>
                    <li>
                        <a href="<?= $config['base_url'] ?>vendas/orcamentos/"
                           class="<?= str_contains($current, '/vendas/orcamentos/') ? 'active' : '' ?>">
                           <i class="bi bi-clipboard-data me-2"></i> Orçamentos
                        </a>
                    </li>
                </ul>
            </li>

            <!-- USUÁRIOS -->
            <li class="mt-2">
                <a href="<?= $config['base_url'] ?>usuarios/"
                   class="<?= str_contains($current, '/usuarios/') ? 'active' : '' ?>">
                    <i class="bi bi-people me-2"></i> Usuários
                </a>
            </li>

            <!-- CONFIGURAÇÕES -->
            <hr class="my-3">
            <li>
                <a href="<?= $config['base_url'] ?>configuracoes/"
                   class="<?= str_contains($current, '/configuracoes/') ? 'active' : '' ?>">
                    <i class="bi bi-gear me-2"></i> Configurações
                </a>
            </li>
        </ul>
    </nav>

    <!-- Página principal -->
    <div id="page-content-wrapper" class="flex-grow-1 bg-light">

        <!-- Header -->
        <header class="navbar navbar-light px-4 py-2 d-flex justify-content-between align-items-center border-bottom bg-white shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-dark btn-sm" id="menu-toggle" title="Alternar menu lateral">
                    <i class="bi bi-list"></i>
                </button>

                <span class="fw-semibold text-muted"><?= $config['app_name'] ?></span>

                <a href="<?= $config['base_url'] ?>pdv/" target="_blank" class="btn btn-sm btn-pdv ms-2">
                    <i class="bi bi-shop me-1"></i> Abrir PDV
                </a>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- 🔔 Notificações -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifCount" style="font-size:10px;"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="min-width:260px;">
                        <li class="dropdown-header bg-danger text-white fw-semibold py-2 px-3">
                            <i class="bi bi-bell-fill me-1"></i> Notificações
                        </li>
                        <li><hr class="dropdown-divider m-0"></li>

                        <?php if ($notifCount === 0): ?>
                            <li><div class="px-3 py-3 small text-muted">Sem novas notificações.</div></li>
                        <?php else: ?>
                            <?php foreach ($notifs as $n): ?>
                                <li>
                                    <a class="dropdown-item small py-2 d-flex align-items-center" href="<?= htmlspecialchars($n['href']) ?>">
                                        <i class="bi <?= $n['icon'] ?> me-2"></i>
                                        <span><?= htmlspecialchars($n['texto']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider m-0"></li>
                        <li><a class="dropdown-item text-center small text-muted py-2" href="<?= $config['base_url'] ?>vendas/estoque/">Ver todas</a></li>
                    </ul>
                </div>

                <span class="text-muted small"><?= htmlspecialchars($_SESSION['usuario']['nome'] ?? '') ?></span>
                <a href="/core/logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </header>

        <!-- Conteúdo -->
        <?php startContent(); ?>

<script>
document.querySelectorAll('.toggle-arrow').forEach(icon => {
    const targetSel = icon.getAttribute('data-bs-target');
    const target = document.querySelector(targetSel);
    if (!target) return;

    const collapse = bootstrap.Collapse.getOrCreateInstance(target, { toggle: false });

    icon.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        collapse.toggle();
    });

    target.addEventListener('shown.bs.collapse', () => icon.classList.add('rotate'));
    target.addEventListener('hidden.bs.collapse', () => icon.classList.remove('rotate'));
});
</script>