<?php
include '../../core/init.php';
include '../../core/auth.php';
include '../../core/layout.php';

function fmt($v){ return number_format((float)$v, 2, ',', '.'); }

startContent();

/* ============================================================
   FINANCEIRO PRO — v4.1 (Visão Executiva Primeiro)
   Ordem: KPIs → Filtros → Tabela → Gráficos → Detalhes (toggle)
   ============================================================ */

// ====== FILTROS ======
$fInicio  = $_GET['inicio']    ?? date('Y-m-01');
$fFim     = $_GET['fim']       ?? date('Y-m-t');
$fTipo    = $_GET['tipo']      ?? '';                // receita | despesa | ''
$fStatus  = $_GET['status']    ?? '';                // pago | pendente | cancelado | ''
$fConta   = $_GET['conta']     ?? '';                // nome_exibicao
$fCat     = $_GET['categoria'] ?? '';
$fForma   = $_GET['forma']     ?? '';
$export   = $_GET['export']    ?? '';                // csv | ''

$where = ["data_lancamento BETWEEN :ini AND :fim"];
$params = [':ini' => $fInicio, ':fim' => $fFim];

if ($fTipo   && in_array($fTipo,   ['receita','despesa']))           { $where[] = "tipo = :tipo"; $params[':tipo']   = $fTipo; }
if ($fStatus && in_array($fStatus, ['pago','pendente','cancelado'])) { $where[] = "status = :status"; $params[':status'] = $fStatus; }
if ($fConta  !== '')                                                 { $where[] = "conta = :conta"; $params[':conta'] = $fConta; }
if ($fCat    !== '')                                                 { $where[] = "categoria = :cat"; $params[':cat']  = $fCat; }
if ($fForma  !== '')                                                 { $where[] = "forma_pagamento = :forma"; $params[':forma'] = $fForma; }

$whereSQL = implode(' AND ', $where);

// ====== COMBOS ======
$contas = $conn->query("SELECT nome_exibicao FROM financeiro_contas WHERE ativo=1 ORDER BY nome_exibicao")->fetchAll(PDO::FETCH_COLUMN);
$cats   = $conn->query("SELECT DISTINCT categoria FROM financeiro WHERE categoria IS NOT NULL AND categoria<>'' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
$formas = $conn->query("SELECT DISTINCT forma_pagamento FROM financeiro WHERE forma_pagamento IS NOT NULL AND forma_pagamento<>'' ORDER BY forma_pagamento")->fetchAll(PDO::FETCH_COLUMN);

// ====== EXPORT CSV (filtro atual) ======
if ($export === 'csv') {
  $sqlCSV = "SELECT data_lancamento, data_vencimento, status, tipo, categoria, descricao, forma_pagamento, conta, valor, referencia_tipo, referencia_id
             FROM financeiro WHERE $whereSQL ORDER BY data_lancamento ASC, id ASC";
  $stCSV=$conn->prepare($sqlCSV); $stCSV->execute($params); $rows=$stCSV->fetchAll(PDO::FETCH_ASSOC);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=financeiro_'.date('Ymd_His').'.csv');
  $out=fopen('php://output','w');
  fputcsv($out,['Data Lançamento','Data Vencimento','Status','Tipo','Categoria','Descrição','Forma Pagamento','Conta','Valor','Referência']);
  foreach($rows as $r){
    fputcsv($out,[
      $r['data_lancamento'],
      $r['data_vencimento'],
      $r['status'],
      strtoupper($r['tipo']),
      $r['categoria'],
      $r['descricao'],
      $r['forma_pagamento'],
      $r['conta'],
      number_format((float)$r['valor'],2,'.',''),
      $r['referencia_tipo'] ? ($r['referencia_tipo'].' #'.$r['referencia_id']) : 'Manual'
    ]);
  }
  fclose($out); exit;
}

// ====== LISTAGEM (base para a tabela) ======
$sqlList = "SELECT * FROM financeiro WHERE $whereSQL ORDER BY data_lancamento DESC, id DESC";
$stList = $conn->prepare($sqlList); $stList->execute($params);
$lancamentos = $stList->fetchAll(PDO::FETCH_ASSOC);

// ====== KPIs (período filtrado | pagos) ======
$sum = function($extra='') use ($conn,$whereSQL,$params){
  $sql = "SELECT COALESCE(SUM(valor),0) FROM financeiro WHERE $whereSQL $extra";
  $st  = $conn->prepare($sql); $st->execute($params);
  return (float)($st->fetchColumn() ?: 0);
};
$totalReceitas = $sum("AND tipo='receita' AND status='pago'");
$totalDespesas = $sum("AND tipo='despesa' AND status='pago'");
$saldoPeriodo  = $totalReceitas - $totalDespesas;

// ====== Comparativo Mensal (pago) ======
function somaMes($tipo,$offset=0){
  global $conn;
  $q=$conn->prepare("SELECT COALESCE(SUM(valor),0) FROM financeiro WHERE tipo=? AND status='pago'
    AND MONTH(data_lancamento)=MONTH(DATE_SUB(CURDATE(), INTERVAL ? MONTH))
    AND YEAR(data_lancamento)=YEAR(DATE_SUB(CURDATE(), INTERVAL ? MONTH))");
  $q->execute([$tipo,$offset,$offset]); return (float)$q->fetchColumn();
}
$recMes=somaMes('receita',0); $desMes=somaMes('despesa',0); $salMes=$recMes-$desMes;
$recAnt=somaMes('receita',1); $desAnt=somaMes('despesa',1); $salAnt=$recAnt-$desAnt;
$delta=function($a,$b){ if($b==0&&$a==0)return[0,'neutro']; if($b==0)return[100,'up']; $p=(($a-$b)/$b)*100; return[$p,$p>=0?'up':'down']; };
list($vRec,$dRec)=$delta($recMes,$recAnt);
list($vDes,$dDes)=$delta($desMes,$desAnt);
list($vSal,$dSal)=$delta($salMes,$salAnt);

// ====== Previsão 7 dias (pendentes) ======
$prev = $conn->query("
  SELECT SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END) rec,
         SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END) des
  FROM financeiro
  WHERE status='pendente'
    AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC) ?: ['rec'=>0,'des'=>0];
