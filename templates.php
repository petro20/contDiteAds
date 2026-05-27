<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_sadmin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'apagar') {
        $pid = (int)($_POST['id'] ?? 0);
        if ($pid > 0) {
            try {
                // Verifica se está sendo usado pela régua (FK SET NULL, mas avisa)
                $usos = 0;
                try {
                    $stmt = $db->prepare('SELECT COUNT(*) FROM regua_etapas WHERE template_email_id = ? OR template_whatsapp_id = ?');
                    $stmt->execute([$pid, $pid]);
                    $usos = (int)$stmt->fetchColumn();
                } catch (PDOException $e) {}
                $db->prepare('DELETE FROM templates_mensagem WHERE id = ?')->execute([$pid]);
                audit_log('template.apagado', 'templates_mensagem', $pid);
                $msg = 'Template apagado.' . ($usos > 0 ? ' (estava em uso por ' . $usos . ' etapa(s) da régua — voltaram a usar texto padrão)' : '');
                header('Location: ' . APP_BASE_URL . '/templates.php?ok=del&msg=' . urlencode($msg)); exit;
            } catch (PDOException $e) {
                $flash = ['err', 'Erro ao apagar: ' . $e->getMessage()];
            }
        }
    }
    if (($_POST['op'] ?? '') === 'seed_pagamento') {
        $tpls = [
            [
                'codigo'  => 'cobranca_pagamento',
                'canal'   => 'whatsapp',
                'assunto' => null,
                'corpo'   => "Olá *{nome_cliente}*! 👋\n\nSua cobrança da Dite Ads:\n💵 Valor: *{valor} {moeda}*\n📅 Vencimento: *{vencimento}*\n📋 Mês: {mes_referencia}\n\n📦 *Itens:*\n{itens}\n\n══════════════\n💳 *COMO PAGAR*\n══════════════\n\n💜 *Opção 1 — Zelle*\n1. Abra o app do *seu banco* (Bank of America, Chase, Wells Fargo, etc.)\n2. Procure a opção *Zelle*\n3. Envie pro email: {zelle_email}\n   ou escaneie o QR: {zelle_qr_url}\n4. Valor: *{valor} {moeda}*\n\n🌍 *Opção 2 — Wise*\nClique no link e siga as instruções:\n{link_wise}\n\n══════════════\n\n📤 Após pagar, envie o comprovante pelo link:\n{link_comprovante}\n\n{instrucoes_pagamento}\n\nQualquer dúvida, estamos por aqui! 🚀\n*Dite Ads*",
            ],
            [
                'codigo'  => 'cobranca_pagamento',
                'canal'   => 'email',
                'assunto' => 'Cobrança Dite Ads — {valor} {moeda} (vence {vencimento})',
                'corpo'   => "<p>Olá <strong>{nome_cliente}</strong>,</p>\n<p>Segue sua cobrança da Dite Ads:</p>\n<ul>\n  <li><strong>Valor:</strong> {valor} {moeda}</li>\n  <li><strong>Vencimento:</strong> {vencimento}</li>\n  <li><strong>Mês de referência:</strong> {mes_referencia}</li>\n</ul>\n<p><strong>Itens:</strong></p>\n<pre style=\"background:#f4f4f4;padding:10px;border-radius:4px;\">{itens}</pre>\n<h3 style=\"color:#9333EA;\">💳 Como pagar</h3>\n<h4>💜 Opção 1 — Zelle</h4>\n<ol>\n  <li>Abra o app do <strong>seu banco</strong> (Bank of America, Chase, Wells Fargo, etc.)</li>\n  <li>Procure a opção <strong>Zelle</strong></li>\n  <li>Envie para o email: <strong>{zelle_email}</strong></li>\n  <li>Ou escaneie o QR Code:<br><img src=\"{zelle_qr_url}\" alt=\"QR Zelle\" style=\"max-width:200px;margin-top:8px;\"></li>\n  <li>Valor: <strong>{valor} {moeda}</strong></li>\n</ol>\n<h4>🌍 Opção 2 — Wise</h4>\n<p>Clique no link abaixo e siga as instruções:<br>\n<a href=\"{link_wise}\">{link_wise}</a></p>\n<hr>\n<p>📤 <strong>Após pagar</strong>, envie o comprovante pelo sistema:<br>\n<a href=\"{link_comprovante}\">{link_comprovante}</a></p>\n<p>{instrucoes_pagamento}</p>\n<p>Qualquer dúvida, estamos à disposição.</p>\n<p>— <strong>Dite Ads</strong></p>",
            ],
        ];
        try {
            $stmt = $db->prepare("
                INSERT INTO templates_mensagem (codigo, canal, assunto, corpo, ativo)
                VALUES (?,?,?,?,1)
                ON DUPLICATE KEY UPDATE assunto = VALUES(assunto), corpo = VALUES(corpo), ativo = 1
            ");
            foreach ($tpls as $t) {
                $stmt->execute([$t['codigo'], $t['canal'], $t['assunto'], $t['corpo']]);
            }
            audit_log('template.seed_pagamento', 'templates_mensagem', 0);
            header('Location: ' . APP_BASE_URL . '/templates.php?ok=seed'); exit;
        } catch (PDOException $e) {
            $flash = ['err', 'Erro: ' . $e->getMessage()];
        }
    }
    if (($_POST['op'] ?? '') === 'salvar') {
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
                header('Location: ' . APP_BASE_URL . '/templates.php?ok=1'); exit;
            } catch (PDOException $e) {
                $flash = ['err', (int)$e->errorInfo[1] === 1062 ? 'Já existe template com este código+canal.' : $e->getMessage()];
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash = ['ok', match($_GET['ok']) {
        'seed' => 'Templates padrão de pagamento instalados (WhatsApp + Email).',
        'del'  => $_GET['msg'] ?? 'Template apagado.',
        default => 'Template salvo.',
    }];
}

$page = 'Templates de mensagem';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$novo = isset($_GET['novo']);

if ($id || $novo) {
    $t = ['id'=>0,'codigo'=>'','canal'=>'whatsapp','assunto'=>'','corpo'=>'','ativo'=>1];
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM templates_mensagem WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $t = array_merge($t, $row);
    }
    ?>
    <?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <div class="card">
        <div class="field"><label>Código (identificador único)</label>
          <input name="codigo" required value="<?= e($t['codigo']) ?>" placeholder="ex: cobranca_nova, lembrete_vencendo">
        </div>
        <div class="field"><label>Canal</label>
          <select name="canal">
            <option value="whatsapp" <?= $t['canal']==='whatsapp'?'selected':'' ?>>WhatsApp</option>
            <option value="email"    <?= $t['canal']==='email'?'selected':'' ?>>Email</option>
          </select>
        </div>
        <div class="field"><label>Assunto (só email)</label><input name="assunto" value="<?= e($t['assunto'] ?? '') ?>"></div>
        <div class="field">
          <label>Corpo</label>
          <textarea name="corpo" required rows="10"><?= e($t['corpo']) ?></textarea>
          <div class="hint">Variáveis: <code>{nome_cliente}</code>, <code>{nome_empresa}</code>, <code>{valor}</code>, <code>{moeda}</code>, <code>{vencimento}</code>, <code>{mes_referencia}</code>, <code>{itens}</code>, <code>{link_recibo}</code>, <code>{link_comprovante}</code>, <code>{link_sistema}</code>, <code>{zelle_email}</code>, <code>{zelle_qr_url}</code>, <code>{link_wise}</code>, <code>{instrucoes_pagamento}</code></div>
        </div>
        <label class="check"><input type="checkbox" name="ativo" <?= $t['ativo']?'checked':'' ?>> Ativo</label>
      </div>
      <button class="btn block" type="submit">Salvar</button>
    </form>

    <?php if ((int)$t['id'] > 0): ?>
      <h2 class="mt-5">⚠ Zona de perigo</h2>
      <form method="post" onsubmit="return confirm('APAGAR DEFINITIVAMENTE este template?\n\nSe alguma etapa da régua estiver usando ele, vai voltar a usar texto padrão.\n\nConfirmar?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="op" value="apagar">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <button class="btn btn-danger block" type="submit">🗑 Apagar este template</button>
      </form>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tpls = $db->query('SELECT * FROM templates_mensagem ORDER BY canal, codigo')->fetchAll();
?>
<h1 class="page-title">Templates</h1>
<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<div class="btn-pair">
  <a class="btn btn-brand" href="?novo=1">+ Novo template</a>
  <form method="post" style="margin:0; flex:1;" onsubmit="return confirm('Vai instalar/sobrescrever os templates padrão de cobrança (WhatsApp + Email) com instruções completas de Zelle/QR/Wise.\n\nConfirmar?');">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="seed_pagamento">
    <button type="submit" class="btn btn-secondary block">✨ Instalar templates de pagamento</button>
  </form>
</div>

<div class="section-label mt-5">Cadastrados (<?= count($tpls) ?>)</div>
<?php foreach ($tpls as $t): ?>
  <a class="list-card" href="?id=<?= (int)$t['id'] ?>">
    <div class="info">
      <div class="nome">
        <?= e($t['codigo']) ?>
        <span class="status status-<?= $t['canal']==='whatsapp'?'paga':'info' ?>"><?= e($t['canal']) ?></span>
        <?php if (!$t['ativo']): ?><span class="status status-info">inativo</span><?php endif; ?>
      </div>
      <div class="sub muted"><?= e(mb_substr($t['corpo'], 0, 80)) ?>...</div>
    </div>
  </a>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
