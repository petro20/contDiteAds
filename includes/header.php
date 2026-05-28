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
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0A0A0F">
<title><?= e($page) ?> · controle e gestão Dite Ads</title>
<link rel="icon" type="image/png" href="<?= e(APP_BASE_URL) ?>/assets/img/logo.png">
<link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=<?= e(@filemtime(__DIR__ . '/../assets/css/style.css') ?: '1') ?>">
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
      <span class="topbar-title">Controle Gerencial</span>
    <?php endif; ?>
  </div>
  <div class="actions">
    <button type="button" onclick="abrirBusca()" aria-label="Buscar" title="Buscar (Ctrl+K)" style="font-size:18px;">🔍</button>
    <a href="<?= e(APP_BASE_URL) ?>/perfil.php" aria-label="Perfil">👤</a>
  </div>
</header>

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