$aReceber=(float)$prev['rec']; $aPagar=(float)$prev['des']; $prev7=$aReceber-$aPagar;

// ====== GRÁFICOS (filtrados e ocultáveis) ======
// Fluxo (pago) no período filtrado
$stFluxo=$conn->prepare("
  SELECT DATE_FORMAT(data_lancamento,'%d/%m') dia,
         SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END) receita,
         SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END) despesa
  FROM financeiro
  WHERE $whereSQL AND status='pago'
  GROUP BY dia
  ORDER BY MIN(data_lancamento)
"); $stFluxo->execute($params);
$fluxoRows=$stFluxo->fetchAll(PDO::FETCH_ASSOC);
$lblFluxo=array_column($fluxoRows,'dia');
$recFluxo=array_map('floatval', array_column($fluxoRows,'receita'));
$desFluxo=array_map('floatval', array_column($fluxoRows,'despesa'));

// Pizza despesas por categoria (pago)
$stPie=$conn->prepare("
  SELECT categoria, SUM(valor) total
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa' AND status='pago'
  GROUP BY categoria
  ORDER BY total DESC
"); $stPie->execute($params);
$pieRows=$stPie->fetchAll(PDO::FETCH_ASSOC);
$pieLabels=array_map(fn($r)=>$r['categoria']?:'Sem categoria',$pieRows);
$pieValues=array_map('floatval', array_column($pieRows,'total'));

// Saldo por conta (visão consolidada)
$contasSaldo=$conn->query("
  SELECT c.nome_exibicao,
         c.saldo_inicial
         + COALESCE((SELECT SUM(valor) FROM financeiro WHERE tipo='receita' AND status='pago' AND conta=c.nome_exibicao),0)
         - COALESCE((SELECT SUM(valor) FROM financeiro WHERE tipo='despesa' AND status='pago' AND conta=c.nome_exibicao),0) AS saldo
  FROM financeiro_contas c
  WHERE c.ativo=1
  ORDER BY saldo DESC
")->fetchAll(PDO::FETCH_ASSOC);
$cLabels=array_column($contasSaldo,'nome_exibicao');
$cValues=array_map('floatval', array_column($contasSaldo,'saldo'));

// ====== RELATÓRIOS (para a área “Ver mais detalhes”) ======
// Por dia
$stGastoDia=$conn->prepare("
  SELECT DATE(data_lancamento) d, DATE_FORMAT(data_lancamento,'%d/%m/%Y') label,
         COUNT(*) qtd, SUM(valor) total
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa' AND status<>'cancelado'
  GROUP BY d ORDER BY d
"); $stGastoDia->execute($params);
$gastoDia=$stGastoDia->fetchAll(PDO::FETCH_ASSOC);

// Por categoria
$stGastoCat=$conn->prepare("
  SELECT categoria, COUNT(*) qtd, SUM(valor) total
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa' AND status<>'cancelado'
  GROUP BY categoria ORDER BY total DESC
"); $stGastoCat->execute($params);
$gastoCat=$stGastoCat->fetchAll(PDO::FETCH_ASSOC);

// Por categoria x forma
$stGastoCatForma=$conn->prepare("
  SELECT categoria, forma_pagamento, COUNT(*) qtd, SUM(valor) total
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa' AND status<>'cancelado'
  GROUP BY categoria, forma_pagamento
  ORDER BY categoria, total DESC
"); $stGastoCatForma->execute($params);
$gastoCatForma=$stGastoCatForma->fetchAll(PDO::FETCH_ASSOC);

// Por conta
$stGastoConta=$conn->prepare("
  SELECT conta, COUNT(*) qtd, SUM(valor) total
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa' AND status<>'cancelado'
  GROUP BY conta ORDER BY total DESC
"); $stGastoConta->execute($params);
$gastoConta=$stGastoConta->fetchAll(PDO::FETCH_ASSOC);

// Por status
$stGastoStatus=$conn->prepare("
  SELECT status, COUNT(*) qtd, SUM(valor) total
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa'
  GROUP BY status
"); $stGastoStatus->execute($params);
$gastoStatus=$stGastoStatus->fetchAll(PDO::FETCH_ASSOC);

// Top 10 maiores gastos
$stTop10=$conn->prepare("
  SELECT id, data_lancamento, categoria, descricao, forma_pagamento, conta, status, valor, referencia_tipo, referencia_id
  FROM financeiro
  WHERE $whereSQL AND tipo='despesa'
  ORDER BY valor DESC
  LIMIT 10
"); $stTop10->execute($params);
$top10=$stTop10->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">

  <!-- Cabeçalho -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Financeiro</h4>
    <div class="d-flex gap-2">
      <a href="editar.php" class="btn btn-danger"><i class="bi bi-plus-circle me-1"></i>Novo Lançamento</a>
      <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>">
        <i class="bi bi-filetype-csv me-1"></i> Exportar CSV (filtro atual)
      </a>
    </div>
  </div>

  <!-- KPIs no TOPO (visão executiva) -->
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card p-3 bg-success text-white shadow-sm">
        <h6 class="mb-1"><i class="bi bi-arrow-up-circle"></i> Receitas (pagas)</h6>
        <h4 class="fw-bold mb-0">R$ <?= fmt($totalReceitas) ?></h4>
        <small class="text-white-50">Período: <?=date('d/m/Y',strtotime($fInicio))?>–<?=date('d/m/Y',strtotime($fFim))?></small>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 bg-danger text-white shadow-sm">
        <h6 class="mb-1"><i class="bi bi-arrow-down-circle"></i> Despesas (pagas)</h6>
        <h4 class="fw-bold mb-0">R$ <?= fmt($totalDespesas) ?></h4>
        <small class="text-white-50">Período: <?=date('d/m/Y',strtotime($fInicio))?>–<?=date('d/m/Y',strtotime($fFim))?></small>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 bg-primary text-white shadow-sm">
        <h6 class="mb-1"><i class="bi bi-wallet2"></i> Saldo (período)</h6>
        <h4 class="fw-bold mb-0">R$ <?= fmt($saldoPeriodo) ?></h4>
        <small class="text-white-50"><?= $saldoPeriodo>=0?'Positivo':'Negativo' ?></small>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card p-3 shadow-sm">
        <h6 class="mb-1"><i class="bi bi-calendar-event"></i> Previsão (7 dias)</h6>
        <h4 class="fw-bold mb-0 <?= $prev7>=0?'text-success':'text-danger' ?>">R$ <?= fmt($prev7) ?></h4>
        <small class="text-muted">A receber: R$ <?=fmt($aReceber)?> | A pagar: R$ <?=fmt($aPagar)?></small>
      </div>
    </div>
  </div>

  <!-- FILTROS (logo abaixo dos KPIs) -->
  <form method="get" class="row g-2 align-items-end bg-light p-3 rounded shadow-sm mb-3">
    <div class="col-md-2"><label class="form-label">Início</label><input type="date" name="inicio" value="<?=htmlspecialchars($fInicio)?>" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Fim</label><input type="date" name="fim" value="<?=htmlspecialchars($fFim)?>" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Tipo</label>
      <select name="tipo" class="form-select">
        <option value="">Todos</option>
        <option value="receita" <?=$fTipo==='receita'?'selected':''?>>Receita</option>
        <option value="despesa" <?=$fTipo==='despesa'?'selected':''?>>Despesa</option>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">Todos</option>
        <option value="pago" <?=$fStatus==='pago'?'selected':''?>>Pago</option>
        <option value="pendente" <?=$fStatus==='pendente'?'selected':''?>>Pendente</option>
        <option value="cancelado" <?=$fStatus==='cancelado'?'selected':''?>>Cancelado</option>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label">Conta</label>
      <select name="conta" class="form-select">
        <option value="">Todas</option>
        <?php foreach($contas as $c): ?>
          <option value="<?=htmlspecialchars($c)?>" <?=$fConta===$c?'selected':''?>><?=htmlspecialchars($c)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label">Categoria</label>
      <select name="categoria" class="form-select">
        <option value="">Todas</option>
        <?php foreach($cats as $c): ?>
          <option value="<?=htmlspecialchars($c)?>" <?=$fCat===$c?'selected':''?>><?=htmlspecialchars($c)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label">Forma</label>
      <select name="forma" class="form-select">
        <option value="">Todas</option>
        <?php foreach($formas as $f): ?>
          <option value="<?=htmlspecialchars($f)?>" <?=$fForma===$f?'selected':''?>><?=htmlspecialchars($f)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-dark w-100"><i class="bi bi-funnel"></i> Filtrar</button>
    </div>
    <div class="col-md-2">
      <!-- Toggle sem reload -->
      <button type="button" id="btnToggleDetalhes" class="btn btn-outline-primary w-100">
        <i class="bi bi-eye"></i> Ver mais detalhes
      </button>
    </div>
    <div class="col-12 text-muted small">
      Exibindo <strong><?=count($lancamentos)?></strong> lançamentos de <strong><?=date('d/m/Y',strtotime($fInicio))?></strong> a <strong><?=date('d/m/Y',strtotime($fFim))?></strong>.
    </div>
  </form>

  <!-- TABELA (após filtros) -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-body table-responsive">
      <h6 class="fw-bold mb-3"><i class="bi bi-list-ul me-1"></i> Lançamentos</h6>
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrição</th>
            <th>Forma</th><th>Conta</th>
            <th class="text-end">Valor</th><th>Status</th><th width="95">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($lancamentos): foreach ($lancamentos as $l): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
              <td><?= $l['tipo']=='receita' ? '<span class="badge bg-success">Receita</span>' : '<span class="badge bg-danger">Despesa</span>' ?></td>
              <td><?= htmlspecialchars($l['categoria'] ?? '-') ?></td>
              <td><?= htmlspecialchars($l['descricao'] ?? '-') ?></td>
              <td><?= htmlspecialchars($l['forma_pagamento'] ?? '-') ?></td>
              <td><?= htmlspecialchars($l['conta'] ?? '-') ?></td>
              <td class="text-end <?= $l['tipo']=='despesa'?'text-danger':'text-success' ?>">R$ <?= fmt($l['valor']) ?></td>
              <td><span class="badge bg-<?= ($l['status']=='pago'?'success':($l['status']=='pendente'?'warning text-dark':'secondary')) ?>"><?= ucfirst($l['status']) ?></span></td>
              <td class="text-nowrap">
                <?php if (!empty($l['referencia_tipo'])): // BLOQUEIO DE EDIÇÃO/EXCLUSÃO PARA AUTOMÁTICOS ?>
                  <button class="btn btn-sm btn-outline-secondary" title="Lançamento automático (<?=htmlspecialchars($l['referencia_tipo'])?>)" disabled>
                    <i class="bi bi-lock"></i>
                  </button>
                  <?php if ($l['referencia_tipo']==='venda' && $l['referencia_id']): ?>
                    <a class="btn btn-sm btn-outline-info" title="Ver Venda #<?= (int)$l['referencia_id'] ?>" href="../vendas/editar.php?id=<?= (int)$l['referencia_id'] ?>">
                      <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                  <?php endif; ?>
                <?php else: ?>
                  <a href="editar.php?id=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                  <a href="excluir.php?id=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirm('Excluir este lançamento?')"><i class="bi bi-trash"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="9" class="text-center text-muted py-3">Nenhum lançamento neste filtro.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- GRÁFICOS (somem se não houver dados) -->
  <div class="row g-4 mb-4">
    <?php if(array_sum($pieValues)>0):?>
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-pie-chart me-1"></i> Despesas por Categoria (pagas)</h6>
          <canvas id="grafPie" height="110"></canvas>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if(array_sum($recFluxo)+array_sum($desFluxo)>0):?>
    <div class="col-lg-6">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="fw-bold mb-2"><i class="bi bi-activity me-1"></i> Fluxo no período (pagas)</h6>
          <canvas id="grafFluxo" height="110"></canvas>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- SEÇÃO DETALHES (oculta por padrão; toggle sem reload) -->
  <div id="secDetalhes" class="d-none">
    <h5 class="fw-bold mt-2 mb-3"><i class="bi bi-search me-1"></i> Detalhamento de Gastos</h5>

    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="bi bi-calendar2-week me-1"></i> Por Dia</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Data</th><th class="text-end">QTD</th><th class="text-end">Total (R$)</th></tr></thead>
                <tbody>
                  <?php if($gastoDia): foreach($gastoDia as $r): ?>
                  <tr><td><?=htmlspecialchars($r['label'])?></td><td class="text-end"><?=$r['qtd']?></td><td class="text-end">R$ <?=fmt($r['total'])?></td></tr>
                  <?php endforeach; else: ?><tr><td colspan="3" class="text-center text-muted">Sem gastos no filtro.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>

            <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-building me-1"></i> Por Conta</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Conta</th><th class="text-end">QTD</th><th class="text-end">Total (R$)</th></tr></thead>
                <tbody>
                  <?php if($gastoConta): foreach($gastoConta as $r): ?>
                  <tr><td><?=htmlspecialchars($r['conta']?:'-')?></td><td class="text-end"><?=$r['qtd']?></td><td class="text-end">R$ <?=fmt($r['total'])?></td></tr>
                  <?php endforeach; else: ?><tr><td colspan="3" class="text-center text-muted">Sem gastos no filtro.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="bi bi-tags me-1"></i> Por Categoria</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Categoria</th><th class="text-end">QTD</th><th class="text-end">Total (R$)</th></tr></thead>
                <tbody>
                  <?php if($gastoCat): foreach($gastoCat as $r): ?>
                  <tr><td><?=htmlspecialchars($r['categoria']?:'Sem categoria')?></td><td class="text-end"><?=$r['qtd']?></td><td class="text-end">R$ <?=fmt($r['total'])?></td></tr>
                  <?php endforeach; else: ?><tr><td colspan="3" class="text-center text-muted">Sem gastos no filtro.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>

            <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-diagram-3 me-1"></i> Categoria × Forma</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Categoria</th><th>Forma</th><th class="text-end">QTD</th><th class="text-end">Total (R$)</th></tr></thead>
                <tbody>
                  <?php if($gastoCatForma): foreach($gastoCatForma as $r): ?>
                  <tr><td><?=htmlspecialchars($r['categoria']?:'Sem categoria')?></td><td><?=htmlspecialchars($r['forma_pagamento']?:'-')?></td><td class="text-end"><?=$r['qtd']?></td><td class="text-end">R$ <?=fmt($r['total'])?></td></tr>
                  <?php endforeach; else: ?><tr><td colspan="4" class="text-center text-muted">Sem gastos no filtro.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>

            <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-flag me-1"></i> Por Status</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Status</th><th class="text-end">QTD</th><th class="text-end">Total (R$)</th></tr></thead>
                <tbody>
                  <?php if($gastoStatus): foreach($gastoStatus as $r): ?>
                  <tr><td><?=htmlspecialchars(ucfirst($r['status']))?></td><td class="text-end"><?=$r['qtd']?></td><td class="text-end">R$ <?=fmt($r['total'])?></td></tr>
                  <?php endforeach; else: ?><tr><td colspan="3" class="text-center text-muted">Sem gastos no filtro.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>

            <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-trophy me-1"></i> Top 10 Maiores Gastos</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Forma</th><th>Conta</th><th>Status</th><th class="text-end">Valor (R$)</th></tr></thead>
                <tbody>
                  <?php if($top10): foreach($top10 as $r): ?>
                  <tr>
                    <td><?=date('d/m/Y',strtotime($r['data_lancamento']))?></td>
                    <td><?=htmlspecialchars($r['categoria']?:'-')?></td>
                    <td><?=htmlspecialchars($r['descricao']?:'-')?></td>
                    <td><?=htmlspecialchars($r['forma_pagamento']?:'-')?></td>
                    <td><?=htmlspecialchars($r['conta']?:'-')?></td>
                    <td><span class="badge bg-<?= ($r['status']=='pago'?'success':($r['status']=='pendente'?'warning text-dark':'secondary')) ?>"><?=ucfirst($r['status'])?></span></td>
                    <td class="text-end">R$ <?=fmt($r['valor'])?></td>
                  </tr>
                  <?php endforeach; else: ?><tr><td colspan="7" class="text-center text-muted">Sem gastos no filtro.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div><!-- /row -->
  </div><!-- /secDetalhes -->

</div>

<!-- CHARTS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if(array_sum($pieValues)>0):?>
new Chart(document.getElementById('grafPie'),{
  type:'doughnut',
  data:{
    labels: <?=json_encode($pieLabels, JSON_UNESCAPED_UNICODE)?>,
    datasets:[{data: <?=json_encode($pieValues)?>, backgroundColor:['#dc3545','#fd7e14','#ffc107','#20c997','#0d6efd','#6f42c1','#adb5bd','#198754','#0dcaf0','#6610f2'] }]
  },
  options:{plugins:{legend:{position:'right'}}}
});
<?php endif; ?>

<?php if(array_sum($recFluxo)+array_sum($desFluxo)>0):?>
new Chart(document.getElementById('grafFluxo'),{
  type:'bar',
  data:{
    labels: <?=json_encode($lblFluxo)?>,
    datasets:[
      {label:'Receitas', data: <?=json_encode($recFluxo)?>, backgroundColor:'rgba(25,135,84,0.6)', borderColor:'#198754', borderWidth:1},
      {label:'Despesas', data: <?=json_encode($desFluxo)?>, backgroundColor:'rgba(220,53,69,0.6)', borderColor:'#dc3545', borderWidth:1}
    ]
  },
  options:{responsive:true, scales:{y:{beginAtZero:true}}, plugins:{legend:{position:'bottom'}}}
});
<?php endif; ?>
</script>

<!-- TOGGLE DETALHES (sem reload + persistência local) -->
<script>
const KEY = 'financeiro_modo_detalhes'; // 'on' | 'off'
const sec  = document.getElementById('secDetalhes');
const btn  = document.getElementById('btnToggleDetalhes');

function applyState(state){
  const on = state === 'on';
  if(on){ sec.classList.remove('d-none'); btn.innerHTML = '<i class="bi bi-eye-slash"></i> Ocultar detalhes'; localStorage.setItem(KEY,'on'); }
  else { sec.classList.add('d-none');    btn.innerHTML = '<i class="bi bi-eye"></i> Ver mais detalhes';     localStorage.setItem(KEY,'off'); }
}
btn.addEventListener('click', ()=>{
  const current = localStorage.getItem(KEY) ?? 'off';
  applyState(current === 'on' ? 'off' : 'on');
});
// aplica estado salvo ao carregar (padrão compacto)
applyState(localStorage.getItem(KEY) ?? 'off');
</script>

<?php endContent(); ?>