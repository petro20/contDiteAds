<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/despesas.php';
require_once __DIR__ . '/lib/distribuicao.php';
require_admin();
$db = db();

$aba = $_GET['aba'] ?? 'agenda';
if (!in_array($aba, ['agenda','clientes','servicos','caixa'], true)) $aba = 'agenda';

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');
$mes_inicio = $competencia . '-01';
$mes_fim    = date('Y-m-t', strtotime($mes_inicio));

$page = t('Painel financeiro');
$nav_active = 'painel';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Painel')) ?></h1>
<details class="card" style="margin-bottom:var(--s-3);">
  <summary class="muted" style="cursor:pointer; padding:8px 0; font-size:13px;">📊 <?= e(t('Exportar planilhas (CSV)')) ?></summary>
  <div class="btn-pair" style="margin-top:8px; flex-wrap:wrap;">
    <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/export.php?tipo=cobrancas&mes=<?= e($competencia) ?>">💳 <?= e(t('Cobranças')) ?></a>
    <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/export.php?tipo=distribuicao&mes=<?= e($competencia) ?>">💎 <?= e(t('Distribuição')) ?></a>
    <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/export.php?tipo=entregas&mes=<?= e($competencia) ?>">📅 <?= e(t('Entregas')) ?></a>
    <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/export.php?tipo=clientes">👥 <?= e(t('Clientes')) ?></a>
    <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/export.php?tipo=funcionarios">🧑‍💼 <?= e(t('Funcionários')) ?></a>
    <?php if (is_sadmin()): ?>
      <a class="btn btn-ghost small" href="<?= e(APP_BASE_URL) ?>/export.php?tipo=despesas">💸 <?= e(t('Despesas')) ?></a>
    <?php endif; ?>
  </div>
  <div class="hint" style="margin-top:6px;"><?= e(t('Abre no Excel/Google Sheets com encoding UTF-8.')) ?></div>
</details>

<nav class="tabs-bar">
  <a class="<?= $aba==='agenda'?'active':'' ?>" href="?aba=agenda&mes=<?= e($competencia) ?>"><?= e(t('Agenda')) ?></a>
  <a class="<?= $aba==='clientes'?'active':'' ?>" href="?aba=clientes&mes=<?= e($competencia) ?>"><?= e(t('Por cliente')) ?></a>
  <a class="<?= $aba==='servicos'?'active':'' ?>" href="?aba=servicos&mes=<?= e($competencia) ?>"><?= e(t('Por serviço')) ?></a>
  <a class="<?= $aba==='caixa'?'active':'' ?>" href="?aba=caixa&mes=<?= e($competencia) ?>">💵 <?= e(t('Caixa')) ?></a>
</nav>

