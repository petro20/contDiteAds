<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/regua.php';
require_once __DIR__ . '/lib/whatsapp.php';
$me = require_sadmin();
$db = db();
$flash = null;

$aba = $_GET['aba'] ?? 'etapas';
if (!in_array($aba, ['etapas','tarefas','templates'], true)) $aba = 'etapas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = $_POST['op'] ?? '';

    // ===== ETAPAS =====
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
        header('Location: ' . APP_BASE_URL . '/regua.php?aba=etapas&ok=1'); exit;
    }
    if ($op === 'remover_etapa') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM regua_etapas WHERE id = ?')->execute([$id]);
        audit_log('regua.etapa_removida', 'regua_etapas', $id);
        header('Location: ' . APP_BASE_URL . '/regua.php?aba=etapas&ok=1'); exit;
    }

    // ===== TAREFAS =====
    if ($op === 'marcar_wa_enviado') {
        $eid = (int)($_POST['evento_id'] ?? 0);
        regua_marcar_evento_enviado($db, $eid, (int)$me['id']);
        audit_log('regua.wa_enviado', 'regua_eventos', $eid);
        header('Location: ' . APP_BASE_URL . '/regua.php?aba=tarefas'); exit;
    }
    if ($op === 'silenciar_cobranca') {
        $cid = (int)($_POST['cobranca_id'] ?? 0);
        $db->prepare('UPDATE cobrancas SET silenciada = 1 - silenciada WHERE id = ?')->execute([$cid]);
        audit_log('cobranca.silenciada', 'cobrancas', $cid);
        header('Location: ' . APP_BASE_URL . '/regua.php?aba=tarefas'); exit;
    }

    // ===== TEMPLATES =====
    if ($op === 'salvar_template') {
        $id      = (int)($_POST['id'] ?? 0);
        $codigo  = trim((string)($_POST['codigo'] ?? ''));
        $canal   = $_POST['canal'] ?? 'email';
        if (!in_array($canal, ['email','whatsapp'], true)) $canal = 'email';
        $assunto = trim((string)($_POST['assunto'] ?? '')) ?: null;
        $corpo   = trim((string)($_POST['corpo'] ?? ''));
        $ativo   = isset($_POST['ativo']) ? 1 : 0;
        if ($codigo === '' || $corpo === '') {
            $flash = ['err', 'Código e corpo obrigatórios.'];
        } else {
            try {
                if ($id) {
                    $stmt = $db->prepare('UPDATE templates_mensagem SET codigo=?, canal=?, assunto=?, corpo=?, ativo=? WHERE id=?');
                    $stmt->execute([$codigo, $canal, $assunto, $corpo, $ativo, $id]);
                } else {
                    $stmt = $db->prepare('INSERT INTO templates_mensagem (codigo, canal, assunto, corpo, ativo) VALUES (?,?,?,?,?)');
                    $stmt->execute([$codigo, $canal, $assunto, $corpo, $ativo]);
                    $id = (int)$db->lastInsertId();
                }
                audit_log('template.salvo', 'templates_mensagem', $id);
                header('Location: ' . APP_BASE_URL . '/regua.php?aba=templates&ok=tpl'); exit;
            } catch (PDOException $e) {
                $flash = ['err', (int)$e->errorInfo[1] === 1062 ? 'Já existe template com este código+canal.' : $e->getMessage()];
            }
        }
    }
    if ($op === 'apagar_template') {
        $pid = (int)($_POST['id'] ?? 0);
        if ($pid > 0) {
            try {
                $usos = 0;
                try {
                    $stmt = $db->prepare('SELECT COUNT(*) FROM regua_etapas WHERE template_email_id = ? OR template_whatsapp_id = ?');
                    $stmt->execute([$pid, $pid]);
                    $usos = (int)$stmt->fetchColumn();
                } catch (PDOException $e) {}
                $db->prepare('DELETE FROM templates_mensagem WHERE id = ?')->execute([$pid]);
                audit_log('template.apagado', 'templates_mensagem', $pid);
                $msg = 'Template apagado.' . ($usos > 0 ? ' (estava em ' . $usos . ' etapa(s) da régua)' : '');
                header('Location: ' . APP_BASE_URL . '/regua.php?aba=templates&ok=del&msg=' . urlencode($msg)); exit;
            } catch (PDOException $e) {
                $flash = ['err', 'Erro ao apagar: ' . $e->getMessage()];
            }
        }
    }
    if ($op === 'seed_pagamento') {
        $tpls = [
            [
                'codigo' => 'cobranca_pagamento', 'canal' => 'whatsapp', 'assunto' => null,
                'corpo' => "Olá *{nome_cliente}*! 👋\n\nSua cobrança da Dite Ads:\n💵 Valor: *{valor} {moeda}*\n📅 Vencimento: *{vencimento}*\n📋 Mês: {mes_referencia}\n\n📦 *Itens:*\n{itens}\n\n══════════════\n💳 *COMO PAGAR*\n══════════════\n\n💜 *Opção 1 — Zelle*\n1. Abra o app do *seu banco* (Bank of America, Chase, Wells Fargo, etc.)\n2. Procure a opção *Zelle*\n3. Envie pro email: {zelle_email}\n   ou escaneie o QR: {zelle_qr_url}\n4. Valor: *{valor} {moeda}*\n\n🌍 *Opção 2 — Wise*\nClique no link e siga as instruções:\n{link_wise}\n\n══════════════\n\n📤 Após pagar, envie o comprovante pelo link:\n{link_comprovante}\n\n{instrucoes_pagamento}\n\nQualquer dúvida, estamos por aqui! 🚀\n*Dite Ads*",
            ],
            [
                'codigo' => 'cobranca_pagamento', 'canal' => 'email',
                'assunto' => 'Cobrança Dite Ads — {valor} {moeda} (vence {vencimento})',
                'corpo' => "<p>Olá <strong>{nome_cliente}</strong>,</p>\n<p>Segue sua cobrança da Dite Ads:</p>\n<ul>\n  <li><strong>Valor:</strong> {valor} {moeda}</li>\n  <li><strong>Vencimento:</strong> {vencimento}</li>\n  <li><strong>Mês de referência:</strong> {mes_referencia}</li>\n</ul>\n<p><strong>Itens:</strong></p>\n<pre style=\"background:#f4f4f4;padding:10px;border-radius:4px;\">{itens}</pre>\n<h3 style=\"color:#9333EA;\">💳 Como pagar</h3>\n<h4>💜 Opção 1 — Zelle</h4>\n<ol>\n  <li>Abra o app do <strong>seu banco</strong></li>\n  <li>Procure a opção <strong>Zelle</strong></li>\n  <li>Envie para o email: <strong>{zelle_email}</strong></li>\n  <li>Ou escaneie o QR Code:<br><img src=\"{zelle_qr_url}\" alt=\"QR Zelle\" style=\"max-width:200px;margin-top:8px;\"></li>\n  <li>Valor: <strong>{valor} {moeda}</strong></li>\n</ol>\n<h4>🌍 Opção 2 — Wise</h4>\n<p>Clique no link abaixo:<br>\n<a href=\"{link_wise}\">{link_wise}</a></p>\n<hr>\n<p>📤 <strong>Após pagar</strong>, envie o comprovante:<br>\n<a href=\"{link_comprovante}\">{link_comprovante}</a></p>\n<p>{instrucoes_pagamento}</p>\n<p>— <strong>Dite Ads</strong></p>",
            ],
        ];
        try {
            $stmt = $db->prepare("INSERT INTO templates_mensagem (codigo, canal, assunto, corpo, ativo) VALUES (?,?,?,?,1)
                                  ON DUPLICATE KEY UPDATE assunto = VALUES(assunto), corpo = VALUES(corpo), ativo = 1");
            foreach ($tpls as $t) $stmt->execute([$t['codigo'], $t['canal'], $t['assunto'], $t['corpo']]);
            audit_log('template.seed_pagamento', 'templates_mensagem', 0);
            header('Location: ' . APP_BASE_URL . '/regua.php?aba=templates&ok=seed'); exit;
        } catch (PDOException $e) {
            $flash = ['err', 'Erro: ' . $e->getMessage()];
        }
    }
}

