<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/regua.php';
require_once __DIR__ . '/lib/whatsapp.php';
$me = require_sadmin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    if ($op === 'salvar_etapa') {
        $id   = (int)($_POST['id'] ?? 0);
        $ord  = (int)($_POST['ordem'] ?? 1);
        $dias = (int)($_POST['dias_apos_vencimento'] ?? 0);
        $te   = (int)($_POST['template_email_id'] ?? 0) ?: null;
        $tw   = (int)($_POST['template_whatsapp_id'] ?? 0) ?: null;
        $at   = isset($_POST['ativa']) ? 1 : 0;
        if ($id) {
            $stmt = $db->prepare('UPDATE regua_etapas SET ordem=?, dias_apos_vencimento=?, template_email_id=?, template_whatsapp_id=?, ativa=? WHERE id=?');
            $stmt->execute([$ord,$dias,$te,$tw,$at,$id]);
        } else {
            $stmt = $db->prepare('INSERT INTO regua_etapas (ordem, dias_apos_vencimento, template_email_id, template_whatsapp_id, ativa) VALUES (?,?,?,?,?)');
            $stmt->execute([$ord,$dias,$te,$tw,$at]);
        }
        audit_log('regua.etapa_salva', 'regua_etapas', $id);
        header('Location: ' . APP_BASE_URL . '/regua.php?ok=1'); exit;
    }

    if ($op === 'remover_etapa') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM regua_etapas WHERE id = ?');
        $stmt->execute([$id]);
        audit_log('regua.etapa_removida', 'regua_etapas', $id);
        header('Location: ' . APP_BASE_URL . '/regua.php?ok=1'); exit;
    }

    if ($op === 'marcar_wa_enviado') {
        $eid = (int)($_POST['evento_id'] ?? 0);
        regua_marcar_evento_enviado($db, $eid, (int)$me['id']);
        audit_log('regua.wa_enviado', 'regua_eventos', $eid);
        header('Location: ' . APP_BASE_URL . '/regua.php#tarefas'); exit;
    }

    if ($op === 'silenciar_cobranca') {
        $cid = (int)($_POST['cobranca_id'] ?? 0);
        $stmt = $db->prepare('UPDATE cobrancas SET silenciada = 1 - silenciada WHERE id = ?');
        $stmt->execute([$cid]);
        audit_log('cobranca.silenciada', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/regua.php#tarefas'); exit;
    }
}

if (isset($_GET['ok'])) $flash = ['ok', 'Salvo.'];

$etapas = $db->query('SELECT re.*, te.codigo AS te_cod, tw.codigo AS tw_cod FROM regua_etapas re LEFT JOIN templates_mensagem te ON te.id = re.template_email_id LEFT JOIN templates_mensagem tw ON tw.id = re.template_whatsapp_id ORDER BY re.ordem')->fetchAll();
$tarefas = regua_tarefas_whatsapp_pendentes($db);
$tpls_email = $db->query("SELECT id, codigo FROM templates_mensagem WHERE canal='email' AND ativo=1 ORDER BY codigo")->fetchAll();
$tpls_wa    = $db->query("SELECT id, codigo FROM templates_mensagem WHERE canal='whatsapp' AND ativo=1 ORDER BY codigo")->fetchAll();

$page = 'Régua de cobrança';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Régua de cobrança</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<h2 id="tarefas">Tarefas WhatsApp pendentes (<?= count($tarefas) ?>)</h2>
<?php if (!$tarefas): ?>
  <p class="muted">Nenhuma tarefa pendente. A régua dispara automaticamente conforme cobranças vencem.</p>
<?php else: foreach ($tarefas as $t):
    $vars = wa_vars_cobranca($db, (int)$t['cobranca_id']);
    $msg  = wa_render($t['template_corpo'], $vars);
    $link = wa_link($t['telefone'], $msg);
?>
  <div class="card attention">
    <div class="title">
      <?= e($t['nome_empresa']) ?>
      <?php $d = (int)$t['dias_apos_vencimento']; ?>
      <span class="status <?= $d < 0 ? 'status-info' : 'status-aberta' ?>">
        <?= $d < 0 ? '−' . abs($d) . ' antes' : ($d === 0 ? 'no dia' : '+' . $d . ' dias') ?>
      </span>
    </div>
    <div class="sub muted">Venc <?= e(date('d/m/Y', strtotime($t['vencimento']))) ?> · <?= e($t['template_codigo']) ?></div>
    <div class="btn-pair mt-3">
      <a class="btn btn-whatsapp" href="<?= e($link) ?>" target="_blank">💬 Abrir WhatsApp</a>
      <form method="post" style="flex:1;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="marcar_wa_enviado">
        <input type="hidden" name="evento_id" value="<?= (int)$t['evento_id'] ?>">
        <button class="btn btn-secondary block" type="submit">✓ Marcar como enviado</button>
      </form>
    </div>
    <form method="post" class="mt-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="silenciar_cobranca">
      <input type="hidden" name="cobranca_id" value="<?= (int)$t['cobranca_id'] ?>">
      <button class="btn btn-ghost small block" type="submit">🔕 Silenciar esta cobrança (negociação direta)</button>
    </form>
  </div>