<?php if ($aba === 'agenda'):
    $ini = $competencia . '-01';
    $fim = date('Y-m-t', strtotime($ini));

    // RECEITA: pagamentos confirmados no mês
    $stmt = $db->prepare("SELECT c.moeda, COALESCE(SUM(p.valor_pago),0) AS total
                          FROM pagamentos_cliente p JOIN cobrancas c ON c.id = p.cobranca_id
                          WHERE p.data_pagamento BETWEEN ? AND ? AND COALESCE(p.pendente,0) = 0
                          GROUP BY c.moeda");
    $stmt->execute([$ini, $fim]);
    $rec = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach ($stmt->fetchAll() as $r) $rec[$r['moeda']] = (float)$r['total'];

    // EM ANÁLISE no mês de competência
    $stmt = $db->prepare("SELECT moeda, COALESCE(SUM(valor_total),0) AS total
                          FROM cobrancas WHERE competencia_mes = ? AND status = 'em_analise' GROUP BY moeda");
    $stmt->execute([$competencia]);
    $analise = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach ($stmt->fetchAll() as $r) $analise[$r['moeda']] = (float)$r['total'];

    // A RECEBER no mês de competência
    $stmt = $db->prepare("SELECT moeda, COALESCE(SUM(valor_total),0) AS total
                          FROM cobrancas WHERE competencia_mes = ? AND status = 'aberta' GROUP BY moeda");
    $stmt->execute([$competencia]);
    $a_receber = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach ($stmt->fetchAll() as $r) $a_receber[$r['moeda']] = (float)$r['total'];

    // DESPESAS do mês
    $desp_mes = despesas_do_mes($db, $competencia);

    // PAGAMENTOS a funcionários no mês (tolera tabela ausente)
    $pag_func_usd = 0.0;
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario
                              WHERE data_pagamento BETWEEN ? AND ?");
        $stmt->execute([$ini, $fim]);
        $pag_func_usd = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {}

    // PAGAMENTOS a sócios + empresa (distribuição de lucro) no mês de competência
    // $pag_socios[$moeda]                 = total geral (para o cálculo do saldo)
    // $pag_socios_det[$moeda][] = ['nome'=>..., 'valor'=>...]   (por sócio/empresa, para listar)
    $pag_socios     = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    $pag_socios_det = ['BRL'=>[],  'USD'=>[],  'EUR'=>[]];
    try {
        $stmt = $db->prepare("SELECT ps.socio_id, ps.moeda, COALESCE(u.nome,'🏢 Empresa (reserva)') AS nome,
                                     COALESCE(SUM(ps.valor),0) AS total
                              FROM pagamentos_socio ps
                              LEFT JOIN usuarios u ON u.id = ps.socio_id
                              WHERE ps.competencia_mes = ?
                              GROUP BY ps.socio_id, ps.moeda, nome
                              ORDER BY (ps.socio_id IS NULL), nome");
        $stmt->execute([$competencia]);
        foreach ($stmt->fetchAll() as $r) {
            $m = $r['moeda']; $v = (float)$r['total'];
            $pag_socios[$m]     += $v;
            $pag_socios_det[$m][] = ['nome' => $r['nome'], 'valor' => $v];
        }
    } catch (PDOException $e) {}

    // LUCRO LÍQUIDO operacional (recebido - despesas; USD também desconta pag. funcionário).
    // Distribuição aos sócios não entra aqui — é mostrada como saída em separado, após o lucro.
    $lucro = [];
    $saldo_pos_dist = [];
    foreach (['BRL','USD','EUR'] as $m) {
        $lucro[$m] = $rec[$m] - ($desp_mes['totais'][$m] ?? 0);
        if ($m === 'USD') $lucro[$m] -= $pag_func_usd;
        $saldo_pos_dist[$m] = $lucro[$m] - $pag_socios[$m];
    }

    // Quotas pra distribuição
    $qts = quotas_total($db);

    // Cobranças vencidas (qualquer mês)
    $stmt = $db->prepare("SELECT c.id, c.valor_total, c.moeda, c.vencimento, cl.nome_empresa
                          FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
                          WHERE c.status = 'aberta' AND c.vencimento < CURDATE()
                          ORDER BY c.vencimento ASC LIMIT 20");
    $stmt->execute();
    $vencidas = $stmt->fetchAll();

    // Cobranças vencendo nos próximos 7 dias
    $stmt = $db->prepare("SELECT c.id, c.valor_total, c.moeda, c.vencimento, cl.nome_empresa
                          FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
                          WHERE c.status = 'aberta' AND c.vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                          ORDER BY c.vencimento ASC LIMIT 20");
    $stmt->execute();
    $proximas = $stmt->fetchAll();

    // Navegação de mês
    $dt = DateTime::createFromFormat('Y-m', $competencia);
    $mes_ant = (clone $dt)->modify('-1 month')->format('Y-m');
    $mes_prox = (clone $dt)->modify('+1 month')->format('Y-m');
    $nome_mes_pt = t(['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')]) . ' ' . t('de') . ' ' . $dt->format('Y');

    // Histórico para o gráfico — gera do primeiro mês com movimento até a competência atual.
    // O JS filtra por período (1m / 3m / 6m / 1a / tudo). Queries toleram tabelas ausentes.
    $cur_dt = DateTimeImmutable::createFromFormat('Y-m', $competencia);
    // Descobre o mês mais antigo com movimento
    $datas_min = [];
    try {
        $d = $db->query("SELECT MIN(data_pagamento) FROM pagamentos_cliente")->fetchColumn();
        if ($d) $datas_min[] = $d;
    } catch (PDOException $e) {}
    try {
        $d = $db->query("SELECT MIN(competencia_mes) FROM cobrancas")->fetchColumn();
        if ($d) $datas_min[] = $d . '-01';
    } catch (PDOException $e) {}
    try {
        $d = $db->query("SELECT MIN(data_inicio) FROM despesas WHERE ativo=1")->fetchColumn();
        if ($d) $datas_min[] = $d;
    } catch (PDOException $e) {}
    try {
        $d = $db->query("SELECT MIN(data_pagamento) FROM pagamentos_funcionario")->fetchColumn();
        if ($d) $datas_min[] = $d;
    } catch (PDOException $e) {}
    // mes_primeira_mov = primeiro mês com qualquer dado real (sem fallback)
    $mes_primeira_mov = $datas_min ? substr(min($datas_min), 0, 7) : $competencia;
    // Pro chart, sempre gera pelo menos 12 meses (pra "1a" funcionar com zeros).
    $doze_atras = (clone $cur_dt)->modify('-11 months')->format('Y-m');
    $mes_inicial = ($mes_primeira_mov < $doze_atras) ? $mes_primeira_mov : $doze_atras;
    // Calcula quantos meses do mes_inicial até cur_dt
    $ini_dt = DateTimeImmutable::createFromFormat('Y-m', $mes_inicial);
    $total_meses = ((int)$cur_dt->format('Y') - (int)$ini_dt->format('Y')) * 12
                   + ((int)$cur_dt->format('n') - (int)$ini_dt->format('n')) + 1;
    if ($total_meses > 60) $total_meses = 60; // teto de 5 anos pra não exagerar
    $historico = [];
    for ($i = $total_meses - 1; $i >= 0; $i--) {
        $mes = (clone $cur_dt)->modify("-{$i} months")->format('Y-m');
        $ini_m = $mes . '-01';
        $fim_m = date('Y-m-t', strtotime($ini_m));
        $mn_pt = t(['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'][(int)substr($mes,5,2)]) . '/' . substr($mes,2,2);
        $h = ['mes'=>$mes,'label'=>$mn_pt];
        foreach (['BRL','USD','EUR'] as $cur) {
            $r = 0.0;
            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos_cliente p JOIN cobrancas c ON c.id = p.cobranca_id WHERE p.data_pagamento BETWEEN ? AND ? AND c.moeda = ? AND COALESCE(p.pendente,0)=0");
                $stmt->execute([$ini_m, $fim_m, $cur]);
                $r = (float)$stmt->fetchColumn();
            } catch (PDOException $e) {}
            $d_mes = despesas_do_mes($db, $mes);
            $d = $d_mes['totais'][$cur] ?? 0;
            $pf = 0;
            if ($cur === 'USD') {
                try {
                    $stmt = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE data_pagamento BETWEEN ? AND ?");
                    $stmt->execute([$ini_m, $fim_m]);
                    $pf = (float)$stmt->fetchColumn();
                } catch (PDOException $e) {}
            }
            // Pagamento aos sócios no mês (por moeda)
            $ps = 0;
            try {
                $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) FROM pagamentos_socio WHERE competencia_mes = ? AND moeda = ?");
                $stmt->execute([$mes, $cur]);
                $ps = (float)$stmt->fetchColumn();
            } catch (PDOException $e) {}
            $h[$cur] = ['r'=>$r, 'd'=>$d + $pf, 's'=>$ps, 'l'=>$r - $d - $pf];
        }
        $historico[] = $h;
    }
    // Índice do primeiro mês com movimento real (pro botão "Tudo" cortar daqui)
    $idx_primeira_mov = 0;
    foreach ($historico as $i => $h) {
        if ($h['mes'] >= $mes_primeira_mov) { $idx_primeira_mov = $i; break; }
    }

    // Resumo por moeda (mês competencia)
    $stmt = $db->prepare("SELECT moeda, status, SUM(valor_total) AS total
                          FROM cobrancas WHERE competencia_mes = ? GROUP BY moeda, status");
    $stmt->execute([$competencia]);
    $resumo = $stmt->fetchAll();
    $por_moeda = ['BRL'=>['receber'=>0,'recebido'=>0,'analise'=>0],'USD'=>['receber'=>0,'recebido'=>0,'analise'=>0],'EUR'=>['receber'=>0,'recebido'=>0,'analise'=>0]];
    foreach ($resumo as $r) {
        $m = $r['moeda'];
        if ($r['status'] === 'paga')             $por_moeda[$m]['recebido'] += (float)$r['total'];
        elseif ($r['status'] === 'aberta')       $por_moeda[$m]['receber']  += (float)$r['total'];
        elseif ($r['status'] === 'em_analise')   $por_moeda[$m]['analise']  += (float)$r['total'];
    }

    // Totais ALL-TIME (todas as cobranças até hoje, não só esse mês)
    $stmt = $db->query("SELECT moeda, status, SUM(valor_total) AS total
                        FROM cobrancas GROUP BY moeda, status");
    $resumo_total = $stmt->fetchAll();
    $por_moeda_total = ['BRL'=>['receber'=>0,'recebido'=>0,'analise'=>0],'USD'=>['receber'=>0,'recebido'=>0,'analise'=>0],'EUR'=>['receber'=>0,'recebido'=>0,'analise'=>0]];
    foreach ($resumo_total as $r) {
        $m = $r['moeda'];
        if ($r['status'] === 'paga')           $por_moeda_total[$m]['recebido'] += (float)$r['total'];
        elseif ($r['status'] === 'aberta')     $por_moeda_total[$m]['receber']  += (float)$r['total'];
        elseif ($r['status'] === 'em_analise') $por_moeda_total[$m]['analise']  += (float)$r['total'];
    }
?>
<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?aba=agenda&mes=<?= e($mes_ant) ?>">← <?= e($mes_ant) ?></a>
  <strong><?= e($nome_mes_pt) ?></strong>
  <a class="btn btn-ghost small" href="?aba=agenda&mes=<?= e($mes_prox) ?>"><?= e($mes_prox) ?> →</a>
</div>

<div class="card">
  <div class="spaced mb-3">
    <strong>📈 <?= e(t('Saúde financeira')) ?> <span class="muted" style="font-weight:normal; font-size:12px;" id="chart-periodo-label"><?= e(t('(últimos 6 meses)')) ?></span></strong>
    <div id="chart-moeda-tabs" class="btn-pair" style="gap:4px;">
      <button type="button" class="btn small btn-secondary" data-moeda="BRL" onclick="trocarMoedaGrafico('BRL')">R$</button>
      <button type="button" class="btn small btn-ghost" data-moeda="USD" onclick="trocarMoedaGrafico('USD')">$</button>
      <button type="button" class="btn small btn-ghost" data-moeda="EUR" onclick="trocarMoedaGrafico('EUR')">€</button>
    </div>
  </div>
  <div id="chart-periodo-tabs" class="btn-pair mb-3" style="gap:4px; flex-wrap:wrap;">
    <button type="button" class="btn small btn-ghost" data-periodo="1"  onclick="trocarPeriodoGrafico(1)">1m</button>
    <button type="button" class="btn small btn-ghost" data-periodo="3"  onclick="trocarPeriodoGrafico(3)">3m</button>
    <button type="button" class="btn small btn-secondary" data-periodo="6"  onclick="trocarPeriodoGrafico(6)">6m</button>
    <button type="button" class="btn small btn-ghost" data-periodo="12" onclick="trocarPeriodoGrafico(12)"><?= e(t('1a')) ?></button>
    <button type="button" class="btn small btn-ghost" data-periodo="0"  onclick="trocarPeriodoGrafico(0)"><?= e(t('Tudo')) ?></button>
  </div>
  <div style="position:relative; height:240px;">
    <canvas id="chart_saude"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const HISTORICO = <?= json_encode($historico, JSON_UNESCAPED_UNICODE) ?>;
const IDX_PRIMEIRA_MOV = <?= (int)$idx_primeira_mov ?>;
let chart_saude = null;
let moeda_atual = 'BRL';
let periodo_atual = 6; // 0 = tudo

const PERIODO_LABEL = {1:<?= json_encode(t('(último mês)'), JSON_UNESCAPED_UNICODE) ?>, 3:<?= json_encode(t('(últimos 3 meses)'), JSON_UNESCAPED_UNICODE) ?>, 6:<?= json_encode(t('(últimos 6 meses)'), JSON_UNESCAPED_UNICODE) ?>, 12:<?= json_encode(t('(último ano)'), JSON_UNESCAPED_UNICODE) ?>, 0:<?= json_encode(t('(o tempo todo)'), JSON_UNESCAPED_UNICODE) ?>};

function trocarMoedaGrafico(m) {
  moeda_atual = m;
  document.querySelectorAll('#chart-moeda-tabs button').forEach(b => {
    b.classList.toggle('btn-secondary', b.dataset.moeda === m);
    b.classList.toggle('btn-ghost', b.dataset.moeda !== m);
  });
  renderChartSaude();
}

function trocarPeriodoGrafico(p) {
  periodo_atual = p;
  document.querySelectorAll('#chart-periodo-tabs button').forEach(b => {
    const sel = parseInt(b.dataset.periodo) === p;
    b.classList.toggle('btn-secondary', sel);
    b.classList.toggle('btn-ghost', !sel);
  });
  document.getElementById('chart-periodo-label').textContent = PERIODO_LABEL[p] || '';
  renderChartSaude();
}

function fatiarHistorico() {
  if (periodo_atual === 0) return HISTORICO.slice(IDX_PRIMEIRA_MOV);
  return HISTORICO.slice(-periodo_atual);
}

function renderChartSaude() {
  const dados = fatiarHistorico();
  const labels = dados.map(h => h.label);
  const receitas = dados.map(h => h[moeda_atual]?.r ?? 0);
  const despesas = dados.map(h => h[moeda_atual]?.d ?? 0);
  const socios   = dados.map(h => h[moeda_atual]?.s ?? 0);
  const lucros   = dados.map(h => h[moeda_atual]?.l ?? 0);

  const ctx = document.getElementById('chart_saude').getContext('2d');
  if (chart_saude) chart_saude.destroy();
  chart_saude = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: <?= json_encode(t('Receita'), JSON_UNESCAPED_UNICODE) ?>,  data: receitas, backgroundColor: '#10B981', borderRadius: 4 },
        { label: <?= json_encode(t('Despesa'), JSON_UNESCAPED_UNICODE) ?>,  data: despesas, backgroundColor: '#DC2626', borderRadius: 4 },
        { label: <?= json_encode(t('Pago sócios'), JSON_UNESCAPED_UNICODE) ?>, data: socios, backgroundColor: '#A855F7', borderRadius: 4 },
        { label: <?= json_encode(t('Lucro'), JSON_UNESCAPED_UNICODE) ?>,    data: lucros,   backgroundColor: '#2563EB', borderRadius: 4, type: 'line', borderColor: '#3B82F6', borderWidth: 2, tension: 0.3, fill: false, pointBackgroundColor: '#3B82F6', pointRadius: 4 }
      ]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#A0A0AB', boxWidth: 12 } },
        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2) } }
      },
      scales: {
        x: { ticks: { color: '#A0A0AB' }, grid: { color: 'rgba(255,255,255,0.05)' } },
        y: { ticks: { color: '#A0A0AB' }, grid: { color: 'rgba(255,255,255,0.05)' } }
      }
    }
  });
}
renderChartSaude();
</script>

