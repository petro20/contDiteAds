<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/entregas.php';
$u = require_login();
$db = db();

// Cliente vê suas; admin pode ver de qualquer cliente via ?cliente_id=
if ($u['role'] === 'cliente') {
    $cliente_id = (int)($u['cliente_id'] ?? 0);
} elseif (is_admin()) {
    $cliente_id = (int)($_GET['cliente_id'] ?? 0);
    if (!$cliente_id) {
        header('Location: ' . APP_BASE_URL . '/painel.php'); exit;
    }
} else {
    header('Location: ' . APP_BASE_URL . '/agenda.php'); exit;
}

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

$cliente_nome = null;
if ($cliente_id) {
    $stmt = $db->prepare('SELECT nome_empresa FROM clientes WHERE id = ?');
    $stmt->execute([$cliente_id]);
    $cliente_nome = $stmt->fetchColumn();
}
if (!$cliente_nome) {
    $page = t('Entregas');
    $nav_active = 'entregas';
    require __DIR__ . '/includes/header.php';
    echo '<h1 class="page-title">' . e(t('Conta sem cliente vinculado')) . '</h1>';
    echo '<div class="card attention"><div class="title">⚠ ' . e(t('Vínculo ausente')) . '</div><div class="desc">' . e(t('Sua conta de cliente não está ligada a um registro de empresa. Avise o admin pra corrigir o cadastro.')) . '</div></div>';
    echo '<a class="btn btn-ghost block mt-3" href="' . htmlspecialchars(APP_BASE_URL) . '/perfil.php">' . e(t('Voltar')) . '</a>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$assinaturas = agenda_assinaturas_cliente($db, $cliente_id, $competencia);

$dt = DateTime::createFromFormat('Y-m', $competencia);
$mes_ant = (clone $dt)->modify('-1 month')->format('Y-m');
$mes_prox = (clone $dt)->modify('+1 month')->format('Y-m');
$nome_mes = t(['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')]) . ' ' . t('de') . ' ' . $dt->format('Y');

$page = t('Entregas');
$nav_active = 'entregas';
$page_sub = is_admin() ? $cliente_nome : null;
$show_back = is_admin();
$back_to = APP_BASE_URL . '/painel.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Entregas do mês')) ?></h1>
<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_ant) ?><?= is_admin() ? '&cliente_id='.$cliente_id : '' ?>">← <?= e($mes_ant) ?></a>
  <strong><?= e($nome_mes) ?></strong>
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_prox) ?><?= is_admin() ? '&cliente_id='.$cliente_id : '' ?>"><?= e($mes_prox) ?> →</a>
</div>

<?php if (!$assinaturas): ?>
  <div class="card"><div class="title"><?= e(t('Nenhum serviço ativo')) ?></div><div class="desc"><?= e(t('Não há assinaturas ativas neste mês.')) ?></div></div>
<?php endif; ?>

<?php foreach ($assinaturas as $a):
    $modo = entregas_modo_ui(['e_pacote' => $a['e_pacote'], 'tipo' => $a['tipo']]);
    $entregas = entregas_do_mes($db, (int)$a['assinatura_id'], $competencia);
    $count = count($entregas);
?>
<div class="card">
  <div class="spaced mb-3">
    <div>
      <div class="title"><?= e($a['item_nome']) ?></div>
      <div class="sub muted">
        <?php if ($a['funcionario_nome']): ?><?= e(t('com')) ?> <?= e($a['funcionario_nome']) ?> · <?php endif; ?>
        <?= e($a['tipo']) ?>
      </div>
    </div>
    <?php if ($modo !== 'info'): ?>
      <div class="muted" style="font-size:13px;"><strong><?= $count ?></strong> <?= e(t('realizadas')) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($modo === 'calendar'):
      $cal = calendario_do_mes($competencia);
      $marcadas = [];
      foreach ($entregas as $en) if ($en['data_marcada']) $marcadas[$en['data_marcada']] = true;
  ?>
    <table style="width:100%; border-collapse:collapse; text-align:center; font-size:13px;">
      <thead><tr>
        <?php foreach ([t('D'),t('S'),t('T'),t('Q'),t('Q2'),t('S2'),t('Sa')] as $w): ?><th style="padding:6px; color:var(--txt-3);"><?= e($w) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($cal as $row): ?>
        <tr>
          <?php foreach ($row as $iso): ?>
            <td style="padding:3px;">
              <?php if (!$iso): ?>&nbsp;<?php else:
                $m = isset($marcadas[$iso]);
                $dia = (int)substr($iso, 8, 2);
              ?>
              <div style="
                width:36px; height:36px; border-radius:6px; border:1px solid var(--border);
                display:inline-flex; align-items:center; justify-content:center;
                background: <?= $m ? 'var(--c-success)' : 'var(--bg-input)' ?>;
                color: <?= $m ? '#fff' : 'var(--txt-3)' ?>;
                font-weight: <?= $m ? '700' : '400' ?>;
              "><?= $dia ?></div>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($modo === 'tally'):
      if ($entregas): ?>
        <?php foreach ($entregas as $en): ?>
          <div style="padding:6px 0; border-bottom:1px solid var(--border);">
            ✅ <?= e(t('Unidade')) ?> #<?= (int)$en['indice'] ?> · <?= e(date('d/m', strtotime($en['criado_em']))) ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="muted"><?= e(t('Nada marcado ainda.')) ?></div>
      <?php endif; ?>

  <?php elseif ($modo === 'single'):
      $m = $count > 0;
  ?>
    <div class="status status-<?= $m ? 'paga' : 'aberta' ?>" style="font-size:14px; padding:8px 12px;">
      <?= $m ? '✅ ' . e(t('Entregue')) : '⏳ ' . e(t('Em andamento')) ?>
    </div>

  <?php else: ?>
    <div class="muted"><?= e(t('Serviço contínuo — ativo no mês.')) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