if (isset($_GET['ok'])) {
    switch ($_GET['ok']) {
        case 'seed': $msg = 'Templates padrão de pagamento instalados.'; break;
        case 'del':  $msg = $_GET['msg'] ?? 'Apagado.'; break;
        case 'tpl':  $msg = 'Template salvo.'; break;
        default:     $msg = 'Salvo.';
    }
    $flash = ['ok', $msg];
}

// Edição inline de template
$edit_tpl_id = (int)($_GET['edit_tpl'] ?? 0);
$novo_tpl    = isset($_GET['novo_tpl']);
$tpl_edit    = null;
if ($edit_tpl_id || $novo_tpl) {
    $aba = 'templates';
    $tpl_edit = ['id'=>0,'codigo'=>'','canal'=>'whatsapp','assunto'=>'','corpo'=>'','ativo'=>1];
    if ($edit_tpl_id) {
        $stmt = $db->prepare('SELECT * FROM templates_mensagem WHERE id = ?');
        $stmt->execute([$edit_tpl_id]);
        $row = $stmt->fetch();
        if ($row) $tpl_edit = array_merge($tpl_edit, $row);
    }
}

$etapas = $db->query('SELECT re.*, te.codigo AS te_cod, tw.codigo AS tw_cod FROM regua_etapas re LEFT JOIN templates_mensagem te ON te.id = re.template_email_id LEFT JOIN templates_mensagem tw ON tw.id = re.template_whatsapp_id ORDER BY re.ordem')->fetchAll();
$tarefas = regua_tarefas_whatsapp_pendentes($db);
$tpls_email = $db->query("SELECT id, codigo FROM templates_mensagem WHERE canal='email' AND ativo=1 ORDER BY codigo")->fetchAll();
$tpls_wa    = $db->query("SELECT id, codigo FROM templates_mensagem WHERE canal='whatsapp' AND ativo=1 ORDER BY codigo")->fetchAll();
$todos_tpls = $db->query('SELECT * FROM templates_mensagem ORDER BY canal, codigo')->fetchAll();