<?php foreach (['BRL','USD','EUR'] as $m):
    $r = $rec[$m]; $a = $analise[$m]; $ar = $a_receber[$m];
    $d = $desp_mes['totais'][$m] ?? 0;
    $extra_func = ($m === 'USD') ? $pag_func_usd : 0;
    $dist = $pag_socios[$m];
    $luc = $lucro[$m];
    $saldo = $saldo_pos_dist[$m];
    if ($r == 0 && $a == 0 && $ar == 0 && $d == 0 && $extra_func == 0 && $dist == 0) continue;
?>
  <div class="card">
    <div class="title" style="font-size:18px;"><?= $m ?></div>

    <div class="section-label" style="margin-top:var(--s-3);"><?= e(t('Entradas')) ?></div>
    <?php if ($r > 0): ?>
      <div class="info-pair"><span class="l">✅ <?= e(t('Recebido')) ?></span><span class="v" style="color:var(--c-success);"><?= e(money_fmt($r, $m)) ?></span></div>
    <?php endif; ?>
    <?php if ($a > 0): ?>
      <div class="info-pair"><span class="l">🟠 <?= e(t('Em análise')) ?></span><span class="v" style="color:var(--c-orange);"><?= e(money_fmt($a, $m)) ?></span></div>
    <?php endif; ?>
    <?php if ($ar > 0): ?>
      <div class="info-pair"><span class="l">⏳ <?= e(t('A receber')) ?></span><span class="v"><?= e(money_fmt($ar, $m)) ?></span></div>
    <?php endif; ?>

    <?php if ($d > 0 || $extra_func > 0): ?>
      <div class="section-label" style="margin-top:var(--s-3);"><?= e(t('Saídas')) ?></div>
      <?php if ($d > 0): ?>
        <div class="info-pair"><span class="l">💸 <?= e(t('Despesas')) ?></span><span class="v" style="color:var(--c-danger);">− <?= e(money_fmt($d, $m)) ?></span></div>
      <?php endif; ?>
      <?php if ($extra_func > 0): ?>
        <div class="info-pair"><span class="l">💵 <?= e(t('Pagos a funcionários')) ?></span><span class="v" style="color:var(--c-danger);">− <?= e(money_fmt($extra_func, $m)) ?></span></div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="info-pair" style="border-top:2px solid var(--border); padding-top:var(--s-3); margin-top:var(--s-3);">
      <strong style="font-size:15px;">💎 <?= e(t('Lucro líquido')) ?> <span class="muted" style="font-weight:normal; font-size:12px;"><?= e(t('(antes de distribuir)')) ?></span></strong>
      <strong style="font-size:18px; color:<?= $luc >= 0 ? 'var(--c-success)' : 'var(--c-danger)' ?>;"><?= e(money_fmt($luc, $m)) ?></strong>
    </div>
    <?php if ($qts > 0 && $luc != 0): ?>
      <div class="info-pair muted" style="font-size:13px;"><span><?= e(t('Por quota')) ?> (÷ <?= $qts ?>)</span><span><?= e(money_fmt($luc / $qts, $m)) ?></span></div>
    <?php endif; ?>

    <?php if ($dist > 0): ?>
      <div class="section-label" style="margin-top:var(--s-3);"><?= e(t('Distribuição já paga')) ?></div>
      <?php foreach ($pag_socios_det[$m] as $ps): ?>
        <div class="info-pair" style="font-size:14px;">
          <span class="l">💼 <?= e($ps['nome']) ?></span>
          <span class="v" style="color:var(--c-danger);">− <?= e(money_fmt($ps['valor'], $m)) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="info-pair muted" style="font-size:13px;">
        <span><?= e(t('Total distribuído')) ?></span>
        <span style="color:var(--c-danger);">− <?= e(money_fmt($dist, $m)) ?></span>
      </div>
      <div class="info-pair" style="border-top:1px solid var(--border); padding-top:var(--s-3); margin-top:var(--s-2);">
        <strong style="font-size:15px;">💰 <?= e(t('Saldo após distribuição')) ?></strong>
        <strong style="font-size:18px; color:<?= $saldo >= 0 ? 'var(--c-success)' : 'var(--c-danger)' ?>;"><?= e(money_fmt($saldo, $m)) ?></strong>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php
  $vazio_total = (array_sum($rec) + array_sum($analise) + array_sum($a_receber) + array_sum($desp_mes['totais']) + $pag_func_usd + array_sum($pag_socios)) == 0;
  if ($vazio_total):
