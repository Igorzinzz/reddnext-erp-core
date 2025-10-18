<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();
?>

<div class="container-fluid p-0" style="height: calc(100vh - 60px); overflow: hidden;">

    <!-- HEADER -->
    <div class="bg-white shadow-sm d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shop fs-4 text-danger"></i>
            <h5 class="mb-0 fw-bold">PDV - Ponto de Venda</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-muted small">
                Operador: <strong><?= htmlspecialchars($_SESSION['usuario_nome']) ?></strong>
            </div>
            <div id="relogio" class="fw-semibold"></div>
            <a href="../../painel.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row g-0 h-100">
        <!-- PRODUTOS -->
        <div class="col-lg-8 col-md-7 col-sm-12 border-end h-100 d-flex flex-column bg-light">

            <!-- BUSCA -->
            <div class="p-3 border-bottom bg-white sticky-top">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-danger text-white border-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="buscaProduto" class="form-control border-0" placeholder="Buscar produto por nome, código interno ou EAN... (F3)">
                </div>
            </div>

            <!-- LISTA PRODUTOS -->
            <div id="listaProdutos" class="p-3 overflow-auto" style="flex-grow:1;">
                <div class="text-center text-muted mt-5">
                    <i class="bi bi-box-seam fs-1"></i>
                    <p class="mt-2">Pesquise um produto para iniciar a venda</p>
                </div>
            </div>
        </div>

        <!-- CARRINHO -->
        <div class="col-lg-4 col-md-5 col-sm-12 d-flex flex-column h-100 bg-white shadow-sm position-relative">

            <!-- TOPO -->
            <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-cart3 me-2 text-danger"></i>Carrinho
                </h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="verHistorico">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" id="limparCarrinho">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <!-- ITENS -->
            <div id="listaCarrinho" class="flex-grow-1 overflow-auto p-3">
                <div class="text-center text-muted mt-5">
                    <i class="bi bi-bag-x fs-1"></i>
                    <p class="mt-2">Nenhum item adicionado</p>
                </div>
            </div>

            <!-- TOTAL -->
            <div class="border-top p-3 bg-light position-sticky bottom-0">
                <div class="d-flex justify-content-between fw-semibold mb-2">
                    <span>Subtotal</span>
                    <span id="subtotal">R$ 0,00</span>
                </div>
                <div class="d-flex justify-content-between fw-bold fs-5">
                    <span>Total</span>
                    <span id="total">R$ 0,00</span>
                </div>
                <button id="finalizarVenda" class="btn btn-danger w-100 mt-3 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-credit-card me-2"></i>Finalizar Venda (F2)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FINALIZAR -->
<div class="modal fade" id="modalFinalizar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-credit-card-2-front me-2"></i>Finalizar Venda</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body bg-light">
        <form id="formFinalizar" class="row g-3">

          <div class="col-md-6">
            <label class="form-label fw-semibold">Cliente (opcional)</label>
            <input type="text" name="cliente" class="form-control" placeholder="Digite o nome do cliente">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Forma de Pagamento</label>
            <select name="forma_pagamento" id="forma_pagamento" class="form-select" required>
              <option value="Dinheiro">Dinheiro</option>
              <option value="Pix">Pix</option>
              <option value="Cartão">Cartão</option>
              <option value="Misto">Misto (2 formas)</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Valor Recebido (R$)</label>
            <input type="number" id="valorRecebido" class="form-control" step="0.01" min="0">
            <div class="mt-1 small text-success fw-semibold">Troco: <span id="trocoValor">R$ 0,00</span></div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Desconto / Acréscimo</label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input type="number" id="ajusteValor" class="form-control" value="0" step="0.01">
              <select id="tipoAjuste" class="form-select">
                <option value="desconto">Desconto</option>
                <option value="acrescimo">Acréscimo</option>
              </select>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Observações</label>
            <textarea name="observacoes" class="form-control" rows="2"
                      placeholder="Ex: Cliente pagou metade em PIX e metade em cartão..."></textarea>
          </div>

        </form>
      </div>

      <div class="modal-footer bg-white border-0 justify-content-between">
        <div class="fw-bold">Total Final: <span id="totalFinalModal" class="text-danger fs-5">R$ 0,00</span></div>
        <div>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" form="formFinalizar" class="btn btn-danger">
            <i class="bi bi-check2-circle me-2"></i>Confirmar Venda
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- HISTÓRICO DE VENDAS -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="painelHistorico">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title fw-bold"><i class="bi bi-clock-history me-2"></i>Histórico de Vendas</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <ul id="listaHistorico" class="list-group small"></ul>
  </div>
</div>

<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'] ?>/modulos/pdv/js/pdv.js?v=<?= time() ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const relogio = document.getElementById('relogio');
  setInterval(() => {
    const d = new Date();
    relogio.innerText = d.toLocaleTimeString('pt-BR', { hour12:false });
  }, 1000);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'F2') document.getElementById('finalizarVenda').click();
    if (e.key === 'F3') document.getElementById('buscaProduto').focus();
  });

  document.getElementById('verHistorico').addEventListener('click', () => {
    const offcanvas = new bootstrap.Offcanvas('#painelHistorico');
    offcanvas.show();
  });
});
</script>

<?php endContent(); ?>