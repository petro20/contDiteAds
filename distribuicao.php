<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/grupos.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/distribuicao.php';
require_once __DIR__ . '/lib/despesas.php';
require_once __DIR__ . '/lib/audit.php';
$u = require_login();
if (!is_admin()) { http_response_code(403); exit('Apenas sócios.'); }
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'apagar_pagamento_socio') {
        if (!is_sadmin()) { http_response_code(403); exit('Apenas Super Admin pode apagar pagamentos a sócios.'); }
        $pid = (int)($_POST['id'] ?? 0);
        $comp_back = $_POST['competencia'] ?? date('Y-m');
        if ($pid > 0) {
            try {
                $db->prepare('DELETE FROM pagamentos_socio WHERE id = ?')->execute([$pid]);
                audit_log('socio.pagamento_apagado', 'pagamentos_socio', $pid);
                header('Location: ' . APP_BASE_URL . '/distribuicao.php?mes=' . urlencode($comp_back) . '&del=1'); exit;
            } catch (PDOException $e) {
                $flash = ['err', 'Erro ao apagar: ' . $e->getMessage()];
            }
        }
    }
    if (($_POST['op'] ?? '') === 'pagar_socio') {
        $socio_id = ($_POST['socio_id'] ?? '') === 'empresa' ? null : (int)$_POST['socio_id'];
        $comp     = $_POST['competencia'] ?? date('Y-m');
        $moeda    = in_array($_POST['moeda'] ?? '', ['USD','BRL','EUR'], true) ? $_POST['moeda'] : 'BRL';
        $valor    = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
        $data     = $_POST['data_pagamento'] ?? date('Y-m-d');
        $obs      = trim((string)($_POST['observacao'] ?? '')) ?: null;
        if ($valor > 0) {
            // Trava: valor pago não pode exceder a quota disponível pro sócio na competência.
            // Cálculo: receita - despesas - pagamentos_funcionário, dividido pelo nº de quotas (sócios + empresa).
            // Subtrai o que já foi pago a esse sócio/empresa no mês.
            try {
                $socios_atuais = socios_ativos($db);
                $n_q = count($socios_atuais) + 1; // +1 empresa
                $rec_m  = receita_mes($db, $comp);
                $desp_m = despesas_do_mes($db, $comp);
                $ini = $comp . '-01';
                $fim = date('Y-m-t', strtotime($ini));
                $pag_func = 0.0;
                try {
                    $stq = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE data_pagamento BETWEEN ? AND ?");
                    $stq->execute([$ini, $fim]);
                    $pag_func = (float)$stq->fetchColumn();
                } catch (PDOException $e) {}
                $liq = $rec_m[$moeda] - ($desp_m['totais'][$moeda] ?? 0);
                if ($moeda === 'USD') $liq -= $pag_func;
                $quota = $liq / $n_q;

                // Já pago a esse beneficiário (sócio ou empresa) na competência+moeda
                $sql_jp = $socio_id === null
                    ? 'SELECT COALESCE(SUM(valor),0) FROM pagamentos_socio WHERE socio_id IS NULL AND competencia_mes=? AND moeda=?'
                    : 'SELECT COALESCE(SUM(valor),0) FROM pagamentos_socio WHERE socio_id=? AND competencia_mes=? AND moeda=?';
                $stj = $db->prepare($sql_jp);
                $stj->execute($socio_id === null ? [$comp, $moeda] : [$socio_id, $comp, $moeda]);
                $ja_pago = (float)$stj->fetchColumn();
                $disponivel = $quota - $ja_pago;

                if ($disponivel <= 0) {
                    $flash = ['err', 'Esse beneficiário já recebeu o total da quota disponível (' . number_format($quota, 2, ',', '.') . ' ' . $moeda . ') nesta competência.'];
                } elseif ($valor > $disponivel + 0.01) {
                    $flash = ['err', sprintf('Valor excede a quota disponível. Quota total: %.2f %s · Já pago: %.2f %s · Disponível: %.2f %s', $quota, $moeda, $ja_pago, $moeda, $disponivel, $moeda)];
                } else {
                    $stmt = $db->prepare('INSERT INTO pagamentos_socio (socio_id, competencia_mes, moeda, valor, data_pagamento, observacao, criado_por) VALUES (?,?,?,?,?,?,?)');
                    $stmt->execute([$socio_id, $comp, $moeda, $valor, $data, $obs, (int)$u['id']]);
                    audit_log('socio.pago', 'pagamentos_socio', (int)$db->lastInsertId());
                    header('Location: ' . APP_BASE_URL . '/distribuicao.php?mes=' . urlencode($comp) . '&ok=1'); exit;
                }
            } catch (PDOException $e) {
                $flash = ['err', 'Erro: ' . $e->getMessage()];
            }
        } else {
            $flash = ['err', 'Valor deve ser maior que zero.'];
        }
    }
}
if (isset($_GET['ok'])) $flash = ['ok', 'Pagamento registrado.'];
if (isset($_GET['del'])) $flash = ['ok', 'Pagamento apagado.'];

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
<h1 class="page-title">Finanças</h1>
<?php render_group_tabs('financas', 'distribuicao'); ?>
<h2>Distribuição de lucro</h2>
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