?>
  <p class="muted center mt-5"><?= e(t('Sem movimentação financeira em')) ?> <?= e($nome_mes_pt) ?>.</p>
<?php endif; ?>

<?php if ($vencidas): ?>
<div class="section-label">⚠️ <?= e(t('Vencidas')) ?> (<?= count($vencidas) ?>)</div>
<?php foreach ($vencidas as $v): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$v['id'] ?>" style="border-color:var(--c-danger);">
    <div class="info">
      <div class="nome"><?= e($v['nome_empresa']) ?></div>
      <div class="sub"><?= e(t('venc')) ?> <?= e(date('d/m/Y', strtotime($v['vencimento']))) ?></div>
    </div>
    <div class="right"><div class="money md"><?= e(money_fmt((float)$v['valor_total'], $v['moeda'])) ?></div></div>
  </a>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($proximas): ?>
<div class="section-label">📅 <?= e(t('Vencendo nos próximos 7 dias')) ?></div>
<?php foreach ($proximas as $p): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$p['id'] ?>">
    <div class="info">
      <div class="nome"><?= e($p['nome_empresa']) ?></div>
      <div class="sub"><?= e(t('venc')) ?> <?= e(date('d/m/Y', strtotime($p['vencimento']))) ?></div>
    </div>
    <div class="right"><div class="money md"><?= e(money_fmt((float)$p['valor_total'], $p['moeda'])) ?></div></div>
  </a>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!$vencidas && !$proximas): ?>
  <p class="muted center mt-5"><?= e(t('Nenhuma cobrança vencida ou vencendo nos próximos 7 dias.')) ?></p>
