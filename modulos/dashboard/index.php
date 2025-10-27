<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';
startContent();

/* =============================
   DASHBOARD PRO — REDDNEXT ERP
   ============================= */

$conn->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"); // segurança pra views/agrupamentos

// =============================
// KPIs — VENDAS / FINANCEIRO
// =============================

// Vendas hoje
$vendasHoje = $conn->query("
  SELECT COALESCE(SUM(valor_total),0) 
  FROM vendas 
  WHERE status='pago' AND DATE(data_venda)=CURDATE()
")->fetchColumn() ?: 0;

$qtdeVendasHoje = $conn->query("
  SELECT COUNT(*) FROM vendas 
  WHERE status='pago' AND DATE(data_venda)=CURDATE()
")->fetchColumn() ?: 0;

$ticketHoje = $qtdeVendasHoje > 0 ? $vendasHoje / $qtdeVendasHoje : 0;

// Vendas mês atual
$vendasMes = $conn->query("
  SELECT COALESCE(SUM(valor_total),0) 
  FROM vendas 
  WHERE status='pago' 
    AND MONTH(data_venda)=MONTH(CURDATE()) 
    AND YEAR(data_venda)=YEAR(CURDATE())
")->fetchColumn() ?: 0;

$qtdeVendasMes = $conn->query("
  SELECT COUNT(*) 
  FROM vendas 
  WHERE status='pago' 
    AND MONTH(data_venda)=MONTH(CURDATE()) 
    AND YEAR(data_venda)=YEAR(CURDATE())
")->fetchColumn() ?: 0;

$ticketMes = $qtdeVendasMes > 0 ? $vendasMes / $qtdeVendasMes : 0;

// Vendas mês anterior (comparativo)
$vendasMesAnt = $conn->query("
  SELECT COALESCE(SUM(valor_total),0) 
  FROM vendas 
  WHERE status='pago' 
    AND MONTH(data_venda)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(data_venda)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
")->fetchColumn() ?: 0;

$delta = function(float $atual, float $anterior): array {
  if ($anterior == 0 && $atual == 0) return [0, 'neutro'];
  if ($anterior == 0) return [100, 'up'];
  $pct = (($atual - $anterior) / $anterior) * 100;
  return [$pct, $pct >= 0 ? 'up' : 'down'];
};
list($vendasMesVarPct, $vendasMesVarDir) = $delta($vendasMes, $vendasMesAnt);

// Financeiro — mês atual
$receitaMes = $conn->query("
  SELECT COALESCE(SUM(valor),0) FROM financeiro 
  WHERE tipo='receita' AND status='pago'
    AND MONTH(data_lancamento)=MONTH(CURDATE())
    AND YEAR(data_lancamento)=YEAR(CURDATE())
")->fetchColumn() ?: 0;

$despesaMes = $conn->query("
  SELECT COALESCE(SUM(valor),0) FROM financeiro 
  WHERE tipo='despesa' AND status='pago'
    AND MONTH(data_lancamento)=MONTH(CURDATE())
    AND YEAR(data_lancamento)=YEAR(CURDATE())
")->fetchColumn() ?: 0;

$saldoMes = $receitaMes - $despesaMes;

// Clientes / Produtos
$totalClientes = $conn->query("SELECT COUNT(*) FROM clientes")->fetchColumn() ?: 0;
$totalProdutos = $conn->query("SELECT COUNT(*) FROM vendas_estoque WHERE ativo=1")->fetchColumn() ?: 0;

// Lucro estimado do mês = Σ((valor_unit - custo) * qtd) nas vendas pagas do mês
$lucroMes = $conn->query("
  SELECT COALESCE(SUM( (i.valor_unitario - e.preco_custo) * i.quantidade ),0) AS lucro
  FROM vendas_itens i
  JOIN vendas v ON v.id = i.venda_id AND v.status='pago'
  JOIN vendas_estoque e ON e.id = i.produto_id
  WHERE MONTH(v.data_venda)=MONTH(CURDATE())
    AND YEAR(v.data_venda)=YEAR(CURDATE())
")->fetchColumn() ?: 0;

// =============================
// SÉRIES — GRÁFICOS
// =============================

// Faturamento diário (últimos 30 dias)
$fat30 = $conn->query("
  SELECT DATE(v.data_venda) d, DATE_FORMAT(v.data_venda,'%d/%m') label, COALESCE(SUM(v.valor_total),0) total
  FROM vendas v
  WHERE v.status='pago'
    AND v.data_venda >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(v.data_venda)
  ORDER BY DATE(v.data_venda)
")->fetchAll(PDO::FETCH_ASSOC);

$labelsFat = array_column($fat30, 'label');
$valoresFat = array_map('floatval', array_column($fat30, 'total'));

// Meta dinâmica: média dos últimos 3 meses * 1.1
$metaMes = $conn->query("
  SELECT COALESCE(AVG(mensal),0)*1.10 FROM (
    SELECT YEAR(data_venda) y, MONTH(data_venda) m, SUM(valor_total) mensal
    FROM vendas
    WHERE status='pago'
      AND data_venda >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY y,m
  ) t
")->fetchColumn() ?: 0;

// Receitas x Despesas (30 dias)
$fluxo30 = $conn->query("
  SELECT DATE(f.data_lancamento) d, DATE_FORMAT(f.data_lancamento,'%d/%m') label,
         SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END) receitas,
         SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END) despesas
  FROM financeiro f
  WHERE f.status='pago'
    AND f.data_lancamento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(f.data_lancamento)
  ORDER BY DATE(f.data_lancamento)
")->fetchAll(PDO::FETCH_ASSOC);

$labelsFluxo = array_column($fluxo30, 'label');
$valReceitas = array_map('floatval', array_column($fluxo30, 'receitas'));
$valDespesas = array_map('floatval', array_column($fluxo30, 'despesas'));

// Saldo por conta (saldo_inicial ± lançamentos pagos)
$contas = $conn->query("
  SELECT 
    c.nome_exibicao,
    c.saldo_inicial
      + COALESCE((
          SELECT SUM(valor) FROM financeiro 
          WHERE status='pago' AND tipo='receita' AND conta=c.nome_exibicao
        ),0)
      - COALESCE((
          SELECT SUM(valor) FROM financeiro 
          WHERE status='pago' AND tipo='despesa' AND conta=c.nome_exibicao
        ),0) AS saldo_atual
  FROM financeiro_contas c
  WHERE c.ativo=1
  ORDER BY saldo_atual DESC
")->fetchAll(PDO::FETCH_ASSOC);
$labelsContas = array_column($contas, 'nome_exibicao');
$valoresContas = array_map('floatval', array_column($contas, 'saldo_atual'));

// Top 5 produtos
$topProdutos = $conn->query("
  SELECT e.nome, SUM(i.quantidade) qtd
  FROM vendas_itens i
  JOIN vendas v ON v.id=i.venda_id AND v.status='pago'
  JOIN vendas_estoque e ON e.id=i.produto_id
  GROUP BY e.nome
  ORDER BY qtd DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Top 5 clientes
$topClientes = $conn->query("
  SELECT COALESCE(c.nome,'Sem nome') cliente, SUM(v.valor_total) total
  FROM vendas v
  LEFT JOIN clientes c ON c.id=v.cliente_id
  WHERE v.status='pago'
  GROUP BY cliente
  ORDER BY total DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// =============================
// ALERTAS
// =============================

// A pagar / receber próximos 7 dias
$prox7 = $conn->query("
  SELECT id, tipo, descricao, valor, data_vencimento, status, conta
  FROM financeiro
  WHERE status='pendente'
    AND data_vencimento IS NOT NULL
    AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
  ORDER BY data_vencimento ASC, tipo DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Estoque crítico
$estoqueCritico = $conn->query("
  SELECT id, nome, estoque_atual, estoque_minimo
  FROM vendas_estoque
  WHERE ativo=1 AND estoque_atual <= estoque_minimo
  ORDER BY (estoque_atual - estoque_minimo) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Helpers visuais
function badgeDelta($pct, $dir) {
  $ico = $dir === 'up' ? 'bi-arrow-up-right' : ($dir==='down' ? 'bi-arrow-down-right' : 'bi-dash');
  $cls = $dir === 'up' ? 'success' : ($dir==='down' ? 'danger' : 'secondary');
  $val = number_format(abs($pct), 1, ',', '.').'%';
  return "<span class='badge bg-$cls'><i class='bi $ico me-1'></i>$val</span>";
}
?>
<div class="container-fluid mt-4">
  <h2 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>

  <!-- CARDS SUPERIORES -->
  <div class="row g-4 mb-2">
    <div class="col-lg-3 col-md-6">
      <div class="card border-0 shadow-sm p-3 text-white" style="background: linear-gradient(135deg,#0d6efd,#6610f2)">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-1"><i class="bi bi-calendar-day me-1"></i>Vendas Hoje</h6>
            <h3 class="fw-bold">R$ <?= number_format($vendasHoje,2,',','.') ?></h3>
            <small class="opacity-75">Ticket médio: R$ <?= number_format($ticketHoje,2,',','.') ?></small>
          </div>
          <i class="bi bi-cash-stack fs-1 opacity-75"></i>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="card border-0 shadow-sm p-3 bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-1"><i class="bi bi-calendar3 me-1"></i>Vendas no Mês</h6>
            <h3 class="fw-bold">R$ <?= number_format($vendasMes,2,',','.') ?></h3>
            <small><?= badgeDelta($vendasMesVarPct, $vendasMesVarDir) ?> vs mês anterior</small>
          </div>
          <i class="bi bi-graph-up-arrow fs-1 opacity-75"></i>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="card border-0 shadow-sm p-3 bg-danger text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-1"><i class="bi bi-receipt me-1"></i>Despesas Mês</h6>
            <h3 class="fw-bold">R$ <?= number_format($despesaMes,2,',','.') ?></h3>
            <small>Receita: R$ <?= number_format($receitaMes,2,',','.') ?></small>
          </div>
          <i class="bi bi-graph-down fs-1 opacity-75"></i>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="card border-0 shadow-sm p-3 bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-1"><i class="bi bi-wallet2 me-1"></i>Saldo do Mês</h6>
            <h3 class="fw-bold">R$ <?= number_format($saldoMes,2,',','.') ?></h3>
            <small>Lucro estimado: R$ <?= number_format($lucroMes,2,',','.') ?></small>
          </div>
          <i class="bi bi-safe2 fs-1 opacity-75"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- CARDS SECUNDÁRIOS -->
  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="mb-1"><i class="bi bi-people me-1"></i>Clientes</h6>
            <h3 class="fw-bold"><?= (int)$totalClientes ?></h3>
          </div>
          <i class="bi bi-people fs-1 text-muted"></i>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="mb-1"><i class="bi bi-box-seam me-1"></i>Produtos Ativos</h6>
            <h3 class="fw-bold"><?= (int)$totalProdutos ?></h3>
          </div>
          <i class="bi bi-box-seam fs-1 text-muted"></i>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex justify-content-between">
          <div>
            <h6 class="mb-1"><i class="bi bi-ticket-detailed me-1"></i>Ticket Médio (mês)</h6>
            <h3 class="fw-bold">R$ <?= number_format($ticketMes,2,',','.') ?></h3>
          </div>
          <i class="bi bi-receipt fs-1 text-muted"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- GRÁFICOS PRINCIPAIS -->
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-bar-chart me-1"></i>Faturamento Diário (30 dias)</h6>
          <canvas id="grafFat" height="130"></canvas>
          <small class="text-muted d-block mt-2">Meta diária baseada em média dos últimos 3 meses (x1.10)</small>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-graph-up me-1"></i>Receitas x Despesas (30 dias)</h6>
          <canvas id="grafFluxo" height="130"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- SALDO POR CONTA + RANKINGS -->
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-bank me-1"></i>Saldo por Conta</h6>
          <canvas id="grafContas" height="130"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-boxes me-1"></i>Top 5 Produtos</h6>
          <table class="table table-sm align-middle">
            <tbody>
              <?php if ($topProdutos): foreach ($topProdutos as $tp): ?>
                <tr>
                  <td><?= htmlspecialchars($tp['nome']) ?></td>
                  <td class="text-end"><?= number_format($tp['qtd'],2,',','.') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="2" class="text-muted text-center">Sem dados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-person-lines-fill me-1"></i>Top 5 Clientes</h6>
          <table class="table table-sm align-middle">
            <tbody>
              <?php if ($topClientes): foreach ($topClientes as $tc): ?>
                <tr>
                  <td><?= htmlspecialchars($tc['cliente']) ?></td>
                  <td class="text-end">R$ <?= number_format($tc['total'],2,',','.') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="2" class="text-muted text-center">Sem dados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ALERTAS OPERACIONAIS -->
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Contas a Pagar/Receber (Próx. 7 dias)</h6>
          <table class="table align-middle table-hover">
            <thead class="table-light">
              <tr>
                <th>Vencimento</th>
                <th>Tipo</th>
                <th>Descrição</th>
                <th class="text-end">Valor</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($prox7): foreach ($prox7 as $l): ?>
                <tr>
                  <td><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></td>
                  <td>
                    <?php if ($l['tipo']==='despesa'): ?>
                      <span class="badge bg-danger">Despesa</span>
                    <?php else: ?>
                      <span class="badge bg-success">Receita</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($l['descricao'] ?? '-') ?></td>
                  <td class="text-end">R$ <?= number_format($l['valor'],2,',','.') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-muted text-center">Sem vencimentos nos próximos 7 dias.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon me-1"></i>Estoque Crítico</h6>
          <table class="table align-middle table-hover">
            <thead class="table-light">
              <tr>
                <th>Produto</th>
                <th class="text-end">Atual</th>
                <th class="text-end">Mínimo</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($estoqueCritico): foreach ($estoqueCritico as $e): ?>
                <tr>
                  <td><?= htmlspecialchars($e['nome']) ?></td>
                  <td class="text-end"><?= number_format($e['estoque_atual'],2,',','.') ?></td>
                  <td class="text-end"><?= number_format($e['estoque_minimo'],2,',','.') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="3" class="text-muted text-center">Nenhum item crítico.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SCRIPTS GRÁFICOS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labelsFat = <?= json_encode($labelsFat) ?>;
const valoresFat = <?= json_encode($valoresFat) ?>;
const metaDiaria = labelsFat.map(_ => <?= json_encode(round(($metaMes/30),2)) ?>);

new Chart(document.getElementById('grafFat'), {
  type: 'bar',
  data: {
    labels: labelsFat,
    datasets: [
      {
        type: 'bar',
        label: 'Faturamento (R$)',
        data: valoresFat,
        backgroundColor: 'rgba(13,110,253,0.6)',
        borderColor: '#0d6efd',
        borderWidth: 1,
        borderRadius: 6
      },
      {
        type: 'line',
        label: 'Meta diária',
        data: metaDiaria,
        borderColor: '#6f42c1',
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 0,
        fill: false
      }
    ]
  },
  options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('grafFluxo'), {
  type: 'line',
  data: {
    labels: <?= json_encode($labelsFluxo) ?>,
    datasets: [
      { label: 'Receitas', data: <?= json_encode($valReceitas) ?>, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.2)', tension: .4, fill: true },
      { label: 'Despesas', data: <?= json_encode($valDespesas) ?>, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.2)', tension: .4, fill: true }
    ]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('grafContas'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labelsContas) ?>,
    datasets: [{
      label: 'Saldo (R$)',
      data: <?= json_encode($valoresContas) ?>,
      backgroundColor: 'rgba(13,202,240,0.6)',
      borderColor: '#0dcaf0',
      borderWidth: 1,
      borderRadius: 6
    }]
  },
  options: { indexAxis: 'y', responsive: true, scales: { x: { beginAtZero: true } }, plugins: { legend: { display: false } } }
});
</script>

<?php endContent(); ?>