if (!function_exists('formato_dias_etapa')) {
    function formato_dias_etapa(int $d): string {
        if ($d === 0)  return 'no dia do vencimento';
        if ($d < 0)    return abs($d) . ' dia' . (abs($d)>1?'s':'') . ' antes do vencimento';
        return $d . ' dia' . ($d>1?'s':'') . ' após vencimento';
    }
}

$page = 'Comunicação';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Comunicação com clientes</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<nav class="tabs-bar">
  <a class="<?= $aba==='etapas'?'active':'' ?>" href="?aba=etapas">⏰ Etapas (<?= count($etapas) ?>)</a>
  <a class="<?= $aba==='tarefas'?'active':'' ?>" href="?aba=tarefas">📤 Tarefas (<?= count($tarefas) ?>)</a>
  <a class="<?= $aba==='templates'?'active':'' ?>" href="?aba=templates">📝 Templates (<?= count($todos_tpls) ?>)</a>
</nav>

<?php if ($tpl_edit !== null): // === EDIÇÃO INLINE DE TEMPLATE === ?>
  <h2><?= $tpl_edit['id'] ? 'Editar template' : 'Novo template' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="salvar_template">
    <input type="hidden" name="id" value="<?= (int)$tpl_edit['id'] ?>">
    <div class="card">
      <div class="field"><label>Código (identificador único)</label>
        <input name="codigo" required value="<?= e($tpl_edit['codigo']) ?>" placeholder="ex: cobranca_pagamento, lembrete_vencendo">
      </div>
      <div class="field"><label>Canal</label>
        <select name="canal">
          <option value="whatsapp" <?= $tpl_edit['canal']==='whatsapp'?'selected':'' ?>>WhatsApp</option>
          <option value="email"    <?= $tpl_edit['canal']==='email'?'selected':'' ?>>Email</option>
        </select>
      </div>
      <div class="field"><label>Assunto (só email)</label><input name="assunto" value="<?= e($tpl_edit['assunto'] ?? '') ?>"></div>
      <div class="field">
        <label>Corpo</label>
        <textarea name="corpo" required rows="10"><?= e($tpl_edit['corpo']) ?></textarea>
        <div class="hint">Variáveis: <code>{nome_cliente}</code>, <code>{nome_empresa}</code>, <code>{valor}</code>, <code>{moeda}</code>, <code>{vencimento}</code>, <code>{mes_referencia}</code>, <code>{itens}</code>, <code>{link_recibo}</code>, <code>{link_comprovante}</code>, <code>{link_sistema}</code>, <code>{zelle_email}</code>, <code>{zelle_qr_url}</code>, <code>{link_wise}</code>, <code>{instrucoes_pagamento}</code></div>
      </div>
      <label class="check"><input type="checkbox" name="ativo" <?= $tpl_edit['ativo']?'checked':'' ?>> Ativo</label>
    </div>
    <div class="btn-pair">
      <a href="?aba=templates" class="btn btn-ghost">Cancelar</a>
      <button class="btn block" type="submit">Salvar</button>
    </div>
  </form>

  <?php if ((int)$tpl_edit['id'] > 0): ?>
    <h2 class="mt-5">⚠ Zona de perigo</h2>
    <form method="post" onsubmit="return confirm('APAGAR este template definitivamente?\n\nSe estiver em uso pela régua, as etapas voltam a usar texto padrão.\n\nConfirmar?');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="apagar_template">
      <input type="hidden" name="id" value="<?= (int)$tpl_edit['id'] ?>">
      <button class="btn btn-danger block" type="submit">🗑 Apagar este template</button>
    </form>
  <?php endif; ?>

