<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

$stmt = $conn->query("SELECT * FROM config_sistema LIMIT 1");
$configSistema = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($configSistema['timezone'])) {
    $configSistema['timezone'] = 'America/Sao_Paulo';
}
?>

<style>
/* === Padrão visual ERP (Reddnext) === */
.nav-tabs {
    border-bottom: 1px solid #dee2e6 !important;
    background-color: #fff;
}
.nav-tabs .nav-link {
    color: #212529 !important;
    font-weight: 500;
    border: none !important;
    background: transparent !important;
    transition: all 0.25s ease;
}
.nav-tabs .nav-link:hover {
    color: #dc3545 !important;
    border-bottom: 2px solid #dc3545 !important;
}
.nav-tabs .nav-link.active {
    color: #dc3545 !important;
    border-bottom: 3px solid #dc3545 !important;
    background: transparent !important;
    font-weight: 600;
}
.nav-tabs .nav-link i {
    color: #dc3545 !important;
}
</style>

<div class="container mt-4">
    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-gear me-2"></i>Configurações do Sistema
    </h4>

    <!-- ALERTAS -->
    <?php if (isset($_GET['ok'])): ?>
        <div id="alertMsg" class="alert alert-success alert-dismissible fade show shadow-sm"
             style="position:sticky;top:10px;z-index:999;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Configurações salvas com sucesso!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (isset($_GET['erro'])): ?>
        <div id="alertMsg" class="alert alert-danger alert-dismissible fade show shadow-sm"
             style="position:sticky;top:10px;z-index:999;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_GET['msg'] ?? 'Ocorreu um erro ao salvar. Tente novamente.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        setTimeout(() => {
            const alert = document.getElementById('alertMsg');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 400);
            }
        }, 3000);
    </script>

    <form action="salvar.php" method="POST" enctype="multipart/form-data" class="card shadow-sm bg-white p-4">

        <!-- NAV TABS -->
        <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basico-tab" data-bs-toggle="tab"
                        data-bs-target="#basico" type="button" role="tab">
                    <i class="bi bi-building me-1"></i> Básico
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="integracoes-tab" data-bs-toggle="tab"
                        data-bs-target="#integracoes" type="button" role="tab">
                    <i class="bi bi-plug me-1"></i> Integrações
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="avancado-tab" data-bs-toggle="tab"
                        data-bs-target="#avancado" type="button" role="tab">
                    <i class="bi bi-sliders me-1"></i> Avançado
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="configTabsContent">

            <!-- =======================
                 ABA: BÁSICO
            ======================== -->
            <div class="tab-pane fade show active" id="basico" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Margem Padrão Global (%)</label>
                        <input type="number" step="0.1" min="0" max="999" name="margem_padrao" class="form-control"
                               value="<?= htmlspecialchars($configSistema['margem_padrao'] ?? 30) ?>">
                        <small class="text-muted">Usada para calcular preços sugeridos quando o produto não possui margem própria.</small>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nome da Empresa</label>
                        <input type="text" name="nome_empresa" class="form-control"
                               value="<?= htmlspecialchars($configSistema['nome_empresa']) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-control"
                               value="<?= htmlspecialchars($configSistema['cnpj']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control"
                               value="<?= htmlspecialchars($configSistema['telefone']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($configSistema['email']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" class="form-control"
                               value="<?= htmlspecialchars($configSistema['cidade']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UF</label>
                        <input type="text" name="uf" maxlength="2"
                               class="form-control text-uppercase"
                               value="<?= htmlspecialchars($configSistema['uf']) ?>">
                    </div>
                    <div class="col-md-10">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" class="form-control"
                               value="<?= htmlspecialchars($configSistema['endereco']) ?>">
                    </div>

                    <!-- NOVO BLOCO: LOGO -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Logo da Empresa</label>
                        <input type="file" name="logo" accept="image/*" class="form-control">
                        <small class="text-muted">Formatos aceitos: JPG, PNG ou WEBP. Será usada em relatórios e documentos.</small>

                        <?php if (!empty($configSistema['logo'])): ?>
                            <div class="mt-3">
                                <img src="<?= htmlspecialchars($configSistema['logo']) ?>" 
                                     alt="Logo atual" class="img-thumbnail" 
                                     style="max-width:180px;max-height:120px;object-fit:contain;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remover_logo" value="1" id="removerLogo">
                                    <label class="form-check-label" for="removerLogo">Remover logo atual</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- FIM BLOCO LOGO -->
                </div>
            </div>

            <!-- =======================
                 ABA: INTEGRAÇÕES
            ======================== -->
            <div class="tab-pane fade" id="integracoes" role="tabpanel">
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-tools display-6 d-block mb-2 text-danger"></i>
                    <h6 class="fw-bold mb-1">Integrações em breve</h6>
                    <p class="small">Esta seção permitirá conectar com gateways de pagamento, e-commerce e outras plataformas.</p>
                    <span class="badge bg-light text-dark border">Em desenvolvimento</span>
                </div>
            </div>

            <!-- =======================
                 ABA: AVANÇADO
            ======================== -->
            <div class="tab-pane fade" id="avancado" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label">Fuso Horário (Timezone)</label>
                        <select name="timezone" class="form-select">
                            <?php
                            $zones = DateTimeZone::listIdentifiers();
                            foreach ($zones as $z) {
                                $sel = ($z == $configSistema['timezone']) ? 'selected' : '';
                                echo "<option value='$z' $sel>$z</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Padrão: America/Sao_Paulo</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTÃO SALVAR -->
        <div class="mt-4 text-end">
            <button class="btn btn-danger px-4" type="submit">
                <i class="bi bi-save me-2"></i>Salvar Alterações
            </button>
        </div>
    </form>
</div>

<?php endContent(); ?>