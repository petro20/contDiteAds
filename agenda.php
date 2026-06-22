<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/entregas.php';
$u = require_login();
if ($u['role'] === 'cliente') { header('Location: ' . APP_BASE_URL . '/entregas.php'); exit; }
$db = db();

// Verifica se o usuário trabalha em dupla — pega ID do parceiro (assinaturas estão sob esse ID)
$trabalha_com_id = null;
$trabalha_com_nome = null;
try {
    $stmt = $db->prepare("SELECT u.trabalha_com_id, p.nome FROM usuarios u LEFT JOIN usuarios p ON p.id = u.trabalha_com_id WHERE u.id = ?");
    $stmt->execute([(int)$u['id']]);
    $r = $stmt->fetch();
    if ($r && $r['trabalha_com_id']) {
        $trabalha_com_id = (int)$r['trabalha_com_id'];
        $trabalha_com_nome = $r['nome'];
    }
} catch (Throwable $e) {}

// Funcionário vê só dele; admin pode ver de qualquer um via ?funcionario_id=
// Se trabalha em dupla, default = vê agenda do parceiro (pagamento vai pra ele).
// Mas pode alternar pra ver as próprias assinaturas via ?ver=eu (caso ele
// também tenha clientes atribuídos diretamente a ele).
$ver = $_GET['ver'] ?? null;
if ($trabalha_com_id) {
    $funcionario_id = ($ver === 'eu') ? (int)$u['id'] : $trabalha_com_id;
} else {
    $funcionario_id = (int)$u['id'];
}
if (is_admin() && isset($_GET['funcionario_id'])) {
    $funcionario_id = (int)$_GET['funcionario_id'];
}

$competencia = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $competencia)) $competencia = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';
    $assin = (int)($_POST['assinatura_id'] ?? 0);
    $comp  = $_POST['competencia'] ?? $competencia;

    // Confirma posse: assinatura tem que ter o funcionário como responsável
    // (ou admin pode tudo, ou usuário trabalha em dupla com o responsável)
    if ($assin) {
        $stmt = $db->prepare('SELECT funcionario_id FROM assinaturas WHERE id = ?');
        $stmt->execute([$assin]);
        $fres = (int)$stmt->fetchColumn();
        $autorizado = is_admin() || $fres === (int)$u['id'] || ($trabalha_com_id && $fres === $trabalha_com_id);
        if (!$autorizado) {
            http_response_code(403); exit('Acesso negado.');
        }
    }

    $result = null;
    if ($op === 'toggle_dia') {
        $result = entregas_toggle_dia($db, $assin, $comp, $_POST['data'] ?? date('Y-m-d'), (int)$u['id']);
    } elseif ($op === 'add_unidade') {
        $result = ['id' => entregas_add_unidade($db, $assin, $comp, (int)$u['id'])];
    } elseif ($op === 'toggle_unico') {
        $result = entregas_toggle_unico($db, $assin, $comp, (int)$u['id']);
    } elseif ($op === 'remover') {
        entregas_remover($db, (int)($_POST['entrega_id'] ?? 0));
        $result = ['action' => 'removed'];
    }

    // Requisição AJAX (fetch) → responde JSON e não recarrega a página
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'     => true,
            'op'     => $op,
            'result' => $result,
            'count'  => $assin ? entregas_count($db, $assin, $comp) : 0,
        ]);
        exit;
    }

    $redir_qs = '';
    if (is_admin() && $funcionario_id !== (int)$u['id']) {
        $redir_qs .= '&funcionario_id=' . $funcionario_id;
    }
    if ($trabalha_com_id && !is_admin()) {
        $redir_qs .= '&ver=' . (($funcionario_id === (int)$u['id']) ? 'eu' : 'parceiro');
    }
    $anchor = $assin ? '#assin-' . $assin : '';
    header('Location: ' . APP_BASE_URL . '/agenda.php?mes=' . urlencode($comp) . $redir_qs . $anchor); exit;
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
<?php if ($trabalha_com_id && !is_admin()):
  $vendo_parceiro = ($funcionario_id === $trabalha_com_id);
