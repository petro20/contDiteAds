<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/email.php';
require_once __DIR__ . '/lib/pagamentos.php';
$me = require_admin();
$db = db();

$acao = $_GET['acao'] ?? 'fila';
$fid  = (int)($_GET['funcionario_id'] ?? 0);
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'apagar_pagamento') {
        if (!is_sadmin()) { http_response_code(403); exit('Apenas Super Admin pode apagar pagamentos.'); }
        $pid = (int)($_POST['id'] ?? 0);
        if ($pid > 0) {
            try {
                $db->beginTransaction();
                // Apaga os itens primeiro (sem FK ON DELETE CASCADE garantido)
                $db->prepare('DELETE FROM pagamento_funcionario_itens WHERE pagamento_id = ?')->execute([$pid]);
                $db->prepare('DELETE FROM pagamentos_funcionario WHERE id = ?')->execute([$pid]);
                $db->commit();
                audit_log('pagamento_funcionario.apagado', 'pagamentos_funcionario', $pid);
                header('Location: ' . APP_BASE_URL . '/pagamentos_funcionarios.php?ok=del'); exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $flash = ['err', 'Erro ao apagar: ' . $e->getMessage()];
            }
        }
    }
    if (($_POST['op'] ?? '') === 'marcar_pago') {
        $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
        $items = $_POST['items'] ?? [];
        if (!is_array($items)) $items = [];
        $items = array_map('intval', $items);
        $items = array_filter($items, fn($i) => $i > 0);
        $data  = trim((string)($_POST['data_pagamento'] ?? '')) ?: date('Y-m-d');
        try {
            $pid = criar_pagamento_funcionario($db, $funcionario_id, $items, $data, (int)$me['id']);
            audit_log('pagamento_funcionario.criado', 'pagamentos_funcionario', $pid);

            // Envia email com link ao comprovante (se SMTP_PASS estiver setado)
            $stmt = $db->prepare('SELECT nome, email FROM usuarios WHERE id = ?');
            $stmt->execute([$funcionario_id]);
            $func = $stmt->fetch();
            if ($func && $func['email']) {
                $link = APP_BASE_URL . '/comprovante_funcionario.php?id=' . $pid;
                $html = '<p>Olá, ' . htmlspecialchars($func['nome']) . '.</p>'
                      . '<p>Seu pagamento foi processado. Acesse o comprovante:</p>'
                      . '<p><a href="' . $link . '">' . $link . '</a></p>'
                      . '<p>— Dite Ads</p>';
                $r = email_enviar($func['email'], 'Comprovante de pagamento — Dite Ads', $html);
                if ($r !== true) error_log('Email comprovante: ' . (string)$r);
            }
            header('Location: ' . APP_BASE_URL . '/pagamentos_funcionarios.php?acao=detalhe&id=' . $pid . '&ok=1'); exit;
        } catch (Throwable $e) {
            $flash = ['err', $e->getMessage()];
        }
    }
}

$page = 'Pagamentos';
$nav_active = '';

