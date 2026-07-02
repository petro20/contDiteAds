<?php
require_once __DIR__ . '/auth.php';
$u = current_user();
$page         = $page ?? 'contDiteAds';
$page_sub     = $page_sub     ?? null;
// Por padrão, mostra botão de voltar em toda página exceto na raiz (dashboard)
$is_dashboard = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'dashboard.php');
$show_back    = $show_back    ?? !$is_dashboard;
$back_to      = $back_to      ?? null;
$hide_nav     = $hide_nav     ?? false;
$nav_active   = $nav_active   ?? '';
// Páginas dinâmicas nunca devem ser cacheadas pelo servidor/CDN/navegador —
// senão trocar de idioma (ou qualquer dado) fica "congelado" numa versão velha.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?><!doctype html>
<html lang="<?= e(idioma_atual() === 'pt' ? 'pt-br' : idioma_atual()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0A0A0F">
<!-- App feel: roda em tela cheia (standalone) quando instalado no celular -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="Dite Ads">
<title><?= e($page) ?> · controle e gestão Dite Ads</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(APP_BASE_URL) ?>/assets/img/logo-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(APP_BASE_URL) ?>/assets/img/logo-180.png">
<link rel="manifest" href="<?= e(APP_BASE_URL) ?>/manifest.json">
<link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=<?= e(@filemtime(__DIR__ . '/../assets/css/style.css') ?: '1') ?>">
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= e(APP_BASE_URL) ?>/OneSignalSDKWorker.js').then(reg => {
    // Força check de atualização ao carregar página
    reg.update();
    // Quando uma nova versão é instalada, força refresh automático
    reg.addEventListener('updatefound', () => {
      const nw = reg.installing;
      if (!nw) return;
      nw.addEventListener('statechange', () => {
        if (nw.state === 'activated' && navigator.serviceWorker.controller) {
          // Nova versão ativada — recarrega 1x pra pegar CSS/JS novos
          if (!sessionStorage.getItem('_sw_reloaded')) {
            sessionStorage.setItem('_sw_reloaded', '1');
            window.location.reload();
          }
        }
      });
    });
  }).catch(()=>{});

  // Kill switch: desregistra SWs antigos (versões anteriores a v13)
  // E força reload pra pegar tudo novo. Roda 1x por sessão.
  if (!sessionStorage.getItem('_sw_purged_v13')) {
    sessionStorage.setItem('_sw_purged_v13', '1');
    caches.keys().then(keys => {
      const oldCaches = keys.filter(k => k.startsWith('diteads-') && !k.includes('v13') && !k.includes('v14') && !k.includes('v15'));
      if (oldCaches.length > 0) {
        Promise.all(oldCaches.map(k => caches.delete(k))).then(() => {
          console.log('Caches antigos removidos:', oldCaches);
          window.location.reload();
        });
      }
    });
  }
}

// Toggle "olho" pra mostrar/esconder senha em campos type=password
function togglePassword(btn) {
  const input = btn.previousElementSibling;
  if (!input || (input.type !== 'password' && input.type !== 'text')) return;
  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  btn.innerHTML = showing ? '👁' : '🙈';
  btn.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Esconder senha');
}

