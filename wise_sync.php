<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/wise.php';
require_once __DIR__ . '/lib/pagamentos.php';
$me = require_sadmin();
$db = db();
$flash = null;

// Filtros
$moeda = strtoupper((string)($_GET['moeda'] ?? 'USD'));
if (!in_array($moeda, ['USD','BRL','EUR'], true)) $moeda = 'USD';
$dias = max(1, min(90, (int)($_GET['dias'] ?? 14)));
$ate = new DateTimeImmutable();
$de  = $ate->modify("-{$dias} days");
$de_iso  = $de->format('Y-m-d\T00:00:00.000\Z');
$ate_iso = $ate->format('Y-m-d\T23:59:59.999\Z');

// Confirmar registro de pagamentos casados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'confirmar_match') {
    csrf_check();
    $items = $_POST['match'] ?? [];
    $n = 0;
    foreach ($items as $key => $cobranca_id) {
        $cid = (int)$cobranca_id;
        if (!$cid) continue;
        $valor = (float)str_replace(',', '.', (string)($_POST['valor'][$key] ?? '0'));
        $data  = (string)($_POST['data'][$key] ?? date('Y-m-d'));
        $ref   = trim((string)($_POST['ref'][$key] ?? ''));
        $obs   = 'Wise auto-sync · ref ' . $ref;
        try {
            registrar_pagamento_cliente($db, $cid, $valor, $data, 'Wise', $obs, null, (int)$me['id'], false);
            $n++;
        } catch (Throwable $e) {
            error_log('Wise sync erro: ' . $e->getMessage());
        }
    }
    audit_log('wise.sync', 'cobrancas', $n);
    header('Location: ' . APP_BASE_URL . '/wise_sync.php?moeda=' . urlencode($moeda) . '&dias=' . $dias . '&ok=' . $n); exit;
}

if (isset($_GET['ok'])) $flash = ['ok', (int)$_GET['ok'] . ' pagamento(s) registrado(s) com sucesso!'];

// Busca transações Wise
$transacoes = wise_buscar_creditos($db, $moeda, $de_iso, $ate_iso);
$erro_api = $transacoes['__error'] ?? null;
if ($erro_api) $transacoes = [];