if ($acao === 'detalhe') {
    $pid = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT p.*, u.nome AS func_nome, u.wisetag, u.email FROM pagamentos_funcionario p JOIN usuarios u ON u.id = p.funcionario_id WHERE p.id = ?');
    $stmt->execute([$pid]);
    $pag = $stmt->fetch();
    if (!$pag) { http_response_code(404); exit('Pagamento não encontrado.'); }
    $detalhes = detalhes_pagamento_funcionario($db, $pid);

    $show_back = true; $back_to = APP_BASE_URL . '/pagamentos_funcionarios.php';
    $page = 'Pagamento #' . $pid;
    $page_sub = $pag['func_nome'];
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if (isset($_GET['ok'])): ?><div class="flash ok">Pagamento registrado. Email enviado ao funcionário.</div><?php endif; ?>
    <div class="card hero success">
      <div class="label">Valor pago</div>
      <div class="value">$<?= e(number_format((float)$pag['valor_usd'], 2, '.', ',')) ?></div>
      <div class="sub"><?= e($pag['func_nome']) ?> · <?= e(date('d/m/Y', strtotime($pag['data_pagamento']))) ?></div>
    </div>
    <div class="card">
      <?php if ($pag['wisetag']): ?><div class="info-pair"><span class="l">WiseTag</span><span class="v"><?= e($pag['wisetag']) ?></span></div><?php endif; ?>
      <div class="info-pair"><span class="l">Email</span><span class="v"><?= e($pag['email']) ?></span></div>
    </div>

    <h2>Detalhamento</h2>
    <?php foreach ($detalhes as $d): ?>
      <div class="card">
        <div class="spaced">
          <div>
            <div class="title"><?= e($d['descricao']) ?></div>
            <div class="sub muted">
              <?= (int)$d['quantidade'] ?>× $<?= e(number_format((float)$d['valor_unitario_usd'], 2, '.', ',')) ?>
              <?php if ($d['nome_empresa']): ?> · <?= e($d['nome_empresa']) ?><?php endif; ?>
              <?php if ($d['competencia_mes']): ?> · <?= e($d['competencia_mes']) ?><?php endif; ?>
            </div>
          </div>
          <div class="money md">$<?= e(number_format((float)$d['subtotal_usd'], 2, '.', ',')) ?></div>
        </div>
      </div>
    <?php endforeach; ?>

    <a class="btn btn-secondary block mt-5" href="<?= e(APP_BASE_URL) ?>/comprovante_funcionario.php?id=<?= (int)$pag['id'] ?>" target="_blank">📄 Ver comprovante (para imprimir/PDF)</a>

    <?php if (is_sadmin()): ?>
      <h2 class="mt-5">⚠ Zona de perigo</h2>
      <form method="post" action="<?= e(APP_BASE_URL) ?>/pagamentos_funcionarios.php" onsubmit="return confirm('APAGAR este pagamento DEFINITIVAMENTE?\n\nOs itens vão voltar pra fila como pendentes.\n\nConfirmar?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="apagar_pagamento">
        <input type="hidden" name="id" value="<?= (int)$pag['id'] ?>">
        <button class="btn btn-danger block" type="submit">🗑 Apagar este pagamento</button>
      </form>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($acao === 'pagar' && $fid) {
    $stmt = $db->prepare('SELECT id, nome, wisetag FROM usuarios WHERE id = ? AND role IN ("admin","funcionario")');
    $stmt->execute([$fid]);
    $func = $stmt->fetch();
    if (!$func) { http_response_code(404); exit('Funcionário não encontrado.'); }
    $itens = itens_pendentes_funcionario($db, $fid);
    $total = (float)array_sum(array_column($itens, 'subtotal_usd'));

    $show_back = true; $back_to = APP_BASE_URL . '/pagamentos_funcionarios.php';
    $page = 'Pagar ' . $func['nome'];
    require __DIR__ . '/includes/header.php';
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <p class="muted">Marque os itens que serão incluídos neste pagamento. Total atualiza conforme você seleciona.</p>

    <form method="post" id="form_pagar">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="marcar_pago">
      <input type="hidden" name="funcionario_id" value="<?= (int)$fid ?>">

      <?php if (!$itens): ?>
        <p class="muted">Nenhum item pendente.</p>
      <?php else: foreach ($itens as $it): ?>
        <label class="card check" style="display:flex; gap:12px; align-items:center; cursor:pointer;">
          <input type="checkbox" name="items[]" value="<?= (int)$it['id'] ?>" checked data-valor="<?= e(number_format((float)$it['subtotal_usd'], 2, '.', '')) ?>" onchange="recalcular()">
          <div class="info" style="flex:1;">
            <div class="title"><?= e($it['item_nome']) ?><?= $it['sem_valor'] ? ' <span class="status status-vencida">sem valor USD!</span>' : '' ?></div>
            <div class="sub muted"><?= e($it['nome_empresa']) ?> · <?= e($it['competencia_mes']) ?> · <?= (int)$it['quantidade'] ?>× $<?= e(number_format((float)$it['valor_unitario_usd'], 2, '.', ',')) ?></div>
          </div>
          <div class="money md">$<?= e(number_format((float)$it['subtotal_usd'], 2, '.', ',')) ?></div>
        </label>
      <?php endforeach; endif; ?>

      <?php if ($itens): ?>
        <div class="card success spaced mt-3">
          <span>Total selecionado:</span>
          <div class="money lg">$<span id="total_sel"><?= e(number_format($total, 2, '.', ',')) ?></span></div>
        </div>
        <div class="field"><label>Data do pagamento</label><input type="date" name="data_pagamento" required value="<?= e(date('Y-m-d')) ?>"></div>
        <button class="btn btn-success block" type="submit" onclick="return confirm('Confirmar pagamento? Os itens marcados serão registrados como pagos.');">Marquei como pago</button>
      <?php endif; ?>
    </form>

    <script>
    function recalcular() {
      let total = 0;
      document.querySelectorAll('input[name="items[]"]:checked').forEach(cb => total += parseFloat(cb.dataset.valor || 0));
      document.getElementById('total_sel').textContent = total.toFixed(2);
    }
    </script>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Fila
$fila = fila_pagamentos_funcionarios($db);

require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Pagamentos a funcionários</h1>
<?php if (isset($_GET['ok']) && $_GET['ok'] === 'del'): ?><div class="flash ok">Pagamento apagado.</div><?php endif; ?>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<?php if (!$fila): ?>
  <div class="card"><div class="title">Fila vazia ✅</div><div class="desc">Nenhum funcionário com valores liberados pendentes.</div></div>
<?php else: ?>
  <?php $tot_geral = (float)array_sum(array_column($fila, 'total_usd')); ?>
  <div class="kpi"><div class="v">$<?= e(number_format($tot_geral, 2, '.', ',')) ?></div><div class="l">Total a pagar (USD)</div></div>
  <div class="section-label">Fila (<?= count($fila) ?> funcionários)</div>
  <?php foreach ($fila as $f): ?>
    <a class="list-card" href="?acao=pagar&funcionario_id=<?= (int)$f['funcionario_id'] ?>">
      <div class="info">
        <div class="nome">
          <?= e($f['nome']) ?>
          <?php if ((int)$f['sem_valor_def'] > 0): ?><span class="status status-vencida"><?= (int)$f['sem_valor_def'] ?> sem valor</span><?php endif; ?>
        </div>
        <div class="sub"><?= (int)$f['itens_count'] ?> itens · <?= e($f['wisetag'] ?? 'sem WiseTag') ?></div>
      </div>
      <div class="right">
        <div class="money md">$<?= e(number_format((float)$f['total_usd'], 2, '.', ',')) ?></div>
      </div>
    </a>
  <?php endforeach; ?>
<?php endif; ?>

<h2 class="mt-5">Histórico recente</h2>
<?php
$stmt = $db->query('SELECT p.id, p.valor_usd, p.data_pagamento, u.nome FROM pagamentos_funcionario p JOIN usuarios u ON u.id = p.funcionario_id ORDER BY p.data_pagamento DESC, p.id DESC LIMIT 30');
$hist = $stmt->fetchAll();
?>
<?php if (!$hist): ?>
  <p class="muted">Nenhum pagamento realizado ainda.</p>
<?php else: foreach ($hist as $h): ?>
  <a class="list-card" href="?acao=detalhe&id=<?= (int)$h['id'] ?>">
    <div class="info">
      <div class="nome"><?= e($h['nome']) ?></div>
      <div class="sub"><?= e(date('d/m/Y', strtotime($h['data_pagamento']))) ?></div>
    </div>
    <div class="right"><div class="money md">$<?= e(number_format((float)$h['valor_usd'], 2, '.', ',')) ?></div></div>
  </a>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