// === Detecção de autofill (3 camadas defensivas) ===
// O Chrome pinta inputs autofilled de branco/amarelo. Pra forçar tema dark:
// 1. animationstart event (rápido, mas pode falhar em alguns Chromes)
// 2. setInterval polling com :-webkit-autofill (backup brute-force)
// 3. Verificação no DOMContentLoaded (cobre o caso inicial)
function _markAutofilled(input) {
  try {
    const isAutofilled = input.matches(':-webkit-autofill') || input.matches(':autofill');
    if (isAutofilled) {
      input.classList.add('autofilled');
      // Solução nuclear: aplica style INLINE direto no elemento.
      // Style inline tem specificity (1,0,0,0) — mais forte que qualquer regra CSS,
      // inclusive !important. Garante 100% mesmo se o Chrome sincronizar autofill
      // entre sessões anônima/normal via conta Google.
      const cssText =
        'background-color: var(--bg-input) !important;' +
        'background-image: none !important;' +
        'color: var(--txt-1) !important;' +
        '-webkit-text-fill-color: var(--txt-1) !important;' +
        '-webkit-box-shadow: 0 0 0 1000px var(--bg-input) inset !important;' +
                'box-shadow: 0 0 0 1000px var(--bg-input) inset !important;' +
        'caret-color: var(--txt-1) !important;' +
        'border: 1px solid var(--border) !important;' +
        'border-radius: var(--r-md) !important;' +
        '-webkit-transition: background-color 99999s ease 0s !important;' +
                'transition: background-color 99999s ease 0s !important;';
      input.style.cssText = cssText;
    } else {
      input.classList.remove('autofilled');
      // Se já tinha style inline forçado, mas não está mais autofilled, limpa
      if (input.style.cssText.indexOf('var(--bg-input)') !== -1) {
        input.style.cssText = '';
      }
    }
  } catch (e) { /* :autofill não suportado, ignora */ }
}
function _scanAutofill() {
  document.querySelectorAll('input, textarea').forEach(_markAutofilled);
}
// Camada 1: animationstart
document.addEventListener('animationstart', function(e) {
  if (e.animationName === 'onAutoFillStart') {
    e.target.classList.add('autofilled');
  } else if (e.animationName === 'onAutoFillCancel') {
    e.target.classList.remove('autofilled');
  }
}, true);
// Camada 2: scan inicial assim que DOM carregar
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', _scanAutofill);
} else {
  _scanAutofill();
}
// Camada 3: polling defensivo nos primeiros 5 segundos (autofill às vezes
// acontece depois do load, especialmente em PWA / Safari)
let _autofillPolls = 0;
const _autofillInterval = setInterval(function() {
  _scanAutofill();
  if (++_autofillPolls > 10) clearInterval(_autofillInterval); // 10 × 500ms = 5s
}, 500);
</script>
</head>
<body>
<?php if ($u && !$hide_nav): ?>
<header class="topbar">
  <?php if ($show_back): ?>
    <a class="back-btn" href="<?= e($back_to ?? (APP_BASE_URL . '/dashboard.php')) ?>" onclick="if(history.length>1){history.back();return false;}" aria-label="Voltar">←</a>
  <?php else: ?>
    <a class="brand-link" href="<?= e(APP_BASE_URL) ?>/dashboard.php" aria-label="Início">
      <img src="<?= e(APP_BASE_URL) ?>/assets/img/logo.png" alt="Dite Ads" class="brand-logo">
    </a>
  <?php endif; ?>
  <div class="titles<?= $show_back ? '' : ' centered' ?>">
    <?php if ($show_back): ?>
      <h1><?= e($page) ?></h1>
      <?php if ($page_sub): ?><div class="sub"><?= e($page_sub) ?></div><?php endif; ?>
    <?php else: ?>
      <span class="topbar-title"><?= e(t('Controle Gerencial')) ?></span>
    <?php endif; ?>
  </div>
  <?php
  // Calcula contagens de notificações pra mostrar no sino
  $notif_count = 0;
  $notif_items = [];
  try {
    if (is_admin()) {
      // Comprovantes em análise
      $n = (int)db()->query("SELECT COUNT(*) FROM cobrancas WHERE status='em_analise'")->fetchColumn();
      if ($n > 0) { $notif_count += $n; $notif_items[] = ['icone'=>'🔔','titulo'=>$n.' comprovante(s) em análise','href'=>APP_BASE_URL.'/cobrancas.php?status=em_analise','cor'=>'orange']; }
      // Cobranças vencidas
      $n = (int)db()->query("SELECT COUNT(*) FROM cobrancas WHERE status='aberta' AND vencimento < CURDATE()")->fetchColumn();
      if ($n > 0) { $notif_count += $n; $notif_items[] = ['icone'=>'💢','titulo'=>$n.' cobrança(s) vencida(s)','href'=>APP_BASE_URL.'/cobrancas.php?status=aberta','cor'=>'danger']; }
      // Pagamentos na fila
      try {
        require_once __DIR__ . '/../lib/pagamentos.php';
        $fila = fila_pagamentos_funcionarios(db());
        $n = count($fila);
        if ($n > 0) { $notif_count += $n; $notif_items[] = ['icone'=>'💵','titulo'=>$n.' funcionário(s) prontos pra pagar','href'=>APP_BASE_URL.'/pagamentos_funcionarios.php','cor'=>'info']; }
      } catch (Throwable $e) {}
    } elseif ($u['role'] === 'cliente' && !empty($u['cliente_id'])) {
      // Cobranças vencidas do cliente
      $stmt = db()->prepare("SELECT COUNT(*) FROM cobrancas WHERE cliente_id=? AND status='aberta' AND vencimento < CURDATE()");
      $stmt->execute([(int)$u['cliente_id']]);
      $n = (int)$stmt->fetchColumn();
      if ($n > 0) { $notif_count += $n; $notif_items[] = ['icone'=>'⚠','titulo'=>$n.' cobrança(s) vencida(s)','href'=>APP_BASE_URL.'/cobrancas.php','cor'=>'danger']; }
      // Cobranças abertas
      $stmt = db()->prepare("SELECT COUNT(*) FROM cobrancas WHERE cliente_id=? AND status='aberta' AND vencimento >= CURDATE()");
      $stmt->execute([(int)$u['cliente_id']]);
      $n = (int)$stmt->fetchColumn();
      if ($n > 0) { $notif_items[] = ['icone'=>'⏳','titulo'=>$n.' cobrança(s) em aberto','href'=>APP_BASE_URL.'/cobrancas.php','cor'=>'info']; }
    } elseif ($u['role'] === 'funcionario') {
      // Pagamentos pendentes pro funcionário
      try {
        require_once __DIR__ . '/../lib/pagamentos.php';
        $pend = itens_pendentes_funcionario(db(), (int)$u['id']);
        $n = count($pend);
        if ($n > 0) { $notif_items[] = ['icone'=>'💵','titulo'=>$n.' item(ns) aguardando pagamento','href'=>APP_BASE_URL.'/meus_pagamentos.php','cor'=>'success']; }
      } catch (Throwable $e) {}
    }
  } catch (Throwable $e) {}
  ?>
  <div class="actions">
    <select aria-label="<?= e(t('Idioma')) ?>" title="<?= e(t('Idioma')) ?>"
            onchange="location.href = location.pathname + '?lang=' + this.value;"
            style="background:var(--bg-input); border:1px solid var(--border); color:var(--txt-2); font-size:12px; font-weight:600; border-radius:var(--r-md); padding:4px 6px; cursor:pointer;">
      <option value="pt" <?= idioma_atual()==='pt'?'selected':'' ?>>PT</option>
      <option value="en" <?= idioma_atual()==='en'?'selected':'' ?>>EN</option>
      <option value="es" <?= idioma_atual()==='es'?'selected':'' ?>>ES</option>
    </select>
    <button type="button" onclick="abrirBusca()" aria-label="<?= e(t('Buscar')) ?>" title="<?= e(t('Buscar')) ?> (Ctrl+K)" style="font-size:18px;">🔍</button>
    <button type="button" onclick="toggleNotif(event)" onmouseenter="showNotif()" onmouseleave="scheduleHideNotif()" aria-label="Notificações" title="Notificações" style="font-size:18px; position:relative;">
      🔔
      <?php if ($notif_count > 0): ?>
        <span style="position:absolute; top:2px; right:2px; background:var(--c-danger); color:#fff; border-radius:10px; padding:1px 5px; font-size:10px; font-weight:700; min-width:16px; text-align:center; pointer-events:none;"><?= $notif_count > 99 ? '99+' : $notif_count ?></span>
      <?php endif; ?>
    </button>
    <a href="<?= e(APP_BASE_URL) ?>/perfil.php" aria-label="Perfil">👤</a>
  </div>