<?php endif; ?>

<?php elseif ($aba === 'clientes'):
    $stmt = $db->prepare("
        SELECT cl.id, cl.nome_empresa, cl.moeda,
               (SELECT COUNT(*) FROM assinaturas a WHERE a.cliente_id = cl.id AND a.status = 'ativa') AS qtd_assin,
               (SELECT COALESCE(SUM(c.valor_total),0) FROM cobrancas c WHERE c.cliente_id = cl.id) AS total_cobrado,
               (SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos_cliente p JOIN cobrancas c ON c.id = p.cobranca_id WHERE c.cliente_id = cl.id) AS total_pago,
               (SELECT COALESCE(SUM(c.valor_total),0) FROM cobrancas c WHERE c.cliente_id = cl.id AND c.status = 'aberta') AS em_aberto
        FROM clientes cl
        WHERE cl.ativo = 1
        ORDER BY em_aberto DESC, cl.nome_empresa
    ");
    $stmt->execute();
    $clis = $stmt->fetchAll();
?>
<div class="section-label"><?= e(t('Por cliente')) ?> (<?= count($clis) ?>)</div>
<?php foreach ($clis as $c): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/clientes.php?acao=editar&id=<?= (int)$c['id'] ?>">
    <div class="info">
      <div class="nome"><?= e($c['nome_empresa']) ?> <span class="muted">(<?= $c['moeda'] ?>)</span></div>
      <div class="sub">
        <?= (int)$c['qtd_assin'] ?> <?= e(t('assin. ativas')) ?> ·
        <?= e(t('cobrado')) ?> <?= e(money_fmt((float)$c['total_cobrado'], $c['moeda'])) ?> ·
        <?= e(t('pago')) ?> <?= e(money_fmt((float)$c['total_pago'], $c['moeda'])) ?>
      </div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$c['em_aberto'], $c['moeda'])) ?></div>
      <div class="muted" style="font-size:11px;"><?= e(t('em aberto')) ?></div>
    </div>
  </a>
