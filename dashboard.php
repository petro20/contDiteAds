<?php
require_once __DIR__ . '/includes/auth.php';
$u  = require_login();
$db = db();

function brl(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

$page = 'Início';
require __DIR__ . '/includes/header.php';
?>
<h1>Olá, <?= e($u['nome']) ?></h1>

<?php if ($u['role'] === 'admin'):
    $totClientes  = (int)$db->query('SELECT COUNT(*) FROM clientes WHERE ativo=1')->fetchColumn();
    $totFunc      = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE role='funcionario' AND ativo=1")->fetchColumn();
    $aRecebMes    = (float)$db->query("SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos p WHERE YEAR(p.data_pagamento)=YEAR(CURDATE()) AND MONTH(p.data_pagamento)=MONTH(CURDATE())")->fetchColumn();
    $emAberto     = (float)$db->query("SELECT COALESCE(SUM(c.valor) - (SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos WHERE cobranca_id=c.id), 0) FROM cobrancas c WHERE c.status='aberta'")->fetchColumn();
    $vencido      = (float)$db->query("SELECT COALESCE(SUM(c.valor) - (SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos WHERE cobranca_id=c.id), 0) FROM cobrancas c WHERE c.status='aberta' AND c.vencimento IS NOT NULL AND c.vencimento < CURDATE()")->fetchColumn();
    $abertasCount = (int)$db->query("SELECT COUNT(*) FROM cobrancas WHERE status='aberta'")->fetchColumn();

    $proximas = $db->query("
        SELECT c.id, c.descricao, c.valor, c.vencimento, c.status,
               cl.nome AS cliente,
               (SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos WHERE cobranca_id=c.id) AS pago
        FROM cobrancas c
        JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.status='aberta'
        ORDER BY c.vencimento IS NULL, c.vencimento ASC, c.id DESC
        LIMIT 10
    ")->fetchAll();
?>
<div class="grid-2">
  <div class="card"><strong><?= $totClientes ?></strong><div class="muted">Clientes ativos</div></div>
  <div class="card"><strong><?= $totFunc ?></strong><div class="muted">Funcionários ativos</div></div>
  <div class="card"><strong><?= brl($aRecebMes) ?></strong><div class="muted">Recebido no mês</div></div>
  <div class="card"><strong><?= brl($emAberto) ?></strong><div class="muted">Em aberto (<?= $abertasCount ?>)</div></div>
  <?php if ($vencido > 0): ?>
  <div class="card" style="border-left:4px solid #dc2626;"><strong><?= brl($vencido) ?></strong><div class="muted">Vencido</div></div>
  <?php endif; ?>
</div>

<h2>Próximas cobranças em aberto</h2>
<?php if (!$proximas): ?>
  <p class="muted">Nenhuma cobrança em aberto.</p>
<?php else: ?>
<table>
  <thead><tr><th>#</th><th>Cliente</th><th>Descrição</th><th>Valor</th><th>Saldo</th><th>Vencimento</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($proximas as $c): $saldo = max((float)$c['valor'] - (float)$c['pago'], 0); ?>
    <tr>
      <td>#<?= (int)$c['id'] ?></td>
      <td><?= e($c['cliente']) ?></td>
      <td><?= e($c['descricao']) ?></td>
      <td><?= brl((float)$c['valor']) ?></td>
      <td><?= brl($saldo) ?></td>
      <td><?= $c['vencimento'] ? e(date('d/m/Y', strtotime($c['vencimento']))) : '—' ?></td>
      <td><a class="btn small" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$c['id'] ?>">Abrir</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php elseif ($u['role'] === 'funcionario'):
    $stmt = $db->prepare("SELECT COUNT(DISTINCT cliente_id) FROM cobrancas WHERE funcionario_id=?");
    $stmt->execute([$u['id']]);
    $clientesAtendidos = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM cobrancas WHERE funcionario_id=? AND status='aberta'");
    $stmt->execute([$u['id']]);
    $minhasAbertas = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos p JOIN cobrancas c ON c.id=p.cobranca_id WHERE c.funcionario_id=? AND YEAR(p.data_pagamento)=YEAR(CURDATE()) AND MONTH(p.data_pagamento)=MONTH(CURDATE())");
    $stmt->execute([$u['id']]);
    $recebMes = (float)$stmt->fetchColumn();
    $comissaoMes = $recebMes * ((float)$u['percentual_comissao'] / 100.0);

    $stmt = $db->prepare("SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos p JOIN cobrancas c ON c.id=p.cobranca_id WHERE c.funcionario_id=?");
    $stmt->execute([$u['id']]);
    $recebTotal = (float)$stmt->fetchColumn();
    $comissaoTotal = $recebTotal * ((float)$u['percentual_comissao'] / 100.0);

    $stmt = $db->prepare("
        SELECT c.id, c.descricao, c.valor, c.vencimento, c.status,
               cl.nome AS cliente,
               (SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos WHERE cobranca_id=c.id) AS pago
        FROM cobrancas c
        JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.funcionario_id = ?
        ORDER BY c.status='paga' ASC, c.vencimento IS NULL, c.vencimento ASC, c.id DESC
        LIMIT 15
    ");
    $stmt->execute([$u['id']]);
    $minhas = $stmt->fetchAll();
?>
<div class="grid-2">
  <div class="card"><strong><?= $clientesAtendidos ?></strong><div class="muted">Clientes que atendo</div></div>
  <div class="card"><strong><?= $minhasAbertas ?></strong><div class="muted">Minhas cobranças em aberto</div></div>
  <div class="card"><strong><?= brl($recebMes) ?></strong><div class="muted">Recebido no mês (meus clientes)</div></div>
  <div class="card" style="border-left:4px solid #16a34a;"><strong><?= brl($comissaoMes) ?></strong><div class="muted">Minha comissão no mês (<?= number_format((float)$u['percentual_comissao'], 2, ',', '.') ?>%)</div></div>
  <div class="card"><strong><?= brl($recebTotal) ?></strong><div class="muted">Recebido total (meus clientes)</div></div>
  <div class="card" style="border-left:4px solid #16a34a;"><strong><?= brl($comissaoTotal) ?></strong><div class="muted">Comissão acumulada total</div></div>
</div>

<h2>Minhas cobranças</h2>
<?php if (!$minhas): ?>
  <p class="muted">Nenhuma cobrança atribuída a você.</p>
<?php else: ?>
<table>
  <thead><tr><th>#</th><th>Cliente</th><th>Descrição</th><th>Valor</th><th>Pago</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($minhas as $c): ?>
    <tr>
      <td>#<?= (int)$c['id'] ?></td>
      <td><?= e($c['cliente']) ?></td>
      <td><?= e($c['descricao']) ?></td>
      <td><?= brl((float)$c['valor']) ?></td>
      <td><?= brl((float)$c['pago']) ?></td>
      <td><span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
      <td><a class="btn small" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$c['id'] ?>">Abrir</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php elseif ($u['role'] === 'cliente'):
    $stmt = $db->prepare("SELECT COALESCE(SUM(c.valor),0) - COALESCE((SELECT SUM(p.valor_pago) FROM pagamentos p JOIN cobrancas c2 ON c2.id=p.cobranca_id WHERE c2.cliente_id=? AND c2.status='aberta'),0) FROM cobrancas c WHERE c.cliente_id=? AND c.status='aberta'");
    $stmt->execute([$u['cliente_id'], $u['cliente_id']]);
    $saldoAberto = max((float)$stmt->fetchColumn(), 0);

    $stmt = $db->prepare("SELECT COALESCE(SUM(p.valor_pago),0) FROM pagamentos p JOIN cobrancas c ON c.id=p.cobranca_id WHERE c.cliente_id=?");
    $stmt->execute([$u['cliente_id']]);
    $totalPago = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT c.id, c.descricao, c.valor, c.vencimento, c.status,
               (SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos WHERE cobranca_id=c.id) AS pago
        FROM cobrancas c
        WHERE c.cliente_id = ?
        ORDER BY c.status='paga' ASC, c.vencimento IS NULL, c.vencimento ASC, c.id DESC
        LIMIT 20
    ");
    $stmt->execute([$u['cliente_id']]);
    $cobr = $stmt->fetchAll();
?>
<div class="grid-2">
  <div class="card"><strong><?= brl($saldoAberto) ?></strong><div class="muted">Em aberto</div></div>
  <div class="card"><strong><?= brl($totalPago) ?></strong><div class="muted">Total pago</div></div>
</div>

<h2>Minhas cobranças</h2>
<?php if (!$cobr): ?>
  <p class="muted">Nenhuma cobrança.</p>
<?php else: ?>
<table>
  <thead><tr><th>#</th><th>Descrição</th><th>Valor</th><th>Pago</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($cobr as $c): ?>
    <tr>
      <td>#<?= (int)$c['id'] ?></td>
      <td><?= e($c['descricao']) ?></td>
      <td><?= brl((float)$c['valor']) ?></td>
      <td><?= brl((float)$c['pago']) ?></td>
      <td><?= $c['vencimento'] ? e(date('d/m/Y', strtotime($c['vencimento']))) : '—' ?></td>
      <td><span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
      <td><a class="btn small" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$c['id'] ?>">Detalhes</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