</header>

<!-- Dropdown de notificações -->
<div id="notif_drop" onmouseenter="cancelHideNotif()" onmouseleave="scheduleHideNotif()" style="display:none; position:fixed; top:56px; right:8px; z-index:999; width:320px; max-width:calc(100vw - 16px); background:var(--bg-elevated); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.4); overflow:hidden;">
  <div style="padding:10px 14px; border-bottom:1px solid var(--border); background:var(--bg-input);">
    <strong><?= e(t('🔔 Notificações')) ?></strong>
  </div>
  <div id="push_ativar_wrap" style="padding:10px 14px; border-bottom:1px solid var(--border); display:none;">
    <button type="button" id="btn_ativar_push" class="btn btn-brand small block"><?= e(t('🔔 Ativar notificações neste aparelho')) ?></button>
    <div class="hint" style="margin-top:6px;"><?= e(t('Receba avisos mesmo com o site fechado.')) ?></div>
  </div>
  <div style="max-height:60vh; overflow-y:auto;">
    <?php if (!$notif_items): ?>
      <div style="padding:24px; text-align:center; color:var(--txt-2); font-size:13px;"><?= e(t('Nenhuma notificação pendente ✨')) ?></div>
    <?php else: foreach ($notif_items as $n): ?>
      <a href="<?= e($n['href']) ?>" style="display:flex; gap:10px; padding:12px 14px; text-decoration:none; color:var(--txt-1); border-bottom:1px solid var(--border); align-items:center;">
        <span style="font-size:20px;"><?= $n['icone'] ?></span>
        <span style="flex:1; font-size:13px;"><?= e($n['titulo']) ?></span>
        <span style="color:var(--c-<?= e($n['cor']) ?>); font-size:18px;">→</span>
      </a>
    <?php endforeach; endif; ?>
  </div>
