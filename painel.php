<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/money.php';
require_admin();
$db = db();

$aba = $_GET['aba'] ?? 'agenda';
if (!in_array($aba, ['agenda','clientes','servicos'], true)) $aba = 'agenda';

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');
$mes_inicio = $competencia . '-01';
$mes_fim    = date('Y-m-t', strtotime($mes_inicio));

$page = 'Painel financeiro';
$nav_active = 'painel';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Painel</h1>

<nav class="tabs-bar">
  <a class="<?= $aba==='agenda'?'active':'' ?>" href="?aba=agenda&mes=<?= e($competencia) ?>">Agenda</a>
  <a class="<?= $aba==='clientes'?'active':'' ?>" href="?aba=clientes&mes=<?= e($competencia) ?>">Por cliente</a>
  <a class="<?= $aba==='servicos'?'active':'' ?>" href="?aba=servicos&mes=<?= e($competencia) ?>">Por serviço</a>
</nav>

<?php if ($aba === 'agenda'):
    // Cobranças vencidas
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
<div class="section-label">Resumo do mês (<?= e($competencia) ?>)</div>
<?php foreach (['BRL','USD','EUR'] as $m):
    $rec = $por_moeda[$m]['recebido']; $rcv = $por_moeda[$m]['receber']; $ana = $por_moeda[$m]['analise'];
    if ($rec == 0 && $rcv == 0 && $ana == 0) continue;
?>
  <div class="card">
    <div class="title"><?= $m ?> · este mês</div>
    <div class="info-pair"><span class="l">Recebido</span><span class="v" style="color:var(--c-success);"><?= e(money_fmt($rec, $m)) ?></span></div>
    <?php if ($ana > 0): ?>
      <div class="info-pair"><span class="l">Em análise</span><span class="v" style="color:var(--c-orange);"><?= e(money_fmt($ana, $m)) ?></span></div>
    <?php endif; ?>
    <div class="info-pair"><span class="l">A receber</span><span class="v"><?= e(money_fmt($rcv, $m)) ?></span></div>
  </div>
<?php endforeach; ?>

<?php
  $tem_total = false;
  foreach (['BRL','USD','EUR'] as $m) {
      if ($por_moeda_total[$m]['recebido'] + $por_moeda_total[$m]['receber'] + $por_moeda_total[$m]['analise'] > 0) { $tem_total = true; break; }
  }
?>
<?php if ($tem_total): ?>
  <div class="section-label">Acumulado total (todas as cobranças até hoje)</div>
  <?php foreach (['BRL','USD','EUR'] as $m):
      $rec = $por_moeda_total[$m]['recebido']; $rcv = $por_moeda_total[$m]['receber']; $ana = $por_moeda_total[$m]['analise'];
      if ($rec == 0 && $rcv == 0 && $ana == 0) continue;
  ?>
    <div class="card">
      <div class="title"><?= $m ?> · total</div>
      <div class="info-pair"><span class="l">Recebido</span><span class="v" style="color:var(--c-success);"><?= e(money_fmt($rec, $m)) ?></span></div>
      <?php if ($ana > 0): ?>
        <div class="info-pair"><span class="l">Em análise</span><span class="v" style="color:var(--c-orange);"><?= e(money_fmt($ana, $m)) ?></span></div>
      <?php endif; ?>
      <div class="info-pair"><span class="l">A receber</span><span class="v"><?= e(money_fmt($rcv, $m)) ?></span></div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($vencidas): ?>
<div class="section-label">⚠️ Vencidas (<?= count($vencidas) ?>)</div>
<?php foreach ($vencidas as $v): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$v['id'] ?>" style="border-color:var(--c-danger);">
    <div class="info">
      <div class="nome"><?= e($v['nome_empresa']) ?></div>
      <div class="sub">venc <?= e(date('d/m/Y', strtotime($v['vencimento']))) ?></div>
    </div>
    <div class="right"><div class="money md"><?= e(money_fmt((float)$v['valor_total'], $v['moeda'])) ?></div></div>
  </a>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($proximas): ?>
<div class="section-label">📅 Vencendo nos próximos 7 dias</div>
<?php foreach ($proximas as $p): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/cobrancas.php?id=<?= (int)$p['id'] ?>">
    <div class="info">
      <div class="nome"><?= e($p['nome_empresa']) ?></div>
      <div class="sub">venc <?= e(date('d/m/Y', strtotime($p['vencimento']))) ?></div>
    </div>
    <div class="right"><div class="money md"><?= e(money_fmt((float)$p['valor_total'], $p['moeda'])) ?></div></div>
  </a>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!$vencidas && !$proximas): ?>
  <p class="muted center mt-5">Nenhuma cobrança vencida ou vencendo nos próximos 7 dias.</p>
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
<div class="section-label">Por cliente (<?= count($clis) ?>)</div>
<?php foreach ($clis as $c): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/clientes.php?acao=editar&id=<?= (int)$c['id'] ?>">
    <div class="info">
      <div class="nome"><?= e($c['nome_empresa']) ?> <span class="muted">(<?= $c['moeda'] ?>)</span></div>
      <div class="sub">
        <?= (int)$c['qtd_assin'] ?> assin. ativas ·
        cobrado <?= e(money_fmt((float)$c['total_cobrado'], $c['moeda'])) ?> ·
        pago <?= e(money_fmt((float)$c['total_pago'], $c['moeda'])) ?>
      </div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$c['em_aberto'], $c['moeda'])) ?></div>
      <div class="muted" style="font-size:11px;">em aberto</div>
    </div>
  </a>
<?php endforeach; ?>

<?php else: /* servicos */
    $stmt = $db->prepare("
        SELECT i.id, i.nome, i.tipo,
               COUNT(DISTINCT CASE WHEN a.status = 'ativa' THEN a.cliente_id END) AS qtd_clientes,
               COUNT(DISTINCT a.id) FILTER (WHERE 1=1) AS qtd_assin
        FROM itens_catalogo i
        LEFT JOIN assinaturas a ON a.item_id = i.id AND a.status = 'ativa'
        WHERE i.ativo = 1
        GROUP BY i.id
        ORDER BY qtd_clientes DESC, i.nome
    ");
    // MySQL não suporta FILTER WHERE — usar sub-query
    $stmt = $db->prepare("
        SELECT i.id, i.nome, i.tipo, i.e_pacote,
               (SELECT COUNT(DISTINCT cliente_id) FROM assinaturas WHERE item_id = i.id AND status = 'ativa') AS qtd_clientes,
               (SELECT COUNT(*) FROM assinaturas WHERE item_id = i.id AND status = 'ativa') AS qtd_assin
        FROM itens_catalogo i
        WHERE i.ativo = 1
        ORDER BY qtd_clientes DESC, i.nome
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();
?>
<div class="section-label">Por serviço (<?= count($items) ?>)</div>
<?php foreach ($items as $it): ?>
  <a class="list-card" href="<?= e(APP_BASE_URL) ?>/catalogo.php?acao=editar&id=<?= (int)$it['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($it['nome']) ?>
        <?php if ($it['e_pacote']): ?><span class="status status-ia">pacote</span><?php endif; ?>
      </div>
      <div class="sub"><?= e($it['tipo']) ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= (int)$it['qtd_clientes'] ?></div>
      <div class="muted" style="font-size:11px;">clientes</div>
    </div>
  </a>
<?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
