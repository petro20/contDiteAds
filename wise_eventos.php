<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/configuracoes.php';
$me = require_sadmin();
$db = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['op'] ?? '') === 'toggle_skip_sig') {
        $atual = config_get($db, 'wise_skip_signature');
        config_set($db, 'wise_skip_signature', $atual === '1' ? '0' : '1');
        header('Location: ' . APP_BASE_URL . '/wise_eventos.php'); exit;
    }
    if (($_POST['op'] ?? '') === 'salvar_pub_key') {
        $pem = trim((string)($_POST['pub_key'] ?? ''));
        if ($pem === '') {
            @unlink(__DIR__ . '/wise_public_key.pem');
            header('Location: ' . APP_BASE_URL . '/wise_eventos.php?ok=key_removed'); exit;
        }
        // Valida formato PEM
        if (!preg_match('/^-----BEGIN [A-Z ]+KEY-----/', $pem)) {
            header('Location: ' . APP_BASE_URL . '/wise_eventos.php?err=pem_invalid'); exit;
        }
        $ok = @file_put_contents(__DIR__ . '/wise_public_key.pem', $pem);
        header('Location: ' . APP_BASE_URL . '/wise_eventos.php?ok=' . ($ok?'key_saved':'key_fail')); exit;
    }
}
if (isset($_GET['ok'])) {
    $msg = match($_GET['ok']) {
        'key_saved'   => 'Chave pública salva. Você pode religar a validação agora.',
        'key_removed' => 'Chave pública removida.',
        'key_fail'    => 'Erro ao salvar chave (permissão de escrita?).',
        default       => 'OK',
    };
    $flash = ['ok', $msg];
}
if (isset($_GET['err']) && $_GET['err'] === 'pem_invalid') {
    $flash = ['err', 'A chave deve começar com -----BEGIN PUBLIC KEY----- (formato PEM).'];
}

$skip_sig = config_get($db, 'wise_skip_signature') === '1';

