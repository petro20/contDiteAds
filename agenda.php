<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/entregas.php';
$u = require_login();
if ($u['role'] === 'cliente') { header('Location: ' . APP_BASE_URL . '/entregas.php'); exit; }
$db = db();

// Funcionário vê só dele; admin pode ver de qualquer um via ?funcionario_id=
$funcionario_id = (int)$u['id'];
if ($u['role'] === 'admin' && isset($_GET['funcionario_id'])) {
    $funcionario_id = (int)$_GET['funcionario_id'];
}

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    $assin = (int)($_POST['assinatura_id'] ?? 0);
    $comp  = $_POST['competencia'] ?? $competencia;

    // Confirma posse: assinatura tem que ter o funcionário como responsável (ou admin pode tudo)
    if ($assin) {
        $stmt = $db->prepare('SELECT funcionario_id FROM assinaturas WHERE id = ?');
        $stmt->execute([$assin]);
        $fres = (int)$stmt->fetchColumn();
        if (!is_admin() && $fres !== (int)$u['id']) {
            http_response_code(403); exit('Acesso negado.');
        }
    }

    if ($op === 'toggle_dia') {
        entregas_toggle_dia($db, $assin, $comp, $_POST['data'] ?? date('Y-m-d'), (int)$u['id']);
    } elseif ($op === 'add_unidade') {
        entregas_add_unidade($db, $assin, $comp, (int)$u['id']);
    } elseif ($op === 'toggle_unico') {
        entregas_toggle_unico($db, $assin, $comp, (int)$u['id']);
    } elseif ($op === 'remover') {
        entregas_remover($db, (int)($_POST['entrega_id'] ?? 0));
    }
    header('Location: ' . APP_BASE_URL . '/agenda.php?mes=' . urlencode($comp) . ($u['role']==='admin' && $funcionario_id !== (int)$u['id'] ? '&funcionario_id=' . $funcionario_id : '')); exit;
}

$assinaturas = agenda_assinaturas($db, $funcionario_id, $competencia);

// Mês anterior/próximo
$dt = DateTime::createFromFormat('Y-m', $competencia);
$mes_anterior_str = (clone $dt)->modify('-1 month')->format('Y-m');
$mes_proximo_str = (clone $dt)->modify('+1 month')->format('Y-m');
$nome_mes = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')] . ' de ' . $dt->format('Y');

$page = 'Agenda';
$nav_active = 'agenda';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Agenda</h1>
<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_anterior_str) ?><?= $u['role']==='admin' ? '&funcionario_id='.$funcionario_id : '' ?>">← <?= e($mes_anterior_str) ?></a>
  <strong><?= e($nome_mes) ?></strong>
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_proximo_str) ?><?= $u['role']==='admin' ? '&funcionario_id='.$funcionario_id : '' ?>"><?= e($mes_proximo_str) ?> →</a>
</div>

<?php if (!$assinaturas): ?>
  <div class="card"><div class="title">Sem assinaturas atribuídas</div><div class="desc">Não há clientes atribuídos a você neste mês. Quando o admin atribuir, aparecem aqui.</div></div>
<?php endif; ?>

<?php foreach ($assinaturas as $a):
    $modo = entregas_modo_ui(['e_pacote' => $a['e_pacote'], 'tipo' => $a['tipo']]);
    $entregas = entregas_do_mes($db, (int)$a['assinatura_id'], $competencia);
    $count = count($entregas);
?>
<div class="card">
  <div class="spaced mb-3">
    <div>
      <div class="title">
        <?= e($a['nome_empresa']) ?>
        <?php if ($a['e_pacote']): ?><span class="status status-ia">pacote</span><?php endif; ?>
      </div>
      <div class="sub muted"><?= e($a['item_nome']) ?> · <?= e($a['tipo']) ?></div>
    </div>
    <?php if ($modo !== 'info'): ?>
      <div class="muted" style="font-size:13px;"><strong><?= $count ?></strong> marcadas</div>
    <?php endif; ?>
  </div>

  <?php if ($modo === 'calendar'):
      $cal = calendario_do_mes($competencia);
      $marcadas_set = [];
      foreach ($entregas as $en) if ($en['data_marcada']) $marcadas_set[$en['data_marcada']] = (int)$en['id'];
  ?>
  <table style="width:100%; border-collapse:collapse; text-align:center; font-size:13px;">
    <thead><tr>
      <?php foreach (['D','S','T','Q','Q','S','S'] as $w): ?><th style="padding:6px; color:var(--txt-3);"><?= $w ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($cal as $row): ?>
      <tr>
        <?php foreach ($row as $iso): ?>
          <td style="padding:3px;">
            <?php if (!$iso): ?>&nbsp;<?php else:
              $marcado = isset($marcadas_set[$iso]);
              $dia = (int)substr($iso, 8, 2);
            ?>
            <form method="post" style="margin:0;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="toggle_dia">
              <input type="hidden" name="assinatura_id" value="<?= (int)$a['assinatura_id'] ?>">
              <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
              <input type="hidden" name="data" value="<?= e($iso) ?>">
              <button type="submit" style="
                width:36px; height:36px; border-radius:6px; border:1px solid var(--border);
                background: <?= $marcado ? 'var(--c-success)' : 'var(--bg-input)' ?>;
                color: <?= $marcado ? '#fff' : 'var(--txt-2)' ?>;
                font-weight:<?= $marcado ? '700' : '400' ?>;
                cursor: pointer;
              " title="<?= $marcado ? 'Desmarcar' : 'Marcar entrega' ?>"><?= $dia ?></button>
            </form>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif ($modo === 'tally'): ?>
  <div class="spaced">
    <div class="muted">Marque cada entrega realizada:</div>
    <form method="post" style="margin:0;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="add_unidade">
      <input type="hidden" name="assinatura_id" value="<?= (int)$a['assinatura_id'] ?>">
      <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
      <button class="btn small" type="submit">+ Entreguei mais um</button>
    </form>
  </div>
  <?php if ($entregas): ?>
    <div class="mt-3">
      <?php foreach ($entregas as $idx => $en): ?>
        <div class="spaced" style="padding:6px 0; border-bottom:1px solid var(--border);">
          <span>✅ Unidade #<?= (int)$en['indice'] ?> · <?= e(date('d/m H:i', strtotime($en['criado_em']))) ?></span>
          <form method="post" style="margin:0;" onsubmit="return confirm('Remover esta marcação?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="op" value="remover">
            <input type="hidden" name="entrega_id" value="<?= (int)$en['id'] ?>">
            <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
            <button class="btn btn-ghost small" type="submit">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php elseif ($modo === 'single'):
      $marcado = $count > 0;
  ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="toggle_unico">
      <input type="hidden" name="assinatura_id" value="<?= (int)$a['assinatura_id'] ?>">
      <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
      <button class="btn block <?= $marcado ? 'btn-success' : '' ?>" type="submit">
        <?= $marcado ? '✅ Entregue (clique pra desmarcar)' : '⬜ Marcar como entregue' ?>
      </button>
    </form>

  <?php else: /* info */ ?>
    <div class="muted">Trabalho contínuo, sem unidades discretas. Está ativo neste mês.</div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
