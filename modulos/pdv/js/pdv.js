console.log("🧠 PDV NEXT - Reddnext Creative");

const API = `${window.location.origin}/modulos/pdv/actions.php`;
const fmt = v => (v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const el = s => document.querySelector(s);

let CARRINHO = [];
let TOTAL = 0;
let BUSCA_CACHE = {};
let DEBOUNCE;

// =================== CARRINHO ===================
function renderCarrinho() {
  const c = el("#listaCarrinho");
  if (!c) return;
  if (!CARRINHO.length) {
    c.innerHTML = `
      <div class="text-center text-muted mt-5">
        <i class="bi bi-bag-x fs-1"></i>
        <p>Nenhum item adicionado</p>
      </div>`;
    el("#subtotal").innerText = fmt(0);
    el("#total").innerText = fmt(0);
    TOTAL = 0;
    return;
  }

  let sub = 0;
  const html = CARRINHO.map((i, ix) => {
    const t = i.qtd * i.preco;
    sub += t;
    const lucro = i.custo > 0 ? ((i.preco - i.custo) / i.preco) * 100 : 0;
    return `
      <div class="pdv-item d-flex justify-content-between align-items-center border rounded p-2 mb-2 bg-light-subtle">
        <div class="fw-semibold small">
          ${i.nome}<br>
          <small class="text-muted">R$ ${i.preco.toFixed(2)} • Lucro ${lucro.toFixed(1)}%</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="width:110px">
            <button class="btn btn-outline-secondary" data-idx="${ix}" data-act="menos">-</button>
            <input class="form-control text-center" value="${i.qtd}" data-idx="${ix}" data-act="qtd">
            <button class="btn btn-outline-secondary" data-idx="${ix}" data-act="mais">+</button>
          </div>
          <div class="text-end small" style="width:90px">${fmt(t)}</div>
          <button class="btn btn-sm btn-outline-danger" data-idx="${ix}" data-act="remover">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>`;
  }).join("");

  c.innerHTML = html;
  el("#subtotal").innerText = fmt(sub);
  el("#total").innerText = fmt(sub);
  TOTAL = sub;
}

function adicionarAoCarrinho(p) {
  // Normaliza o payload vindo do backend
  const produto = {
    id: p.id,
    nome: p.nome,
    preco: +p.preco || +p.preco_venda || 0,
    custo: +p.preco_custo || 0,
    qtd: 1
  };

  if (!produto.id || !produto.preco) {
    console.error("Produto inválido recebido do backend:", p);
    alert("Não foi possível adicionar este produto. Verifique o cadastro (preço).");
    return;
  }

  const i = CARRINHO.findIndex(x => x.id === produto.id);
  if (i >= 0) CARRINHO[i].qtd++;
  else CARRINHO.push(produto);
  renderCarrinho();

  try {
    const audio = new Audio("https://assets.mixkit.co/sfx/preview/mixkit-game-click-1114.wav");
    audio.volume = 0.25;
    audio.play();
  } catch (_) {}
}

document.addEventListener("click", (e) => {
  const b = e.target.closest("[data-act]");
  if (!b) return;
  const a = b.dataset.act, i = +b.dataset.idx;
  if (a === "menos") CARRINHO[i].qtd = Math.max(1, CARRINHO[i].qtd - 1);
  else if (a === "mais") CARRINHO[i].qtd++;
  else if (a === "remover") CARRINHO.splice(i, 1);
  renderCarrinho();
});

el("#limparCarrinho")?.addEventListener("click", () => {
  if (!CARRINHO.length) return;
  if (confirm("Deseja limpar o carrinho?")) {
    CARRINHO = [];
    renderCarrinho();
  }
});

// =================== PRODUTOS ===================
function cardProduto(p) {
  const est = +p.estoque_atual || 0;
  const min = +p.estoque_minimo || 0;
  const alerta =
    est <= 0
      ? `<span class="badge bg-danger">Sem estoque</span>`
      : est <= min
      ? `<span class="badge bg-warning text-dark">Baixo estoque</span>`
      : "";
  const thumb = p.imagem_url
    ? `<img src="${p.imagem_url}" class="img-fluid object-fit-contain p-2">`
    : `<i class="bi bi-box text-muted fs-1"></i>`;
  return `
    <div class="card h-100 shadow-sm hover-shadow-sm">
      <div class="ratio ratio-4x3 pdv-thumb bg-white">${thumb}</div>
      <div class="card-body d-flex flex-column">
        <div class="fw-semibold small mb-1">${p.nome}</div>
        <div class="mb-2">${alerta}</div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="text-danger fw-bold">${fmt(+p.preco_venda)}</div>
          <small class="text-muted">Est.: ${est}</small>
        </div>
        <button class="btn btn-sm btn-danger mt-auto" data-add="${p.id}" ${est <= 0 ? "disabled" : ""}>
          <i class="bi bi-plus-circle me-1"></i>Adicionar
        </button>
      </div>
    </div>`;
}

function buscarProdutos() {
  const q = el("#buscaProduto")?.value?.trim() || "";
  const cont = el("#listaProdutos");

  if (!q) {
    cont.innerHTML = `<div class="text-center text-muted mt-5"><i class="bi bi-box-seam fs-1"></i><p>Pesquise um produto</p></div>`;
    return;
  }

  if (BUSCA_CACHE[q]) return renderProdutos(BUSCA_CACHE[q]);

  cont.innerHTML = `<div class="text-center text-muted mt-5"><div class="spinner-border"></div><p>Buscando...</p></div>`;

  fetch(`${API}?action=search_products`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ q })
  })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(j => {
      if (!j.ok) throw new Error(j.msg);
      BUSCA_CACHE[q] = j.data;
      renderProdutos(j.data);
    })
    .catch(e => cont.innerHTML = `<div class="alert alert-danger m-3">Erro: ${e}</div>`);
}

