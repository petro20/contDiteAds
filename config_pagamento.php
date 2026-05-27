<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/configuracoes.php';
require_sadmin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $zelle = trim((string)($_POST['zelle_email'] ?? ''));
    $wise  = trim((string)($_POST['wise_link']   ?? ''));
    $instr = trim((string)($_POST['instrucoes']  ?? ''));

    if ($wise && !preg_match('~^https?://~i', $wise)) {
        $flash = ['err', 'O link do Wise precisa começar com http:// ou https://'];
    } else {
        config_set($db, 'pagamento_zelle_email', $zelle);
        config_set($db, 'pagamento_wise_link',   $wise);
        config_set($db, 'pagamento_instrucoes',  $instr);
        audit_log('config_pagamento.atualizada', 'configuracoes', 0);
        $flash = ['ok', 'Configurações de pagamento salvas.'];
    }
}

$cfg = config_pagamento($db);

$page = 'Formas de pagamento';
$show_back = true;
$back_to = APP_BASE_URL . '/dashboard.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">Formas de pagamento</h1>
<p class="muted">Configure os métodos de pagamento que serão exibidos nas cobranças e disponibilizados nas mensagens (templates).</p>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="card">
    <div class="title">💜 Zelle</div>
    <div class="field">
      <label>Email cadastrado no Zelle</label>
      <input type="email" name="zelle_email" value="<?= e($cfg['zelle_email']) ?>" placeholder="ex: voce@gmail.com">
      <div class="hint">O cliente vai usar este email no app do banco dele.</div>
    </div>
  </div>

  <div class="card">
    <div class="title">🌍 Wise</div>
    <div class="field">
      <label>Link público de pagamento</label>
      <input type="url" name="wise_link" value="<?= e($cfg['wise_link']) ?>" placeholder="https://wise.com/pay/me/...">
      <div class="hint">O link gerado em "Receber → Compartilhar página de pagamento" no Wise.</div>
    </div>
  </div>

  <div class="card">
    <div class="title">📝 Instruções adicionais</div>
    <div class="field">
      <label>Texto extra (opcional)</label>
      <textarea name="instrucoes" rows="3" placeholder="ex: Após o pagamento, envie o comprovante pelo botão abaixo."><?= e($cfg['instrucoes']) ?></textarea>
    </div>
  </div>

  <button class="btn block" type="submit">Salvar</button>
</form>

<div class="section-label mt-5">Variáveis nos templates</div>
<div class="card">
  <p>Você pode usar nos templates de email/WhatsApp:</p>
  <ul style="padding-left:20px; color:var(--txt-2);">
    <li><code>{zelle_email}</code> → email cadastrado no Zelle</li>
    <li><code>{link_wise}</code> → link público do Wise</li>
    <li><code>{instrucoes_pagamento}</code> → texto extra</li>
  </ul>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