?>
  <div class="card brand">
    <div class="title">👥 Você trabalha em dupla com <?= e($trabalha_com_nome) ?></div>
    <div class="desc">
      <?php if ($vendo_parceiro): ?>
        Esta é a agenda de <strong><?= e($trabalha_com_nome) ?></strong> — você pode ver e marcar entregas, mas o <strong>pagamento vai todo pra ele</strong>.
      <?php else: ?>
        Esta é a <strong>sua agenda pessoal</strong> (clientes atribuídos diretamente a você).
      <?php endif; ?>
    </div>
    <div class="spaced mt-2" style="gap:8px;">
      <a class="btn small <?= !$vendo_parceiro?'':'btn-ghost' ?>" href="?mes=<?= e($competencia) ?>&ver=eu">Minha agenda</a>
      <a class="btn small <?= $vendo_parceiro?'':'btn-ghost' ?>" href="?mes=<?= e($competencia) ?>&ver=parceiro">Agenda do <?= e($trabalha_com_nome) ?></a>
    </div>
  </div>
<?php endif; ?>
<?php
  // Preserva ?ver= e ?funcionario_id= ao navegar entre meses
  $extra_qs = '';
  if (is_admin() && isset($_GET['funcionario_id'])) {
      $extra_qs .= '&funcionario_id=' . $funcionario_id;
  }
  if ($trabalha_com_id && !is_admin()) {
      $extra_qs .= '&ver=' . (($funcionario_id === (int)$u['id']) ? 'eu' : 'parceiro');
  }
?>
<div class="spaced mb-3">
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_anterior_str) ?><?= $extra_qs ?>">← <?= e($mes_anterior_str) ?></a>
  <strong><?= e($nome_mes) ?></strong>
  <a class="btn btn-ghost small" href="?mes=<?= e($mes_proximo_str) ?><?= $extra_qs ?>"><?= e($mes_proximo_str) ?> →</a>
</div>

<?php if (!$assinaturas): ?>
  <div class="card"><div class="title">Sem assinaturas atribuídas</div><div class="desc">Não há clientes atribuídos a você neste mês. Quando o admin atribuir, aparecem aqui.</div></div>
<?php endif; ?>

<?php foreach ($assinaturas as $a):
    $modo = entregas_modo_ui(['e_pacote' => $a['e_pacote'], 'tipo' => $a['tipo']]);
    $entregas = entregas_do_mes($db, (int)$a['assinatura_id'], $competencia);
    $count = count($entregas);
?>
<div class="card" id="assin-<?= (int)$a['assinatura_id'] ?>" style="scroll-margin-top:16px;">
  <div class="spaced mb-3">
    <div>
      <div class="title">
        <?= e($a['nome_empresa']) ?>
        <?php if ($a['e_pacote']): ?><span class="status status-ia">pacote</span><?php endif; ?>
      </div>
      <div class="sub muted"><?= e($a['item_nome']) ?> · <?= e($a['tipo']) ?></div>
    </div>
    <?php if ($modo !== 'info'): ?>
      <div class="muted" style="font-size:13px;"><strong class="marc-count" data-assin="<?= (int)$a['assinatura_id'] ?>"><?= $count ?></strong> marcadas</div>
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
            <input type="hidden" name="assinatura_id" value="<?= (int)$a['assinatura_id'] ?>">
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

<script>
(function () {
  // Toggles de calendário e item único viram AJAX: sem reload, sem subir a tela.
  function applyDay(btn, marked) {
    btn.style.background = marked ? 'var(--c-success)' : 'var(--bg-input)';
    btn.style.color = marked ? '#fff' : 'var(--txt-2)';
    btn.style.fontWeight = marked ? '700' : '400';
    btn.title = marked ? 'Desmarcar' : 'Marcar entrega';
  }
  function applySingle(btn, marked) {
    btn.classList.toggle('btn-success', marked);
    btn.textContent = marked
      ? '✅ Entregue (clique pra desmarcar)'
      : '⬜ Marcar como entregue';
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var opEl = form.querySelector('input[name="op"]');
    if (!opEl) return;
    var op = opEl.value;
    if (op !== 'toggle_dia' && op !== 'toggle_unico') return; // só estes via AJAX

    ev.preventDefault();
    var btn = form.querySelector('button[type="submit"]');
    if (btn && btn.dataset.busy) return;
    if (btn) btn.dataset.busy = '1';

    var data = new FormData(form);
    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: data,
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) throw new Error('falha');
        var assin = data.get('assinatura_id');
        var cnt = document.querySelector('.marc-count[data-assin="' + assin + '"]');
        if (cnt) cnt.textContent = res.count;
        var marked = res.result && res.result.action === 'added';
        if (op === 'toggle_dia') applyDay(btn, marked);
        else applySingle(btn, marked);
        if (btn) delete btn.dataset.busy;
      })
      .catch(function () {
        // Fallback: se o AJAX falhar, envia normal (recarrega).
        if (btn) delete btn.dataset.busy;
        HTMLFormElement.prototype.submit.call(form);
      });
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