// Lista últimos 100 eventos
$eventos = $db->query("
    SELECT we.*, cl.nome_empresa AS cliente_nome
    FROM wise_eventos we
    LEFT JOIN cobrancas c ON c.id = we.cobranca_id
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    ORDER BY we.recebido_em DESC LIMIT 100
")->fetchAll();

$page = 'Wise — Eventos webhook';
$show_back = true;
$back_to = APP_BASE_URL . '/config_pagamento.php';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">🪝 Wise — Eventos do webhook</h1>
<p class="muted">Histórico de eventos recebidos do Wise via webhook. Quando um pagamento bate com cobrança aberta, o sistema registra automaticamente.</p>

<div class="card brand">
  <div class="title">🌐 URL do webhook</div>
  <p>Configure no painel Wise (Settings → Webhooks) apontando pra:</p>
  <div class="spaced" style="gap:8px; margin-top:8px;">
    <code id="webhook_url" style="flex:1; padding:8px 12px; background:var(--bg-input); border-radius:6px; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e(APP_BASE_URL) ?>/wise_webhook.php</code>
    <button type="button" class="btn small btn-brand" onclick="navigator.clipboard.writeText('<?= e(APP_BASE_URL) ?>/wise_webhook.php').then(()=>{this.innerHTML='✅ Copiado!';setTimeout(()=>this.innerHTML='📋 Copiar',2000)})">📋 Copiar</button>
  </div>
  <p class="hint" style="margin-top:8px;">Inscreva pra eventos <code>balances#credit</code> (recebimento de pagamento).</p>
</div>

<?php if ($flash): ?><div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="card">
  <form method="post" style="display:flex; align-items:center; gap:12px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="op" value="toggle_skip_sig">
    <span style="flex:1;">
      <strong>Validação de assinatura:</strong>
      <?= $skip_sig ? '<span class="status status-vencida">DESLIGADA (modo teste)</span>' : '<span class="status status-paga">LIGADA (produção)</span>' ?>
    </span>
    <button type="submit" class="btn btn-ghost small"><?= $skip_sig ? 'Ligar' : 'Desligar (teste)' ?></button>
  </form>
  <div class="hint">Wise envia assinatura SHA256 do payload. Pra TESTES iniciais (sem chave pública), DESLIGUE. Pra PRODUÇÃO, ligue com chave pública cadastrada abaixo.</div>
</div>

<details class="card">
  <summary style="cursor:pointer; padding:8px 0;"><strong>🔑 Chave pública RSA da Wise</strong> <?= file_exists(__DIR__ . '/wise_public_key.pem') ? '<span class="status status-paga">✓ Salva</span>' : '<span class="status status-info">não configurada</span>' ?></summary>
  <div style="margin-top:12px;">
    <p class="muted" style="font-size:13px;">Pegue a chave pública na documentação da Wise: <a href="https://api-docs.wise.com/api-docs/guides/webhooks/subscription-event-public-keys" target="_blank" rel="noopener" style="color:var(--c-primary-2);">api-docs.wise.com → Webhooks → Public Keys</a>.</p>
    <p class="muted" style="font-size:13px;">Procure por <strong>"Live signing key (production)"</strong>. Copia tudo desde <code>-----BEGIN PUBLIC KEY-----</code> até <code>-----END PUBLIC KEY-----</code> e cola aqui.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="op" value="salvar_pub_key">
      <div class="field">
        <textarea name="pub_key" rows="10" placeholder="-----BEGIN PUBLIC KEY-----&#10;MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...&#10;-----END PUBLIC KEY-----" style="font-family:monospace; font-size:11px;"><?= e(@file_get_contents(__DIR__ . '/wise_public_key.pem') ?: '') ?></textarea>
        <div class="hint">Deixe em branco e salve pra remover a chave.</div>
      </div>
      <button class="btn block" type="submit">Salvar chave pública</button>
    </form>
  </div>
</details>

<h2 class="mt-5">Últimos 100 eventos</h2>
<?php if (!$eventos): ?>
  <div class="card"><div class="title muted">Nenhum evento recebido ainda</div><div class="desc">Configure o webhook no painel do Wise apontando pra URL acima. Depois faça um teste de recebimento.</div></div>
<?php else: foreach ($eventos as $ev):
  $cor = match($ev['status']) {
    'casado'              => 'success',
    'sem_cobranca'        => 'attention',
    'assinatura_invalida' => 'danger',
    'erro'                => 'danger',
    default               => '',
  };
?>
  <details class="card <?= $cor ?>">
    <summary style="cursor:pointer;">
      <strong><?= e($ev['event_type']) ?></strong>
      <?php if ($ev['valor']): ?> · <strong><?= e($ev['moeda']) ?> <?= number_format((float)$ev['valor'], 2, ',', '.') ?></strong><?php endif; ?>
      <?php if ($ev['payer_nome']): ?> · <?= e($ev['payer_nome']) ?><?php endif; ?>
      <span class="status status-info" style="font-size:11px; margin-left:8px;"><?= e($ev['status']) ?></span>
      <span class="muted" style="font-size:12px; float:right;"><?= e(date('d/m/Y H:i', strtotime($ev['recebido_em']))) ?></span>
    </summary>
    <div style="font-size:13px; margin-top:8px;">
      <?php if ($ev['cliente_nome']): ?>
        <p>💳 <strong>Cobrança casada:</strong> <?= e($ev['cliente_nome']) ?> · pagamento_cliente_id #<?= (int)$ev['pagamento_id'] ?></p>
      <?php endif; ?>
      <?php if ($ev['erro']): ?>
        <p style="color:var(--c-danger);"><strong>Erro:</strong> <?= e($ev['erro']) ?></p>
      <?php endif; ?>
      <?php if ($ev['delivery_id']): ?>
        <p class="muted">Delivery ID: <code><?= e($ev['delivery_id']) ?></code></p>
      <?php endif; ?>
      <details style="margin-top:8px;">
        <summary class="muted" style="font-size:12px; cursor:pointer;">Payload bruto</summary>
        <pre style="background:var(--bg-input); padding:8px; border-radius:4px; font-size:11px; overflow-x:auto; max-height:300px;"><?= e($ev['payload_json']) ?></pre>
      </details>
    </div>
  </details>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
