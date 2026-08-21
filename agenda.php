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
            http_response_code(403); exit(t('Acesso negado.'));
        }
    }

    $result = null;
    if ($op === 'toggle_dia') {
        $result = entregas_toggle_dia($db, $assin, $comp, $_POST['data'] ?? date('Y-m-d'), (int)$u['id'], $_POST['redes'] ?? '');
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
$nome_mes = t(['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'][(int)$dt->format('n')]) . ' ' . t('de') . ' ' . $dt->format('Y');

$page = t('Agenda');
$nav_active = 'agenda';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= e(t('Agenda')) ?></h1>
<?php if ($trabalha_com_id && !is_admin()):
  $vendo_parceiro = ($funcionario_id === $trabalha_com_id);
?>
  <div class="card brand">
    <div class="title">👥 <?= e(t('Você trabalha em dupla com')) ?> <?= e($trabalha_com_nome) ?></div>
    <div class="desc">
      <?php if ($vendo_parceiro): ?>
        <?= e(t('Esta é a agenda de')) ?> <strong><?= e($trabalha_com_nome) ?></strong> — <?= e(t('você pode ver e marcar entregas, mas o')) ?> <strong><?= e(t('pagamento vai todo pra ele')) ?></strong>.
      <?php else: ?>
        <?= e(t('Esta é a')) ?> <strong><?= e(t('sua agenda pessoal')) ?></strong> (<?= e(t('clientes atribuídos diretamente a você')) ?>).
      <?php endif; ?>
    </div>
    <div class="spaced mt-2" style="gap:8px;">
      <a class="btn small <?= !$vendo_parceiro?'':'btn-ghost' ?>" href="?mes=<?= e($competencia) ?>&ver=eu"><?= e(t('Minha agenda')) ?></a>
      <a class="btn small <?= $vendo_parceiro?'':'btn-ghost' ?>" href="?mes=<?= e($competencia) ?>&ver=parceiro"><?= e(t('Agenda do')) ?> <?= e($trabalha_com_nome) ?></a>
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
  <div class="card"><div class="title"><?= e(t('Sem assinaturas atribuídas')) ?></div><div class="desc"><?= e(t('Não há clientes atribuídos a você neste mês. Quando o admin atribuir, aparecem aqui.')) ?></div></div>
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
        <?php if ($a['e_pacote']): ?><span class="status status-ia"><?= e(t('pacote')) ?></span><?php endif; ?>
      </div>
      <div class="sub muted"><?= e($a['item_nome']) ?> · <?= e($a['tipo']) ?></div>
    </div>
    <?php if ($modo !== 'info'): ?>
      <div class="muted" style="font-size:13px;"><strong class="marc-count" data-assin="<?= (int)$a['assinatura_id'] ?>"><?= $count ?></strong> <?= e(t('marcadas')) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($modo === 'calendar'):
      $cal = calendario_do_mes($competencia);
      $marcadas_set = [];
      foreach ($entregas as $en) if ($en['data_marcada']) $marcadas_set[$en['data_marcada']] = ['id' => (int)$en['id'], 'redes' => $en['redes'] ?? ''];
  ?>
  <div class="paleta-redes" data-assin="<?= (int)$a['assinatura_id'] ?>" title="<?= e(t('Selecione as redes e clique no dia')) ?>">
    <span class="paleta-label"><?= e(t('Redes:')) ?></span>
    <?php foreach (entregas_redes_defs() as $slug => $def): ?>
      <button type="button" class="rede-btn" data-rede="<?= e($slug) ?>" aria-pressed="false"
              aria-label="<?= e($def['nome']) ?>" title="<?= e($def['nome']) ?>"><?= $def['svg'] ?></button>
    <?php endforeach; ?>
  </div>
  <table style="width:100%; border-collapse:collapse; text-align:center; font-size:13px;">
    <thead><tr>
      <?php foreach (['D','S','T','Q','Q','S','S'] as $w): ?><th style="padding:6px; color:var(--txt-3);"><?= e($w) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($cal as $row): ?>
      <tr>
        <?php foreach ($row as $iso): ?>
          <td style="padding:3px;">
            <?php if (!$iso): ?>&nbsp;<?php else:
              $marcado = isset($marcadas_set[$iso]);
              $dia = (int)substr($iso, 8, 2);
              $redes_dia = $marcado ? ($marcadas_set[$iso]['redes'] ?? '') : '';
            ?>
            <form method="post" style="margin:0;">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="op" value="toggle_dia">
              <input type="hidden" name="assinatura_id" value="<?= (int)$a['assinatura_id'] ?>">
              <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
              <input type="hidden" name="data" value="<?= e($iso) ?>">
              <input type="hidden" name="redes" value="">
              <button type="submit" class="dia-btn<?= $marcado ? ' marcado' : '' ?>"
                title="<?= e($marcado ? t('Desmarcar') : t('Marcar entrega')) ?>">
                <span class="dia-num"><?= $dia ?></span><?= entregas_redes_html($redes_dia) ?>
              </button>
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
    <div class="muted"><?= e(t('Marque cada entrega realizada:')) ?></div>
    <form method="post" style="margin:0;">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="add_unidade">
      <input type="hidden" name="assinatura_id" value="<?= (int)$a['assinatura_id'] ?>">
      <input type="hidden" name="competencia" value="<?= e($competencia) ?>">
      <button class="btn small" type="submit"><?= e(t('+ Entreguei mais um')) ?></button>
    </form>
  </div>
  <?php if ($entregas): ?>
    <div class="mt-3">
      <?php foreach ($entregas as $idx => $en): ?>
        <div class="spaced" style="padding:6px 0; border-bottom:1px solid var(--border);">
          <span>✅ <?= e(t('Unidade')) ?> #<?= (int)$en['indice'] ?> · <?= e(date('d/m H:i', strtotime($en['criado_em']))) ?></span>
          <form method="post" style="margin:0;" onsubmit="return confirm('<?= e(t('Remover esta marcação?')) ?>');">
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
        <?= e($marcado ? t('✅ Entregue (clique pra desmarcar)') : t('⬜ Marcar como entregue')) ?>
      </button>
    </form>

  <?php else: /* info */ ?>
    <div class="muted"><?= e(t('Trabalho contínuo, sem unidades discretas. Está ativo neste mês.')) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
(function () {
  var REDES = <?php
    $redes_js = [];
    foreach (entregas_redes_defs() as $slug => $def) { $redes_js[$slug] = ['nome' => $def['nome'], 'svg' => $def['svg']]; }
    echo json_encode($redes_js, JSON_UNESCAPED_UNICODE);
  ?>;

  // --- Paleta de redes (por card): seleção fica no navegador (localStorage). ---
  function paletaAtiva(assin) {
    var bar = document.querySelector('.paleta-redes[data-assin="' + assin + '"]');
    if (!bar) return [];
    return Array.prototype.slice.call(bar.querySelectorAll('.rede-btn.ativa'))
      .map(function (b) { return b.getAttribute('data-rede'); });
  }
  document.querySelectorAll('.paleta-redes').forEach(function (bar) {
    var assin = bar.getAttribute('data-assin');
    var key = 'cont_paleta_' + assin;
    var saved = [];
    try { saved = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) {}
    bar.querySelectorAll('.rede-btn').forEach(function (b) {
      if (saved.indexOf(b.getAttribute('data-rede')) !== -1) {
        b.classList.add('ativa'); b.setAttribute('aria-pressed', 'true');
      }
      b.addEventListener('click', function () {
        var on = b.classList.toggle('ativa');
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        try { localStorage.setItem(key, JSON.stringify(paletaAtiva(assin))); } catch (e) {}
      });
    });
  });

  // --- Render dos ícones de um dia a partir do CSV de slugs. ---
  function renderDiaRedes(csv) {
    if (!csv) return '';
    var out = '';
    csv.split(',').forEach(function (slug) {
      var d = REDES[slug];
      if (d) out += '<span class="rede-ico" title="' + d.nome + '">' + d.svg + '</span>';
    });
    return out ? '<span class="dia-redes">' + out + '</span>' : '';
  }

  // Toggles de calendário e item único viram AJAX: sem reload, sem subir a tela.
  function applyDay(btn, marked, csv) {
    btn.classList.toggle('marcado', marked);
    btn.title = marked ? <?= json_encode(t('Desmarcar')) ?> : <?= json_encode(t('Marcar entrega')) ?>;
    var old = btn.querySelector('.dia-redes');
    if (old) old.parentNode.removeChild(old);
    if (marked) btn.insertAdjacentHTML('beforeend', renderDiaRedes(csv));
  }
  function applySingle(btn, marked) {
    btn.classList.toggle('btn-success', marked);
    btn.textContent = marked
      ? <?= json_encode(t('✅ Entregue (clique pra desmarcar)')) ?>
      : <?= json_encode(t('⬜ Marcar como entregue')) ?>;
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

    // Ao marcar um dia, carimba as redes ativas da paleta; ao desmarcar, vai vazio.
    if (op === 'toggle_dia') {
      var assinF = form.querySelector('input[name="assinatura_id"]').value;
      var redesInput = form.querySelector('input[name="redes"]');
      var vaiMarcar = !btn.classList.contains('marcado');
      if (redesInput) redesInput.value = vaiMarcar ? paletaAtiva(assinF).join(',') : '';
    }

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
        if (op === 'toggle_dia') applyDay(btn, marked, res.result && res.result.redes);
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
