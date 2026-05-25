<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/money.php';
require_once __DIR__ . '/lib/cobrancas.php';
$me = require_login();
$db = db();

$acao = $_GET['acao'] ?? 'lista';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$flash = null;

function pode_ver_cobranca(array $c, array $me): bool {
    if ($me['role'] === 'admin') return true;
    if ($me['role'] === 'cliente') return (int)$c['cliente_id'] === (int)$me['cliente_id'];
    if ($me['role'] === 'funcionario') {
        // funcionário vê se tem assinatura dele em algum item da cobrança
        $stmt = db()->prepare('SELECT 1 FROM cobranca_itens ci JOIN assinaturas a ON a.id = ci.assinatura_id WHERE ci.cobranca_id = ? AND a.funcionario_id = ? LIMIT 1');
        $stmt->execute([(int)$c['id'], (int)$me['id']]);
        return (bool)$stmt->fetchColumn();
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!is_admin()) { http_response_code(403); exit('Apenas admin.'); }
    $op = $_POST['op'] ?? '';

    if ($op === 'gerar_manual') {
        $cliente_id = (int)($_POST['cliente_id'] ?? 0);
        $competencia = trim((string)($_POST['competencia'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');
        $r = gerar_cobranca_mensal($db, $cliente_id, $competencia);
        $map = ['created'=>'Cobrança criada.','exists'=>'Já existia (não foi sobrescrita).','empty'=>'Cliente não tem assinaturas elegíveis no mês.','cliente_nao_encontrado'=>'Cliente não encontrado.'];
        $tipo = $r['status']==='created' ? 'ok' : 'err';
        $msg = $map[$r['status']] ?? 'Resultado desconhecido.';
        if ($r['cobranca_id']) {
            audit_log('cobranca.gerada_manual', 'cobrancas', (int)$r['cobranca_id']);
            header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . (int)$r['cobranca_id']); exit;
        }
        $flash = [$tipo, $msg];
    }

    if ($op === 'cancelar') {
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE cobrancas SET status='cancelada' WHERE id=?");
        $stmt->execute([$cid]);
        audit_log('cobranca.cancelada', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }

    if ($op === 'reabrir') {
        $cid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE cobrancas SET status='aberta' WHERE id=?");
        $stmt->execute([$cid]);
        audit_log('cobranca.reaberta', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/cobrancas.php?id=' . $cid); exit;
    }
}

$page = 'Cobranças';
$nav_active = 'cobrancas';

if ($id) {
    $stmt = $db->prepare('SELECT c.*, cl.nome_empresa, cl.moeda AS cli_moeda FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
    $stmt->execute([$id]);
    $cob = $stmt->fetch();
    if (!$cob || !pode_ver_cobranca($cob, $me)) {
        http_response_code(404);
        $show_back = true; $back_to = APP_BASE_URL . '/cobrancas.php';
        require __DIR__ . '/includes/header.php';
        echo '<h1 class="page-title">Cobrança não encontrada</h1>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
    $stmt = $db->prepare('SELECT ci.*, a.funcionario_id, u.nome AS func_nome FROM cobranca_itens ci LEFT JOIN assinaturas a ON a.id = ci.assinatura_id LEFT JOIN usuarios u ON u.id = a.funcionario_id WHERE ci.cobranca_id = ? ORDER BY ci.id');
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM pagamentos_cliente WHERE cobranca_id = ? ORDER BY data_pagamento DESC');
    $stmt->execute([$id]);
    $pagamentos = $stmt->fetchAll();
    $pago = (float)array_sum(array_column($pagamentos, 'valor_pago'));
    $saldo = max((float)$cob['valor_total'] - $pago, 0);
    $vencido = $cob['status'] === 'aberta' && strtotime($cob['vencimento']) < strtotime(date('Y-m-d'));

    $show_back = true; $back_to = APP_BASE_URL . '/cobrancas.php';
    $page = 'Cobrança #' . (int)$cob['id'];
    $page_sub = $cob['nome_empresa'] . ' · ' . $cob['competencia_mes'];
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

    <div class="card <?= $vencido ? 'danger' : ($cob['status']==='paga' ? 'success' : '') ?>">
      <div class="spaced">
        <div>
          <div class="muted" style="font-size:12px; text-transform:uppercase;">Total</div>
          <div class="money lg"><?= e(money_fmt((float)$cob['valor_total'], $cob['moeda'])) ?></div>
        </div>
        <span class="status status-<?= e($cob['status']) ?>"><?= e($cob['status']) ?><?= $vencido?' · vencida':'' ?></span>
      </div>
      <div class="grid-2 mt-3">
        <div><div class="muted" style="font-size:12px;">Vencimento</div><strong><?= e(date('d/m/Y', strtotime($cob['vencimento']))) ?></strong></div>
        <div><div class="muted" style="font-size:12px;">Pago</div><strong><?= e(money_fmt($pago, $cob['moeda'])) ?></strong></div>
        <div><div class="muted" style="font-size:12px;">Saldo</div><strong><?= e(money_fmt($saldo, $cob['moeda'])) ?></strong></div>
        <div><div class="muted" style="font-size:12px;">Competência</div><strong><?= e($cob['competencia_mes']) ?></strong></div>
      </div>
    </div>

    <h2>Itens da cobrança</h2>
    <?php foreach ($itens as $it): ?>
      <div class="card">
        <div class="spaced">
          <div>
            <div class="title"><?= e($it['descricao']) ?></div>
            <div class="sub muted"><?= (int)$it['quantidade'] ?>× <?= e(money_fmt((float)$it['valor_unitario'], $cob['moeda'])) ?><?= $it['func_nome'] ? ' · ' . e($it['func_nome']) : '' ?></div>
          </div>
          <div class="money md"><?= e(money_fmt((float)$it['subtotal'], $cob['moeda'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>

    <h2>Pagamentos</h2>
    <?php if (!$pagamentos): ?>
      <p class="muted">Nenhum pagamento registrado.</p>
    <?php else: ?>
      <?php foreach ($pagamentos as $p): ?>
        <div class="card">
          <div class="spaced">
            <div>
              <div class="title"><?= e(date('d/m/Y', strtotime($p['data_pagamento']))) ?> · <?= e($p['metodo'] ?? '—') ?></div>
              <div class="sub muted"><?= e($p['observacao'] ?? '') ?></div>
            </div>
            <div class="money md"><?= e(money_fmt((float)$p['valor_pago'], $cob['moeda'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <div class="card attention">
      <div class="title">Pagamentos no Sprint 4</div>
      <div class="desc">Upload de comprovante e registro de pagamento serão liberados no Sprint 4. Por enquanto a cobrança é só visualização.</div>
    </div>

    <?php if (is_admin()): ?>
      <div class="btn-pair mt-5">
        <?php if ($cob['status'] !== 'cancelada'): ?>
          <form method="post" style="flex:1; margin:0;" onsubmit="return confirm('Cancelar esta cobrança?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="cancelar">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <button class="btn btn-danger block" type="submit">Cancelar cobrança</button>
          </form>
        <?php else: ?>
          <form method="post" style="flex:1; margin:0;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="reabrir">
            <input type="hidden" name="id" value="<?= (int)$cob['id'] ?>">
            <button class="btn block" type="submit">Reabrir</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Lista
require __DIR__ . '/includes/header.php';
$f_status = $_GET['status'] ?? '';
$f_cliente = (int)($_GET['cliente_id'] ?? 0);

$where = ['1=1']; $params = [];
if ($me['role'] === 'cliente') {
    $where[] = 'c.cliente_id = ?'; $params[] = (int)$me['cliente_id'];
} elseif ($me['role'] === 'funcionario') {
    $where[] = 'c.id IN (SELECT ci.cobranca_id FROM cobranca_itens ci JOIN assinaturas a ON a.id = ci.assinatura_id WHERE a.funcionario_id = ?)';
    $params[] = (int)$me['id'];
}
if (in_array($f_status, ['aberta','paga','cancelada'], true)) {
    $where[] = 'c.status = ?'; $params[] = $f_status;
}
if (is_admin() && $f_cliente) {
    $where[] = 'c.cliente_id = ?'; $params[] = $f_cliente;
}

$sql = 'SELECT c.id, c.competencia_mes, c.valor_total, c.moeda, c.vencimento, c.status, cl.nome_empresa
        FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY c.status = "paga", c.vencimento DESC LIMIT 200';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cobr = $stmt->fetchAll();
?>
<h1 class="page-title">Cobranças</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if (is_admin()): ?>
  <details class="card">
    <summary><strong>🔧 Gerar cobrança manualmente</strong> (teste)</summary>
    <form method="post" class="mt-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="gerar_manual">
      <div class="field"><label>Cliente</label>
        <select name="cliente_id" required>
          <option value="">— selecione —</option>
          <?php
            $cls = $db->query('SELECT id, nome_empresa FROM clientes WHERE ativo=1 ORDER BY nome_empresa')->fetchAll();
            foreach ($cls as $c) echo '<option value="'.(int)$c['id'].'">'.e($c['nome_empresa']).'</option>';
          ?>
        </select>
      </div>
      <div class="field"><label>Competência (YYYY-MM)</label><input name="competencia" value="<?= e(date('Y-m')) ?>" pattern="\d{4}-\d{2}" required></div>
      <button class="btn block" type="submit">Gerar agora</button>
    </form>
  </details>
<?php endif; ?>

<?php if (is_admin()): ?>
  <form method="get" class="card">
    <div class="grid-2">
      <div class="field"><label>Status</label>
        <select name="status" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="aberta"    <?= $f_status==='aberta'?'selected':'' ?>>Aberta</option>
          <option value="paga"      <?= $f_status==='paga'?'selected':'' ?>>Paga</option>
          <option value="cancelada" <?= $f_status==='cancelada'?'selected':'' ?>>Cancelada</option>
        </select>
      </div>
      <div class="field"><label>Cliente</label>
        <select name="cliente_id" onchange="this.form.submit()">
          <option value="0">Todos</option>
          <?php foreach ($cls ?? [] as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $f_cliente==$c['id']?'selected':'' ?>><?= e($c['nome_empresa']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </form>
<?php endif; ?>

<div class="section-label mt-5">Cobranças (<?= count($cobr) ?>)</div>
<?php foreach ($cobr as $c):
    $vencido = $c['status'] === 'aberta' && strtotime($c['vencimento']) < strtotime(date('Y-m-d'));
?>
  <a class="list-card" href="?id=<?= (int)$c['id'] ?>">
    <div class="info">
      <div class="nome">
        #<?= (int)$c['id'] ?> · <?= e($c['nome_empresa']) ?>
        <span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span>
        <?php if ($vencido): ?><span class="status status-vencida">vencida</span><?php endif; ?>
      </div>
      <div class="sub"><?= e($c['competencia_mes']) ?> · venc <?= e(date('d/m/Y', strtotime($c['vencimento']))) ?></div>
    </div>
    <div class="right">
      <div class="money md"><?= e(money_fmt((float)$c['valor_total'], $c['moeda'])) ?></div>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$cobr): ?>
  <p class="muted center mt-5">Nenhuma cobrança ainda.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