// Busca cobranças abertas dessa moeda pra tentar casar
$stmt = $db->prepare("
    SELECT c.id, c.cliente_id, c.valor_total, c.moeda, c.status, c.vencimento, cl.nome_empresa
    FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
    WHERE c.status IN ('aberta','em_analise') AND c.moeda = ?
    ORDER BY c.vencimento
");
$stmt->execute([$moeda]);
$cobrancas_abertas = $stmt->fetchAll();

// Pra cada cobrança, conta o que já foi pago (pra calcular saldo)
foreach ($cobrancas_abertas as &$c) {
    $stmt2 = $db->prepare('SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos_cliente WHERE cobranca_id = ?');
    $stmt2->execute([(int)$c['id']]);
    $c['pago'] = (float)$stmt2->fetchColumn();
    $c['saldo'] = (float)$c['valor_total'] - $c['pago'];
}
unset($c);

// Tentar casar cada transação com uma cobrança aberta
// Critério: valor exato (com tolerância $0.01) + moeda match
function casar_transacao(array $tx, array $cobrancas): ?array {
    $val = (float)($tx['amount']['value'] ?? 0);
    foreach ($cobrancas as $cob) {
        if (abs($cob['saldo'] - $val) < 0.01 && $cob['saldo'] > 0) {
            return $cob;
        }
    }
    return null;
}

$matched   = [];
$unmatched = [];
foreach ($transacoes as $tx) {
    $cob = casar_transacao($tx, $cobrancas_abertas);
    if ($cob) $matched[]   = ['tx' => $tx, 'cob' => $cob];
    else      $unmatched[] = $tx;
}

$page = 'Wise — Sincronizar pagamentos';
$show_back = true;
$back_to = APP_BASE_URL . '/config_pagamento.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">🌍 Wise — Sincronizar pagamentos</h1>
<p class="muted">Busca os créditos recebidos no Wise e tenta casar com cobranças abertas. Você revisa e confirma antes de registrar.</p>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if ($erro_api): ?>
  <div class="flash err"><?= e($erro_api) ?></div>
  <p class="muted">Verifique a API key e Profile ID em <a href="<?= e(APP_BASE_URL) ?>/config_pagamento.php" style="color:var(--c-primary-2);">Finanças → Pagamentos</a>.</p>
<?php endif; ?>

<form method="get" class="card">
  <div class="grid-2">
    <div class="field">
      <label>Moeda</label>
      <select name="moeda" onchange="this.form.submit()">
        <option value="USD" <?= $moeda==='USD'?'selected':'' ?>>$ USD</option>
        <option value="EUR" <?= $moeda==='EUR'?'selected':'' ?>>€ EUR</option>
        <option value="BRL" <?= $moeda==='BRL'?'selected':'' ?>>R$ BRL</option>
      </select>
    </div>
    <div class="field">
      <label>Últimos N dias</label>
      <input type="number" name="dias" value="<?= (int)$dias ?>" min="1" max="90" onchange="this.form.submit()">
    </div>
  </div>
  <div class="hint">Período: <?= e($de->format('d/m/Y')) ?> → <?= e($ate->format('d/m/Y')) ?> · <?= count($transacoes) ?> crédito(s) encontrado(s) no Wise.</div>
</form>

<?php if ($matched): ?>
  <h2 class="mt-5">✅ Pagamentos casados (<?= count($matched) ?>)</h2>
  <p class="muted" style="font-size:13px;">Esses créditos do Wise batem exatamente com cobranças abertas. Revise e confirme.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="confirmar_match">
    <?php foreach ($matched as $idx => $m):
      $tx  = $m['tx'];
      $cob = $m['cob'];
      $data_iso = substr((string)($tx['date'] ?? ''), 0, 10);
      $ref = $tx['referenceNumber'] ?? ($tx['details']['paymentReference'] ?? '');
      $payer = $tx['details']['senderName'] ?? '';
    ?>
      <div class="card success" style="margin-bottom:var(--s-3);">
        <label class="check" style="font-weight:600;">
          <input type="checkbox" name="match[<?= (int)$idx ?>]" value="<?= (int)$cob['id'] ?>" checked>
          Registrar este pagamento
        </label>
        <div class="info-pair muted" style="font-size:12px; margin-top:8px;">
          <span class="l">🌍 Wise: <?= e($payer) ?: '—' ?></span>
          <span class="v"><?= e($cob['moeda']) ?> <?= number_format((float)($tx['amount']['value'] ?? 0), 2, ',', '.') ?> · <?= e(date('d/m/Y', strtotime($data_iso))) ?></span>
        </div>
        <div class="info-pair" style="font-size:13px;">
          <span class="l">💳 Cobrança</span>
          <span class="v"><strong><?= e($cob['nome_empresa']) ?></strong> · saldo <?= e($cob['moeda']) ?> <?= number_format((float)$cob['saldo'], 2, ',', '.') ?></span>
        </div>
        <input type="hidden" name="valor[<?= (int)$idx ?>]" value="<?= e(number_format((float)($tx['amount']['value'] ?? 0), 2, '.', '')) ?>">
        <input type="hidden" name="data[<?= (int)$idx ?>]" value="<?= e($data_iso) ?>">
        <input type="hidden" name="ref[<?= (int)$idx ?>]" value="<?= e($ref) ?>">
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-success block mt-3">✓ Confirmar e registrar (<?= count($matched) ?>) pagamentos</button>
  </form>
<?php endif; ?>

<?php if ($unmatched): ?>
  <h2 class="mt-5">❓ Créditos sem casamento (<?= count($unmatched) ?>)</h2>
  <p class="muted" style="font-size:13px;">Esses créditos do Wise não batem com nenhuma cobrança em aberto. Pode ser: cobrança duplicada, valor diferente, ou pagamento não relacionado.</p>
  <?php foreach ($unmatched as $tx):
    $data_iso = substr((string)($tx['date'] ?? ''), 0, 10);
    $payer = $tx['details']['senderName'] ?? '—';
    $valor = (float)($tx['amount']['value'] ?? 0);
  ?>
    <div class="card attention">
      <div class="info-pair">
        <span class="l">🌍 <?= e($payer) ?></span>
        <span class="v"><strong><?= e($moeda) ?> <?= number_format($valor, 2, ',', '.') ?></strong></span>
      </div>
      <div class="info-pair muted" style="font-size:12px;">
        <span class="l"><?= e(date('d/m/Y', strtotime($data_iso))) ?></span>
        <span class="v">ref: <?= e($tx['referenceNumber'] ?? '—') ?></span>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!$transacoes && !$erro_api): ?>
  <div class="card"><div class="title muted">Nenhum crédito encontrado nesse período</div><div class="desc">Ajuste a moeda ou período acima.</div></div>
<?php endif; ?>

<?php if ($cobrancas_abertas): ?>
  <details class="card mt-5">
    <summary class="muted" style="cursor:pointer; padding:8px 0;">📋 Cobranças <?= e($moeda) ?> em aberto (<?= count($cobrancas_abertas) ?>)</summary>
    <div style="margin-top:8px;">
      <?php foreach ($cobrancas_abertas as $c): if ($c['saldo'] <= 0) continue; ?>
        <div class="info-pair" style="padding:6px 0; border-bottom:1px solid var(--border); font-size:13px;">
          <span class="l"><strong><?= e($c['nome_empresa']) ?></strong></span>
          <span class="v"><?= e($c['moeda']) ?> <?= number_format($c['saldo'], 2, ',', '.') ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