<?php
  // Quanto já foi pago a cada sócio (e à empresa) NESTE mês
  $ja_pago_por = []; // ['socio_X' => ['BRL'=>0,'USD'=>0,'EUR'=>0], 'empresa' => [...]]
  try {
      $stmt = $db->prepare('SELECT socio_id, moeda, COALESCE(SUM(valor),0) AS total FROM pagamentos_socio WHERE competencia_mes = ? GROUP BY socio_id, moeda');
      $stmt->execute([$competencia]);
      foreach ($stmt->fetchAll() as $r) {
          $k = $r['socio_id'] === null ? 'empresa' : 'socio_' . (int)$r['socio_id'];
          if (!isset($ja_pago_por[$k])) $ja_pago_por[$k] = ['BRL'=>0,'USD'=>0,'EUR'=>0];
          $ja_pago_por[$k][$r['moeda']] = (float)$r['total'];
      }
  } catch (PDOException $e) { /* tabela ainda não criada */ }

  function bloco_socio($nome, $key, $is_empresa, $tag_html, $quota_brl, $quota_usd, $quota_eur, $ja_pago_por, $competencia, $u_id) {
      $jp = $ja_pago_por[$key] ?? ['BRL'=>0,'USD'=>0,'EUR'=>0];
      $pendente = [
          'BRL' => max(0, $quota_brl - $jp['BRL']),
          'USD' => max(0, $quota_usd - $jp['USD']),
          'EUR' => max(0, $quota_eur - $jp['EUR']),
      ];
      $tem_pendente = $pendente['BRL'] + $pendente['USD'] + $pendente['EUR'] > 0.001;
      ?>
      <div class="card<?= $is_empresa ? '' : '' ?>" <?= $is_empresa ? 'style="border-color:var(--c-orange);"' : '' ?>>
        <div class="title"><?= e($nome) ?> <?= $tag_html ?></div>
        <div class="sub muted">1 quota</div>
        <div class="grid-2 mt-3">
          <div>
            <div class="muted" style="font-size:11px;">Quota total</div>
            <div class="money md"><?= e(money_fmt($quota_brl, 'BRL')) ?></div>
            <div class="money md"><?= e(money_fmt($quota_usd, 'USD')) ?></div>
            <div class="money md"><?= e(money_fmt($quota_eur, 'EUR')) ?></div>
          </div>
          <div>
            <div class="muted" style="font-size:11px;">Já pago</div>
            <div class="money md" style="color:var(--c-success);"><?= e(money_fmt($jp['BRL'], 'BRL')) ?></div>
            <div class="money md" style="color:var(--c-success);"><?= e(money_fmt($jp['USD'], 'USD')) ?></div>
            <div class="money md" style="color:var(--c-success);"><?= e(money_fmt($jp['EUR'], 'EUR')) ?></div>
          </div>
        </div>
        <?php if ($tem_pendente): ?>
          <div class="mt-3">
            <details>
              <summary class="btn btn-secondary block" style="cursor:pointer;"><?= $is_empresa ? '🏦 Registrar retenção da empresa' : '💵 Registrar pagamento' ?></summary>
              <?php foreach (['BRL','USD','EUR'] as $m): if ($pendente[$m] <= 0) continue; ?>
                <form method="post" class="mt-3" onsubmit="return confirmaPagar(this);">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="op" value="pagar_socio">
                  <input type="hidden" name="socio_id" value="<?= e((string)($is_empresa ? 'empresa' : (int)str_replace('socio_', '', $key))) ?>">
                  <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
                  <input type="hidden" name="moeda" value="<?= e($m) ?>">
                  <div class="grid-2">
                    <div class="field"><label>Valor (<?= e($m) ?>)</label><input type="number" step="0.01" min="0.01" name="valor" required value="<?= e(number_format($pendente[$m], 2, '.', '')) ?>" data-moeda="<?= e($m) ?>" oninput="atualizaBtnPagar(this)"></div>
                    <div class="field"><label>Data</label><input type="date" name="data_pagamento" required value="<?= e(date('Y-m-d')) ?>"></div>
                  </div>
                  <div class="field"><label>Observação</label><input name="observacao" placeholder="opcional"></div>
                  <button class="btn block" type="submit" data-btn-pagar><?= e(money_fmt($pendente[$m], $m)) ?> · marcar como pago</button>
                </form>
              <?php endforeach; ?>
            </details>
          </div>
        <?php else: ?>
          <div class="mt-3"><span class="status status-paga">✅ pago neste mês</span></div>
        <?php endif; ?>
      </div>
      <?php
  }

  $quota_brl = $liq_mes['BRL'] / $total_quotas;
  $quota_usd = $liq_mes['USD'] / $total_quotas;
  $quota_eur = $liq_mes['EUR'] / $total_quotas;