</div>

<script>
function toggleNotif(e) {
  if (e) { e.stopPropagation(); e.preventDefault(); }
  const d = document.getElementById('notif_drop');
  if (!d) return;
  d.style.display = d.style.display === 'block' ? 'none' : 'block';
}
// Abertura por hover (passar o mouse). O atraso ao fechar deixa o mouse
// viajar do sino até o dropdown sem ele sumir no caminho.
let _notifHideTimer = null;
function showNotif() {
  clearTimeout(_notifHideTimer);
  const d = document.getElementById('notif_drop');
  if (d) d.style.display = 'block';
}
function cancelHideNotif() {
  clearTimeout(_notifHideTimer);
}
function scheduleHideNotif() {
  clearTimeout(_notifHideTimer);
  _notifHideTimer = setTimeout(function () {
    const d = document.getElementById('notif_drop');
    if (d) d.style.display = 'none';
  }, 250);
}
document.addEventListener('click', function(e) {
  const d = document.getElementById('notif_drop');
  if (!d || d.style.display !== 'block') return;
  // Não fecha se clicou dentro do dropdown ou no próprio botão do sino
  if (d.contains(e.target)) return;
  const btn = e.target.closest('button[aria-label="Notificações"]');
  if (btn) return;
  d.style.display = 'none';
});
</script>

<!-- Modal de busca global -->
<div id="busca_overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; padding:60px 16px 16px;" onclick="if(event.target===this) fecharBusca()">
  <div style="max-width:600px; margin:0 auto; background:var(--bg-elevated); border-radius:12px; overflow:hidden;">
    <div style="padding:16px; border-bottom:1px solid var(--border);">
      <input id="busca_input" type="text" placeholder="Buscar cliente, cobrança, funcionário, item..." autocomplete="off"
        style="width:100%; padding:12px 14px; background:var(--bg-input); border:1px solid var(--border); border-radius:8px; color:var(--txt-1); font-size:15px;"
        oninput="buscarGlobal(this.value)">
      <div class="hint" style="margin-top:6px;">Digite pelo menos 2 caracteres. <kbd>Esc</kbd> fecha. <kbd>Ctrl+K</kbd> abre em qualquer tela.</div>
    </div>
    <div id="busca_resultados" style="max-height:60vh; overflow-y:auto; padding:8px;"></div>
  </div>
</div>

<script>
function abrirBusca() {
  document.getElementById('busca_overlay').style.display = 'block';
  setTimeout(() => document.getElementById('busca_input').focus(), 50);
}
function fecharBusca() {
  document.getElementById('busca_overlay').style.display = 'none';
  document.getElementById('busca_input').value = '';
  document.getElementById('busca_resultados').innerHTML = '';
}
let buscaTimer = null;
function buscarGlobal(q) {
  clearTimeout(buscaTimer);
  const box = document.getElementById('busca_resultados');
  if (q.trim().length < 2) { box.innerHTML = ''; return; }
  buscaTimer = setTimeout(async () => {
    box.innerHTML = '<div style="padding:16px; text-align:center; color:var(--txt-2);">Buscando...</div>';
    try {
      const r = await fetch('<?= e(APP_BASE_URL) ?>/busca.php?q=' + encodeURIComponent(q));
      const d = await r.json();
      if (!d.ok || !d.resultados.length) {
        box.innerHTML = '<div style="padding:24px; text-align:center; color:var(--txt-2);">Nada encontrado.</div>';
        return;
      }
      box.innerHTML = d.resultados.map(it => `
        <a href="${it.href}" style="display:flex; gap:12px; padding:12px; text-decoration:none; color:var(--txt-1); border-radius:6px; align-items:center; border-bottom:1px solid var(--border);">
          <span style="font-size:24px;">${it.icone}</span>
          <span style="flex:1;">
            <div style="font-weight:600;">${it.titulo}</div>
            <div style="font-size:12px; color:var(--txt-2);">${it.sub}</div>
          </span>
          <span class="status status-info" style="font-size:10px;">${it.tipo}</span>
        </a>
      `).join('');
    } catch (e) {
      box.innerHTML = '<div style="padding:16px; color:var(--c-danger);">Erro: ' + e.message + '</div>';
    }
  }, 250);
}
// Atalho Ctrl+K / Cmd+K em qualquer tela
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); abrirBusca(); }
  if (e.key === 'Escape' && document.getElementById('busca_overlay').style.display === 'block') fecharBusca();
});
</script>
<?php endif; ?>
<main class="container<?= $u && !$hide_nav ? '' : ' no-bottom-nav' ?>">