function renderProdutos(lista) {
  const cont = el("#listaProdutos");
  if (!lista.length) {
    cont.innerHTML = `<div class="text-center text-muted mt-5"><i class="bi bi-box-seam fs-1"></i><p>Nenhum produto encontrado</p></div>`;
    return;
  }
  cont.innerHTML = `
    <div class="row g-3">
      ${lista.map(p => `<div class="col-12 col-sm-6 col-md-4 col-lg-3">${cardProduto(p)}</div>`).join("")}
    </div>`;
}

el("#buscaProduto")?.addEventListener("input", () => {
  clearTimeout(DEBOUNCE);
  DEBOUNCE = setTimeout(buscarProdutos, 400);
});

el("#listaProdutos")?.addEventListener("click", (e) => {
  const b = e.target.closest("[data-add]");
  if (!b || b.disabled) return;
  const id = +b.dataset.add;
  fetch(`${API}?action=product_by_id`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id })
  })
    .then(r => r.json())
    .then(j => { if (j.ok) adicionarAoCarrinho(j.data); });
});

// =================== MODAL DE FINALIZAÇÃO ===================
el("#finalizarVenda")?.addEventListener("click", () => {
  if (!CARRINHO.length) return alert("Adicione itens ao carrinho.");
  const modal = new bootstrap.Modal(el("#modalFinalizar"));
  el("#totalFinalModal").innerText = fmt(TOTAL);
  el("#ajusteValor").value = 0;
  el("#valorRecebido").value = "";
  el("#trocoValor").innerText = fmt(0);
  modal.show();
  atualizarTotaisModal();
});

["#ajusteValor","#valorRecebido","#forma_pagamento","#tipoAjuste"].forEach(sel => {
  el(sel)?.addEventListener("input", atualizarTotaisModal);
  el(sel)?.addEventListener("change", atualizarTotaisModal);
});

function atualizarTotaisModal(){
  const valor = +el("#ajusteValor").value || 0;
  const tipo = el("#tipoAjuste").value;
  const totalFinal = Math.max(0, TOTAL + (tipo === "acrescimo" ? valor : -valor));
  el("#totalFinalModal").innerText = fmt(totalFinal);

  const recebido = +el("#valorRecebido").value || 0;
  const troco = Math.max(0, recebido - totalFinal);
  el("#trocoValor").innerText = fmt(troco);
}

el("#formFinalizar")?.addEventListener("submit", (e) => {
  e.preventDefault();
  if (!CARRINHO.length) return;

  const payload = {
    cliente: el("#cliente")?.value?.trim() || "",
    forma_pagamento: el("#forma_pagamento")?.value || "Dinheiro",
    observacoes: el("#observacoes")?.value || "",
    ajuste_valor: +el("#ajusteValor").value || 0,
    ajuste_tipo: el("#tipoAjuste")?.value || "desconto",
    itens: CARRINHO.map(i => ({ produto_id: i.id, qtd: i.qtd, preco_unit: i.preco }))
  };

  fetch(`${API}?action=finalize_sale`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
    .then(r => r.json())
    .then(j => {
      if (!j.ok) throw new Error(j.msg);
      CARRINHO = [];
      renderCarrinho();
      bootstrap.Modal.getInstance(el("#modalFinalizar")).hide();
      alert(`✅ Venda #${j.sale_id} registrada com sucesso!`);
    })
    .catch(err => alert("Erro: " + err.message));
});

// =================== HISTÓRICO DE VENDAS ===================
el("#verHistorico")?.addEventListener("click", async () => {
  const offcanvas = new bootstrap.Offcanvas("#painelHistorico");
  offcanvas.show();
  const lista = el("#listaHistorico");
  lista.innerHTML = `<div class="text-center text-muted"><div class="spinner-border"></div></div>`;
  const res = await fetch(`${API}?action=ultimas_vendas`);
  const data = await res.json();
  lista.innerHTML = (data.data || []).map(v => `
    <li class="list-group-item d-flex justify-content-between align-items-center">
      <div><strong>#${v.id}</strong> - ${v.cliente || 'Sem cliente'}<br><small>${v.data}</small></div>
      <span class="fw-bold text-danger">${fmt(v.total)}</span>
    </li>`).join("");
});

// =================== RELÓGIO & ATALHOS ===================
document.addEventListener("DOMContentLoaded", () => {
  setInterval(() => {
    const d = new Date();
    el("#relogio").innerText = d.toLocaleTimeString("pt-BR", { hour12: false });
  }, 1000);

  document.addEventListener("keydown", (e) => {
    if (e.key === "F2") el("#finalizarVenda").click();
    if (e.key === "F3") el("#buscaProduto").focus();
    if (e.key === "F4") el("#limparCarrinho").click();
  });

  buscarProdutos(); // inicial
});