<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/distribuicao.php';
require_once __DIR__ . '/lib/despesas.php';
$u = require_login();
if (!is_admin()) { http_response_code(403); exit('Apenas sócios.'); }
$db = db();

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

$socios = socios_ativos($db);
$n_socios = count($socios);
$total_quotas = $n_socios + 1; // +1 empresa

$rec_mes   = receita_mes($db, $competencia);
$rec_total = receita_por_moeda($db); // tudo
$desp_mes  = despesas_do_mes($db, $competencia);

// Pagamentos a funcionários no mês (USD)
$ini_m = $competencia . '-01';
$fim_m = date('Y-m-t', strtotime($ini_m));
$pag_func_mes = 0.0;
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE data_pagamento BETWEEN ? AND ?");
    $stmt->execute([$ini_m, $fim_m]);
    $pag_func_mes = (float)$stmt->fetchColumn();
} catch (PDOException $e) {}

// Lucro líquido = receita - despesas - pagamentos a funcionários (USD)
$liq_mes = [];
foreach (['BRL','USD','EUR'] as $m) {
    $liq_mes[$m] = $rec_mes[$m] - ($desp_mes['totais'][$m] ?? 0);
    if ($m === 'USD') $liq_mes[$m] -= $pag_func_mes;
}

$dt = DateTime::createFromFormat('Y-m', $competencia);
$mes_ant  = (clone $dt)->modify('-1 month')->format('Y-m');
$mes_prox = (clone $dt)->modify('+1 month')->format('Y-m');
$nome_mes = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')] . ' de ' . $dt->format('Y');

$historico = cobrancas_pagas_recentes($db, 30);

$page = 'Distribuição de lucro';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Distribuição de lucro</h1>
<p class="muted">Receita das cobranças pagas é dividida em <strong><?= $total_quotas ?> quotas iguais</strong>: <?= $n_socios ?> sócio<?= $n_socios===1?'':'s' ?> + empresa.</p>

<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_ant) ?>">← <?= e($mes_ant) ?></a>
  <strong><?= e($nome_mes) ?></strong>
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_prox) ?>"><?= e($mes_prox) ?> →</a>
</div>

<h2>Receita × Despesas × Lucro líquido</h2>
<?php foreach (['BRL','USD','EUR'] as $m):
    $rec = $rec_mes[$m]; $desp = $desp_mes['totais'][$m] ?? 0;
    $pf = ($m === 'USD') ? $pag_func_mes : 0;
    $liq = $liq_mes[$m]; $parte = $total_quotas > 0 ? $liq / $total_quotas : 0;
    if ($rec == 0 && $desp == 0 && $pf == 0) continue;
?>
  <div class="card">
    <div class="title"><?= $m ?></div>
    <div class="spaced" style="padding:6px 0;"><span>Receita</span><strong style="color:var(--c-success);"><?= e(money_fmt($rec, $m)) ?></strong></div>
    <?php if ($desp > 0): ?>
      <div class="spaced" style="padding:6px 0;"><span>Despesas</span><strong style="color:var(--c-danger);">− <?= e(money_fmt($desp, $m)) ?></strong></div>
    <?php endif; ?>
    <?php if ($pf > 0): ?>
      <div class="spaced" style="padding:6px 0;"><span>Pagos a funcionários</span><strong style="color:var(--c-danger);">− <?= e(money_fmt($pf, $m)) ?></strong></div>
    <?php endif; ?>
    <div class="spaced" style="padding:6px 0; border-top:1px solid var(--border);">
      <strong>Lucro líquido</strong>
      <strong style="color:<?= $liq>=0 ? 'var(--c-success)' : 'var(--c-danger)' ?>;"><?= e(money_fmt($liq, $m)) ?></strong>
    </div>
    <div class="spaced" style="padding:6px 0; color:var(--c-primary-2);">
      <span>Por quota (÷ <?= $total_quotas ?>)</span>
      <strong><?= e(money_fmt($parte, $m)) ?></strong>
    </div>
  </div>
<?php endforeach; ?>

<h2>Sócios ativos</h2>
<?php foreach ($socios as $s): ?>
  <div class="card">
    <div class="spaced">
      <div>
        <div class="title">
          <?= e($s['nome']) ?>
          <span class="status status-<?= $s['role']==='sadmin'?'destaque':'ia' ?>"><?= $s['role'] === 'sadmin' ? 'super admin' : 'admin' ?></span>
          <?php if ((int)$s['id'] === (int)$u['id']): ?><span class="status status-paga">você</span><?php endif; ?>
        </div>
        <div class="sub muted">1 quota (1/<?= $total_quotas ?>)</div>
      </div>
      <div class="right">
        <div class="money md"><?= e(money_fmt($liq_mes['BRL']/$total_quotas, 'BRL')) ?></div>
        <div class="money md"><?= e(money_fmt($liq_mes['USD']/$total_quotas, 'USD')) ?></div>
        <div class="money md"><?= e(money_fmt($liq_mes['EUR']/$total_quotas, 'EUR')) ?></div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<div class="card" style="border-color:var(--c-orange);">
  <div class="spaced">
    <div>
      <div class="title">🏢 Empresa (reserva)</div>
      <div class="sub muted">1 quota (1/<?= $total_quotas ?>)</div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt($liq_mes['BRL']/$total_quotas, 'BRL')) ?></div>
      <div class="money md"><?= e(money_fmt($liq_mes['USD']/$total_quotas, 'USD')) ?></div>
      <div class="money md"><?= e(money_fmt($liq_mes['EUR']/$total_quotas, 'EUR')) ?></div>
    </div>
  </div>
</div>

<h2>Acumulado total (todas as cobranças pagas até hoje)</h2>
<div class="grid-2">
  <?php foreach (['BRL','USD','EUR'] as $m): $valor = $rec_total[$m]; $parte = $total_quotas > 0 ? $valor / $total_quotas : 0; ?>
    <div class="kpi">
      <div class="v"><?= e(money_fmt($valor, $m)) ?></div>
      <div class="l">Total <?= $m ?> · sua parte <?= e(money_fmt($parte, $m)) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<h2>Cobranças pagas recentes</h2>
<?php foreach ($historico as $h): $parte = $total_quotas > 0 ? (float)$h['valor_total'] / $total_quotas : 0; ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$h['id'] ?>">
    <div class="info">
      <div class="nome"><?= e($h['nome_empresa']) ?></div>
      <div class="sub"><?= e($h['competencia_mes']) ?> · pago <?= $h['data_quitacao'] ? e(date('d/m/Y', strtotime($h['data_quitacao']))) : '—' ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$h['valor_total'], $h['moeda'])) ?></div>
      <div class="muted" style="font-size:11px;">quota: <?= e(money_fmt($parte, $h['moeda'])) ?></div>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$historico): ?>
  <p class="muted center mt-5">Nenhuma cobrança paga ainda.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