<?php elseif ($aba === 'tarefas'): ?>
  <h2>Tarefas WhatsApp pendentes</h2>
  <?php if (!$tarefas): ?>
    <p class="muted">Nenhuma tarefa pendente. A régua dispara automaticamente conforme cobranças chegam ao prazo configurado.</p>
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
        <button class="btn btn-ghost small block" type="submit">🔕 Silenciar esta cobrança</button>
      </form>
    </div>
  <?php endforeach; endif; ?>

<?php elseif ($aba === 'templates'): ?>
  <div class="btn-pair">
    <a class="btn btn-brand" href="?aba=templates&novo_tpl=1">+ Novo template</a>
    <form method="post" style="margin:0; flex:1;" onsubmit="return confirm('Vai instalar/sobrescrever os templates padrão de cobrança (WhatsApp + Email) com instruções completas de Zelle/QR/Wise.\n\nConfirmar?');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="seed_pagamento">
      <button type="submit" class="btn btn-secondary block">✨ Instalar templates de pagamento</button>
    </form>
  </div>

  <div class="section-label mt-5">Cadastrados (<?= count($todos_tpls) ?>)</div>
  <?php foreach ($todos_tpls as $t): ?>
    <a class="list-card" href="?aba=templates&edit_tpl=<?= (int)$t['id'] ?>">
      <div class="info">
        <div class="nome">
          <?= e($t['codigo']) ?>
          <span class="status status-<?= $t['canal']==='whatsapp'?'paga':'info' ?>"><?= e($t['canal']) ?></span>
          <?php if (!$t['ativo']): ?><span class="status status-info">inativo</span><?php endif; ?>
        </div>
        <div class="sub muted"><?= e(mb_substr(strip_tags($t['corpo']), 0, 80)) ?>...</div>
      </div>
    </a>
  <?php endforeach; ?>

<?php else: // ===== ABA ETAPAS (default) ===== ?>
  <p class="muted" style="font-size:13px;">Use dias <strong>negativos</strong> para lembretes <em>antes</em> do vencimento (ex: −3 = 3 dias antes) e <strong>positivos</strong> para cobranças <em>após</em> vencer.</p>

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
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