<?php endforeach; ?>

<?php elseif ($aba === 'servicos'): /* servicos */
    $stmt = $db->prepare("
        SELECT i.id, i.nome, i.tipo, i.e_pacote,
               (SELECT COUNT(DISTINCT cliente_id) FROM assinaturas WHERE item_id = i.id AND status = 'ativa') AS qtd_clientes,
               (SELECT COUNT(*) FROM assinaturas WHERE item_id = i.id AND status = 'ativa') AS qtd_assin
        FROM itens_catalogo i
        WHERE i.ativo = 1
          AND EXISTS (SELECT 1 FROM assinaturas WHERE item_id = i.id AND status = 'ativa')
        ORDER BY qtd_clientes DESC, i.nome
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();
?>
<div class="section-label"><?= e(t('Por serviço')) ?> (<?= count($items) ?>)</div>
<?php foreach ($items as $it): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/catalogo.php?acao=editar&id=<?= (int)$it['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($it['nome']) ?>
        <?php if ($it['e_pacote']): ?><span class="status status-ia"><?= e(t('pacote')) ?></span><?php endif; ?>
      </div>
      <div class="sub"><?= e($it['tipo']) ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= (int)$it['qtd_clientes'] ?></div>
      <div class="muted" style="font-size:11px;"><?= e(t('clientes')) ?></div>
    </div>
  </a>
<?php endforeach; ?>

<?php elseif ($aba === 'caixa'):
    require_once __DIR__ . '/lib/cotacao.php';
    $mes_ant  = date('Y-m', strtotime($competencia . '-01 -1 month'));
    $mes_prox = date('Y-m', strtotime($competencia . '-01 +1 month'));
    $meses_nm = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $nome_mes_cx = t($meses_nm[(int)substr($competencia,5,2)]) . ' ' . substr($competencia,0,4);

    // ENTROU: recebimentos de clientes confirmados no mês (pela data do pagamento)
    $stmt = $db->prepare("SELECT p.valor_pago, p.data_pagamento, p.metodo, c.moeda, cl.nome_empresa
                          FROM pagamentos_cliente p
                          JOIN cobrancas c ON c.id = p.cobranca_id
                          JOIN clientes cl ON cl.id = c.cliente_id
                          WHERE p.data_pagamento BETWEEN ? AND ? AND COALESCE(p.pendente,0)=0
                          ORDER BY p.data_pagamento, cl.nome_empresa");
    $stmt->execute([$mes_inicio, $mes_fim]);
    $cx_entradas = $stmt->fetchAll();
    $cx_tot_ent = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach ($cx_entradas as $e) $cx_tot_ent[$e['moeda']] += (float)$e['valor_pago'];

    // SAIU 1: despesas que se aplicam ao mês
    $cx_desp = despesas_do_mes($db, $competencia);
    $cx_tot_desp = $cx_desp['totais'];

    // SAIU 2: pagamentos à equipe (USD) pela data do pagamento
    $stmt = $db->prepare("SELECT pf.valor_usd, pf.data_pagamento, u.nome
                          FROM pagamentos_funcionario pf JOIN usuarios u ON u.id = pf.funcionario_id
                          WHERE pf.data_pagamento BETWEEN ? AND ? ORDER BY pf.data_pagamento");
    $stmt->execute([$mes_inicio, $mes_fim]);
    $cx_equipe = $stmt->fetchAll();
    $cx_tot_equipe_usd = (float)array_sum(array_column($cx_equipe, 'valor_usd'));

    // SAIU 3: distribuição aos sócios/empresa (pela data do pagamento)
    $cx_socios = [];
    try {
        $stmt = $db->prepare("SELECT ps.valor, ps.moeda, ps.data_pagamento, ps.socio_id, u.nome
                              FROM pagamentos_socio ps LEFT JOIN usuarios u ON u.id = ps.socio_id
                              WHERE ps.data_pagamento BETWEEN ? AND ? ORDER BY ps.data_pagamento");
        $stmt->execute([$mes_inicio, $mes_fim]);
        $cx_socios = $stmt->fetchAll();
    } catch (PDOException $e) {}
    $cx_tot_soc = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach ($cx_socios as $s) $cx_tot_soc[$s['moeda']] += (float)$s['valor'];

    // Consolidado em US$ (a conta do lucro)
    $cx_rec_usd = 0.0; $cx_desp_usd = 0.0; $cx_soc_usd = 0.0;
    foreach (['BRL','USD','EUR'] as $m) {
        $cx_rec_usd  += para_usd($db, $cx_tot_ent[$m], $m);
        $cx_desp_usd += para_usd($db, $cx_tot_desp[$m] ?? 0, $m);
        $cx_soc_usd  += para_usd($db, $cx_tot_soc[$m], $m);
    }
    $cx_lucro_usd = $cx_rec_usd - $cx_desp_usd - $cx_tot_equipe_usd;
    $cx_falta = $cx_lucro_usd - $cx_soc_usd;
    $fmt_multi = function(array $t) { $p=[]; foreach(['BRL','USD','EUR'] as $m) if(($t[$m]??0)>0.001)$p[]=money_fmt($t[$m],$m); return $p ? implode(' · ',$p) : '—'; };
?>
  <div class="spaced mb-3">
    <a class="btn btn-ghost small" href="?aba=caixa&mes=<?= e($mes_ant) ?>">← <?= e($mes_ant) ?></a>
    <strong><?= e($nome_mes_cx) ?></strong>
    <a class="btn btn-ghost small" href="?aba=caixa&mes=<?= e($mes_prox) ?>"><?= e($mes_prox) ?> →</a>
  </div>

  <div class="card brand">
    <div class="title">🧮 <?= e(t('Conta do lucro (em US$)')) ?></div>
    <div class="spaced" style="padding:6px 0;"><span><?= e(t('Entrou (recebido de clientes)')) ?></span><strong style="color:var(--c-success);"><?= e(money_fmt($cx_rec_usd,'USD')) ?></strong></div>
    <?php if ($cx_desp_usd > 0): ?><div class="spaced" style="padding:6px 0;"><span>− <?= e(t('Despesas')) ?></span><strong style="color:var(--c-danger);"><?= e(money_fmt($cx_desp_usd,'USD')) ?></strong></div><?php endif; ?>
    <?php if ($cx_tot_equipe_usd > 0): ?><div class="spaced" style="padding:6px 0;"><span>− <?= e(t('Pagamentos à equipe')) ?></span><strong style="color:var(--c-danger);"><?= e(money_fmt($cx_tot_equipe_usd,'USD')) ?></strong></div><?php endif; ?>
    <div class="spaced" style="padding:8px 0; border-top:1px solid var(--border);">
      <strong><?= e(t('= Lucro líquido')) ?></strong>
      <strong style="font-size:18px; color:<?= $cx_lucro_usd>=0?'var(--c-success)':'var(--c-danger)' ?>;"><?= e(money_fmt($cx_lucro_usd,'USD')) ?></strong>
    </div>
    <div class="muted" style="font-size:11px; margin-top:6px; border-top:1px dashed var(--border); padding-top:8px;">
      🏦 <?= e(t('A distribuição aos sócios/empresa NÃO é custo — ela sai DO lucro acima.')) ?>
    </div>
    <div class="spaced" style="padding:6px 0;"><span><?= e(t('Distribuído no mês')) ?></span><strong style="color:var(--c-primary-2);"><?= e(money_fmt($cx_soc_usd,'USD')) ?></strong></div>
    <div class="spaced" style="padding:6px 0;">
      <span><?= $cx_falta>=0 ? e(t('Ainda a distribuir')) : '⚠ '.e(t('Distribuído a MAIS que o lucro')) ?></span>
      <strong style="color:<?= $cx_falta>=0?'var(--txt-1)':'var(--c-danger)' ?>;"><?= e(money_fmt(abs($cx_falta),'USD')) ?></strong>
    </div>
  </div>

  <h2 class="mt-5">📥 <?= e(t('Entrou')) ?> <span class="muted" style="font-size:13px; font-weight:normal;">(<?= e($fmt_multi($cx_tot_ent)) ?>)</span></h2>
  <?php if (!$cx_entradas): ?>
    <p class="muted"><?= e(t('Nenhum recebimento neste mês.')) ?></p>
  <?php else: foreach ($cx_entradas as $e): ?>
    <div class="list-card">
      <div class="info">
        <div class="nome"><?= e($e['nome_empresa']) ?></div>
        <div class="sub"><?= e(date('d/m/Y', strtotime($e['data_pagamento']))) ?><?php if ($e['metodo']): ?> · <?= e($e['metodo']) ?><?php endif; ?></div>
      </div>
      <div class="right"><div class="money md" style="color:var(--c-success);"><?= e(money_fmt((float)$e['valor_pago'], $e['moeda'])) ?></div></div>
    </div>
  <?php endforeach; endif; ?>

  <h2 class="mt-5">📤 <?= e(t('Saiu')) ?></h2>

  <div class="section-label"><?= e(t('Despesas')) ?> <span class="muted" style="font-weight:normal;">(<?= e($fmt_multi($cx_tot_desp)) ?>)</span></div>
  <?php if (empty($cx_desp['detalhes'])): ?>
    <p class="muted"><?= e(t('Nenhuma despesa neste mês.')) ?></p>
  <?php else: foreach ($cx_desp['detalhes'] as $d): ?>
    <div class="list-card">
      <div class="info">
        <div class="nome"><?= e($d['descricao']) ?></div>
        <div class="sub"><?= e($d['categoria'] ?? '—') ?> · <?= e($d['recorrencia']) ?></div>
      </div>
      <div class="right"><div class="money md" style="color:var(--c-danger);"><?= e(money_fmt((float)$d['valor'], $d['moeda'])) ?></div></div>
    </div>
  <?php endforeach; endif; ?>

  <div class="section-label mt-3"><?= e(t('Pagamentos à equipe')) ?> <span class="muted" style="font-weight:normal;">(<?= e($cx_tot_equipe_usd>0 ? money_fmt($cx_tot_equipe_usd,'USD') : '—') ?>)</span></div>
  <?php if (!$cx_equipe): ?>
    <p class="muted"><?= e(t('Nenhum pagamento à equipe neste mês.')) ?></p>
  <?php else: foreach ($cx_equipe as $pf): ?>
    <div class="list-card">
      <div class="info">
        <div class="nome"><?= e($pf['nome']) ?></div>
        <div class="sub"><?= e(date('d/m/Y', strtotime($pf['data_pagamento']))) ?></div>
      </div>
      <div class="right"><div class="money md" style="color:var(--c-danger);"><?= e(money_fmt((float)$pf['valor_usd'], 'USD')) ?></div></div>
    </div>
  <?php endforeach; endif; ?>

  <div class="section-label mt-3">🏦 <?= e(t('Distribuição aos sócios/empresa')) ?> <span class="muted" style="font-weight:normal;">(<?= e($fmt_multi($cx_tot_soc)) ?>)</span></div>
  <?php if (!$cx_socios): ?>
    <p class="muted"><?= e(t('Nenhuma distribuição neste mês.')) ?></p>
  <?php else: foreach ($cx_socios as $s): ?>
    <div class="list-card">
      <div class="info">
        <div class="nome"><?= $s['socio_id'] === null ? '🏢 ' . e(t('Empresa (reserva)')) : e($s['nome']) ?></div>
        <div class="sub"><?= e(date('d/m/Y', strtotime($s['data_pagamento']))) ?></div>
      </div>
      <div class="right"><div class="money md" style="color:var(--c-primary-2);"><?= e(money_fmt((float)$s['valor'], $s['moeda'])) ?></div></div>
    </div>
  <?php endforeach; endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
