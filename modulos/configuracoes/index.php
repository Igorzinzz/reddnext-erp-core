<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

$stmt = $conn->query("SELECT * FROM config_sistema LIMIT 1");
$configSistema = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-gear me-2"></i>Configurações</h4>

    <!-- ALERTAS DE SUCESSO / ERRO -->
    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm mt-3" role="alert" style="position:sticky;top:10px;z-index:999;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Configurações salvas com sucesso!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm mt-3" role="alert" style="position:sticky;top:10px;z-index:999;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Ocorreu um erro ao salvar. Tente novamente.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <script>
        // Faz a mensagem sumir suavemente após 3 segundos
        setTimeout(() => {
            const alert = document.getElementById('alertMsg');
            if (alert) {
                alert.classList.remove('show');
                alert.classList.add('fade');
                setTimeout(() => alert.remove(), 400);
            }
        }, 3000);
    </script>

    <style>
    /* === ABAS REDDNEXT === */
    .nav-tabs {
        border-bottom: 1px solid #e0e0e0 !important;
        background-color: #fff !important;
    }

    /* Links padrão */
    .nav-tabs .nav-link,
    .nav-tabs .nav-item.show .nav-link {
        border: none !important;
        color: #555 !important;
        font-weight: 500 !important;
        border-radius: 0 !important;
        background: transparent !important;
        transition: all 0.25s ease;
        padding: 10px 18px !important;
    }

    /* Hover */
    .nav-tabs .nav-link:hover,
    .nav-tabs .nav-link:focus {
        color: #dc3545 !important;
        border: none !important;
        border-bottom: 2px solid #dc3545 !important;
        background: transparent !important;
        box-shadow: none !important;
        outline: none !important;
    }

    /* Aba ativa */
    .nav-tabs .nav-link.active,
    .nav-tabs .nav-item.show .nav-link.active {
        color: #dc3545 !important;
        font-weight: 600 !important;
        border: none !important;
        border-bottom: 3px solid #dc3545 !important;
        background: transparent !important;
    }

    /* Mobile (scroll horizontal) */
    @media (max-width: 768px) {
        .nav-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .nav-tabs .nav-link {
            white-space: nowrap !important;
            padding: 8px 14px !important;
        }
    }
    </style>

    <form action="salvar.php" method="POST" enctype="multipart/form-data" class="card shadow-sm bg-white p-4">

        <!-- NAV TABS -->
        <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basico-tab" data-bs-toggle="tab" data-bs-target="#basico" type="button" role="tab">
                    <i class="bi bi-building me-1"></i> Básico
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="integracoes-tab" data-bs-toggle="tab" data-bs-target="#integracoes" type="button" role="tab">
                    <i class="bi bi-plug me-1"></i> Integrações
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="aparencia-tab" data-bs-toggle="tab" data-bs-target="#aparencia" type="button" role="tab">
                    <i class="bi bi-palette me-1"></i> Aparência
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="avancado-tab" data-bs-toggle="tab" data-bs-target="#avancado" type="button" role="tab">
                    <i class="bi bi-sliders me-1"></i> Avançado
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="configTabsContent">

            <!-- BÁSICO -->
            <div class="tab-pane fade show active" id="basico" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="nome_empresa" class="form-control" required value="<?= htmlspecialchars($configSistema['nome_empresa']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-control" value="<?= htmlspecialchars($configSistema['cnpj']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($configSistema['telefone']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($configSistema['email']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($configSistema['endereco']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-control" value="<?= htmlspecialchars($configSistema['cidade']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <input type="text" name="uf" maxlength="2" class="form-control" value="<?= htmlspecialchars($configSistema['uf']) ?>">
                    </div>
                </div>
            </div>

            <!-- INTEGRAÇÕES -->
            <div class="tab-pane fade" id="integracoes" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Chave PIX</label>
                        <input type="text" name="pix_chave" class="form-control" placeholder="Digite a chave PIX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Token NuvemShop</label>
                        <input type="text" name="token_nuvemshop" class="form-control" placeholder="Token da NuvemShop">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Token Mercado Pago</label>
                        <input type="text" name="token_mp" class="form-control" placeholder="Token Mercado Pago">
                    </div>
                </div>
            </div>

            <!-- APARÊNCIA -->
            <div class="tab-pane fade" id="aparencia" role="tabpanel">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label">Tema</label>
                        <select name="tema" class="form-select">
                            <option value="claro" <?= $configSistema['tema'] == 'claro' ? 'selected' : '' ?>>Claro</option>
                            <option value="escuro" <?= $configSistema['tema'] == 'escuro' ? 'selected' : '' ?>>Escuro</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Logo</label><br>
                        <?php if (!empty($configSistema['logo'])): ?>
                            <img src="logo/<?= htmlspecialchars($configSistema['logo']) ?>" alt="Logo" height="60" class="mb-2 d-block">
                        <?php endif; ?>
                        <input type="file" name="logo" class="form-control">
                    </div>
                </div>
            </div>

            <!-- AVANÇADO -->
            <div class="tab-pane fade" id="avancado" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <?php
                            $zones = DateTimeZone::listIdentifiers();
                            foreach ($zones as $z) {
                                $sel = $z == $configSistema['timezone'] ? 'selected' : '';
                                echo "<option value='$z' $sel>$z</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button class="btn btn-danger px-4" type="submit"><i class="bi bi-save me-2"></i>Salvar Alterações</button>
        </div>
    </form>
</div>

<?php endContent(); ?>