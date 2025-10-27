<?php
include '../../../core/init.php';
include '../../../core/auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('Orçamento inválido.');

// =========================
// Buscar dados do orçamento
// =========================
$stmt = $conn->prepare("SELECT * FROM vendas_orcamentos WHERE id = ?");
$stmt->execute([$id]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orc) die('Orçamento não encontrado.');

// =========================
// Buscar itens
// =========================
$stmtItens = $conn->prepare("
    SELECT i.*, p.nome, p.tipo_unidade
    FROM vendas_orcamentos_itens i
    LEFT JOIN vendas_estoque p ON p.id = i.produto_id
    WHERE i.orcamento_id = ?
");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

// =========================
// Buscar logo e configs
// =========================
$config = $conn->query("
    SELECT nome_empresa, telefone, email, cidade, uf, logo, timezone 
    FROM config_sistema 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$empresa = htmlspecialchars($config['nome_empresa'] ?? 'Minha Empresa');
$cabecalhoContato = trim(($config['telefone'] ?? '') . ' • ' . ($config['email'] ?? ''));
$timezone = $config['timezone'] ?? 'America/Sao_Paulo';

// Ajustar timezone para exibição correta
$dt = new DateTime($orc['criado_em']);
$dt->setTimezone(new DateTimeZone($timezone));
$dataEmissao = $dt->format('d/m/Y H:i');

// Carregar logo (com fallback seguro)
$logoBase64 = '';
if (!empty($config['logo'])) {
    $logoPath = __DIR__ . '/../../configuracoes/' . $config['logo'];
    if (file_exists($logoPath)) {
        $type = pathinfo($logoPath, PATHINFO_EXTENSION);
        $data = file_get_contents($logoPath);
        $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

// =========================
// Montar HTML do PDF
// =========================
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Orçamento #<?= $id ?></title>
<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    color: #333;
    margin: 20px;
}
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #dc3545;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.header img {
    max-height: 100px; /* aumento da logo */
    width: auto;
}
h2 {
    color: #dc3545;
    margin: 0;
}
.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.table th, .table td {
    border: 1px solid #ddd;
    padding: 6px;
    text-align: left;
}
.table th {
    background: #f8f9fa;
    color: #111;
}
.totais {
    text-align: right;
    margin-top: 15px;
}
.observacoes {
    margin-top: 20px;
    font-size: 12px;
}
.footer {
    margin-top: 30px;
    border-top: 1px solid #ccc;
    font-size: 11px;
    color: #666;
    text-align: center;
    padding-top: 10px;
}
</style>
</head>
<body>

<div class="header">
    <div>
        <h2><?= $empresa ?></h2>
        <div><?= htmlspecialchars($cabecalhoContato) ?><br>
        <?= htmlspecialchars(($config['cidade'] ?? '') . ' - ' . ($config['uf'] ?? '')) ?></div>
    </div>
    <?php if ($logoBase64): ?>
        <img src="<?= $logoBase64 ?>" alt="Logo">
    <?php endif; ?>
</div>

<h3>Orçamento #<?= $id ?></h3>
<p><strong>Cliente:</strong> <?= htmlspecialchars($orc['cliente_nome']) ?><br>
<strong>Telefone:</strong> <?= htmlspecialchars($orc['cliente_telefone']) ?><br>
<strong>Validade:</strong> <?= $orc['validade'] ? date('d/m/Y', strtotime($orc['validade'])) : '-' ?><br>
<strong>Data de Emissão:</strong> <?= $dataEmissao ?></p>

<table class="table">
    <thead>
        <tr>
            <th>Produto</th>
            <th style="width:15%">Unid.</th>
            <th style="width:15%">Qtd</th>
            <th style="width:15%">Preço (R$)</th>
            <th style="width:15%">Subtotal (R$)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($itens as $item): ?>
        <tr>
            <td><?= htmlspecialchars($item['nome']) ?></td>
            <td><?= htmlspecialchars($item['tipo_unidade'] ?? 'UN') ?></td>
            <td><?= number_format($item['quantidade'], 3, ',', '.') ?></td>
            <td><?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
            <td><?= number_format($item['subtotal'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="totais">
    <?php if ($orc['desconto'] > 0): ?>
        <p>Desconto aplicado: <strong><?= number_format($orc['desconto'], 2, ',', '.') ?>%</strong></p>
    <?php endif; ?>
    <h3>Total: R$ <?= number_format($orc['total'], 2, ',', '.') ?></h3>
</div>

<?php if (!empty($orc['observacoes'])): ?>
<div class="observacoes">
    <strong>Observações:</strong><br>
    <?= nl2br(htmlspecialchars($orc['observacoes'])) ?>
</div>
<?php endif; ?>

<div class="footer">
    <p>Gerado automaticamente pelo sistema em <?= date('d/m/Y H:i') ?>.</p>
    <p>Este orçamento é válido até <?= $orc['validade'] ? date('d/m/Y', strtotime($orc['validade'])) : '-' ?>.</p>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// =========================
// Gerar PDF (Dompdf)
// =========================
require_once '../../../vendor/autoload.php';
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("orcamento_{$id}.pdf", ["Attachment" => false]);
exit;
?>