?>

<h2>Sócios ativos</h2>
<?php foreach ($socios as $s):
    $key = 'socio_' . (int)$s['id'];
    $tag = '<span class="status status-' . ($s['role']==='sadmin'?'destaque':'ia') . '">' . ($s['role']==='sadmin'?'super admin':'admin') . '</span>';
    if ((int)$s['id'] === (int)$u['id']) $tag .= ' <span class="status status-paga">você</span>';
    bloco_socio($s['nome'], $key, false, $tag, $quota_brl, $quota_usd, $quota_eur, $ja_pago_por, $competencia, (int)$u['id']);
?>
<?php endforeach; ?>

<?php bloco_socio('🏢 Empresa (reserva)', 'empresa', true, '', $quota_brl, $quota_usd, $quota_eur, $ja_pago_por, $competencia, (int)$u['id']); ?>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?> mt-3"><?= e($flash[1]) ?></div><?php endif; ?>

<?php
  // Lista pagamentos individuais do mês — pra sadmin poder apagar entradas erradas
  $pagamentos_mes = [];
  try {
      $stmt = $db->prepare("SELECT ps.id, ps.socio_id, ps.moeda, ps.valor, ps.data_pagamento, ps.observacao,
                                    u.nome AS socio_nome
                             FROM pagamentos_socio ps
                             LEFT JOIN usuarios u ON u.id = ps.socio_id
                             WHERE ps.competencia_mes = ?
                             ORDER BY ps.data_pagamento DESC, ps.id DESC");
      $stmt->execute([$competencia]);
      $pagamentos_mes = $stmt->fetchAll();
  } catch (PDOException $e) {}
?>

<?php if ($pagamentos_mes): ?>
  <h2 class="mt-5">Pagamentos lançados em <?= e($nome_mes) ?></h2>
  <?php foreach ($pagamentos_mes as $p):
      $nome_destino = $p['socio_id'] === null ? '🏢 Empresa (reserva)' : $p['socio_nome'];
  ?>
    <div class="card spaced">
      <div class="info" style="flex:1;">
        <div class="nome"><?= e($nome_destino) ?></div>
        <div class="sub muted">
          <?= e(date('d/m/Y', strtotime($p['data_pagamento']))) ?>
          <?php if ($p['observacao']): ?> · <?= e($p['observacao']) ?><?php endif; ?>
        </div>
      </div>
      <div class="right" style="display:flex; gap:8px; align-items:center;">
        <div class="money md" style="color:var(--c-danger);">− <?= e(money_fmt((float)$p['valor'], $p['moeda'])) ?></div>
        <?php if (is_sadmin()): ?>
          <form method="post" style="margin:0;" onsubmit="return confirm('Apagar este pagamento de <?= e($nome_destino) ?> (<?= e(money_fmt((float)$p['valor'], $p['moeda'])) ?>)?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="apagar_pagamento_socio">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
            <button class="btn btn-ghost small" type="submit" title="Apagar" style="color:var(--c-danger);">🗑</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

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

<script>
// Mantém o rótulo do botão e o confirm em sincronia com o valor digitado,
// pra não lançar um valor diferente do que aparece no botão.
function _moneyFmt(v, moeda) {
  if (isNaN(v)) return '';
  var neg = v < 0; v = Math.abs(v);
  var p = v.toFixed(2).split('.'), intPart = p[0], dec = p[1];
  var thou, decSep, prefix;
  if (moeda === 'BRL')      { thou = '.'; decSep = ','; prefix = 'R$ '; }
  else if (moeda === 'USD') { thou = ','; decSep = '.'; prefix = '$'; }
  else if (moeda === 'EUR') { thou = '.'; decSep = ','; prefix = '€'; }
  else                      { thou = ','; decSep = '.'; prefix = moeda + ' '; }
  intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thou);
  return (neg ? '-' : '') + prefix + intPart + decSep + dec;
}
function _valorDoForm(input) {
  return parseFloat(String(input && input.value || '').replace(',', '.'));
}
function atualizaBtnPagar(input) {
  var form = input.closest('form'); if (!form) return;
  var btn = form.querySelector('[data-btn-pagar]'); if (!btn) return;
  var v = _valorDoForm(input);
  btn.textContent = (isNaN(v) ? '' : _moneyFmt(v, input.dataset.moeda || '') + ' · ') + 'marcar como pago';
}
function confirmaPagar(form) {
  var input = form.querySelector('input[name="valor"]');
  var v = _valorDoForm(input);
  var txt = isNaN(v) ? 'este valor' : _moneyFmt(v, input.dataset.moeda || '');
  return confirm('Confirmar pagamento de ' + txt + '?');
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