<?php endforeach; endif; ?>

<h2 class="mt-5">Etapas configuradas</h2>
<p class="muted" style="font-size:13px;">Use dias <strong>negativos</strong> para lembretes <em>antes</em> do vencimento (ex: −3 = 3 dias antes) e <strong>positivos</strong> para cobranças <em>após</em> vencer.</p>
<?php
function formato_dias_etapa(int $d): string {
    if ($d === 0)  return 'no dia do vencimento';
    if ($d < 0)    return abs($d) . ' dia' . (abs($d)>1?'s':'') . ' antes do vencimento';
    return $d . ' dia' . ($d>1?'s':'') . ' após vencimento';
}
?>
<?php foreach ($etapas as $e): ?>
  <div class="card">
    <div class="spaced">
      <div>
        <div class="title">Etapa <?= (int)$e['ordem'] ?> · <?= e(formato_dias_etapa((int)$e['dias_apos_vencimento'])) ?></div>
        <div class="sub muted">
          Email: <?= $e['te_cod'] ? e($e['te_cod']) : '<em>nenhum</em>' ?> ·
          WhatsApp: <?= $e['tw_cod'] ? e($e['tw_cod']) : '<em>nenhum</em>' ?> ·
          <?= $e['ativa'] ? '<span class="status status-paga">ativa</span>' : '<span class="status status-info">inativa</span>' ?>
        </div>
      </div>
    </div>
    <details class="mt-3">
      <summary>Editar</summary>
      <form method="post" class="mt-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="salvar_etapa">
        <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
        <div class="grid-2">
          <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= (int)$e['ordem'] ?>" required></div>
          <div class="field"><label>Dias relativos ao venc.</label><input type="number" name="dias_apos_vencimento" value="<?= (int)$e['dias_apos_vencimento'] ?>" required><div class="hint">negativo = antes · 0 = no dia · positivo = depois</div></div>
        </div>
        <div class="field"><label>Template email</label>
          <select name="template_email_id">
            <option value="">— nenhum —</option>
            <?php foreach ($tpls_email as $tp): ?><option value="<?= (int)$tp['id'] ?>" <?= $e['template_email_id']==$tp['id']?'selected':'' ?>><?= e($tp['codigo']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Template WhatsApp</label>
          <select name="template_whatsapp_id">
            <option value="">— nenhum —</option>
            <?php foreach ($tpls_wa as $tp): ?><option value="<?= (int)$tp['id'] ?>" <?= $e['template_whatsapp_id']==$tp['id']?'selected':'' ?>><?= e($tp['codigo']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <label class="check"><input type="checkbox" name="ativa" <?= $e['ativa']?'checked':'' ?>> Etapa ativa</label>
        <div class="btn-pair mt-3">
          <button class="btn" type="submit">Salvar</button>
          <button class="btn btn-danger" type="submit" name="op" value="remover_etapa" onclick="return confirm('Remover etapa?');">Remover</button>
        </div>
      </form>
    </details>
  </div>
<?php endforeach; ?>

<details class="card mt-3">
  <summary><strong>+ Nova etapa</strong></summary>
  <form method="post" class="mt-3">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="salvar_etapa">
    <input type="hidden" name="id" value="0">
    <div class="grid-2">
      <div class="field"><label>Ordem</label><input type="number" name="ordem" value="<?= count($etapas)+1 ?>" required></div>
      <div class="field"><label>Dias relativos ao venc.</label><input type="number" name="dias_apos_vencimento" value="-3" required><div class="hint">ex: −3 (3 dias antes) · 0 (no dia) · 7 (7 dias após)</div></div>
    </div>
    <div class="field"><label>Template email</label>
      <select name="template_email_id">
        <option value="">— nenhum —</option>
        <?php foreach ($tpls_email as $tp): ?><option value="<?= (int)$tp['id'] ?>"><?= e($tp['codigo']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Template WhatsApp</label>
      <select name="template_whatsapp_id">
        <option value="">— nenhum —</option>
        <?php foreach ($tpls_wa as $tp): ?><option value="<?= (int)$tp['id'] ?>"><?= e($tp['codigo']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <label class="check"><input type="checkbox" name="ativa" checked> Ativa</label>
    <button class="btn block mt-3" type="submit">Criar etapa</button>
  </form>
</details>

<a class="btn btn-secondary block mt-5" href="<?= e(APP_BASE_URL) ?>/templates.php">📝 Editar templates</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
