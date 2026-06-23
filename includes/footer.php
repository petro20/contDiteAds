</main>
<?php
$u = current_user();
if ($u && empty($hide_nav)):
    $nav_active = $nav_active ?? '';
    $base = APP_BASE_URL;

    if (in_array($u['role'], ['admin','sadmin'], true)) {
        $items = [
            ['key'=>'painel',   'href'=>"$base/dashboard.php", 'label'=>'Início',   'icon'=>'🏠'],
            ['key'=>'clientes', 'href'=>"$base/clientes.php",  'label'=>'Clientes', 'icon'=>'👥'],
        ];
        if ($u['role'] === 'sadmin') {
            $items[] = ['key'=>'catalogo', 'href'=>"$base/catalogo.php", 'label'=>'Catálogo', 'icon'=>'📦'];
        } else {
            $items[] = ['key'=>'painel_fin', 'href'=>"$base/painel.php", 'label'=>'Painel', 'icon'=>'📊'];
        }
        $items[] = ['key'=>'perfil', 'href'=>"$base/perfil.php", 'label'=>'Perfil', 'icon'=>'👤'];
    } elseif ($u['role'] === 'funcionario') {
        $items = [
            ['key'=>'inicio',     'href'=>"$base/dashboard.php",         'label'=>'Início',   'icon'=>'🏠'],
            ['key'=>'agenda',     'href'=>"$base/agenda.php",            'label'=>'Agenda',   'icon'=>'📅'],
            ['key'=>'pagamentos', 'href'=>"$base/meus_pagamentos.php",   'label'=>'Pagamentos','icon'=>'💵'],
            ['key'=>'perfil',     'href'=>"$base/perfil.php",            'label'=>'Perfil',   'icon'=>'👤'],
        ];
    } else { // cliente
        $items = [
            ['key'=>'inicio',    'href'=>"$base/dashboard.php", 'label'=>'Início',   'icon'=>'🏠'],
            ['key'=>'cobrancas', 'href'=>"$base/cobrancas.php", 'label'=>'Cobranças','icon'=>'💳'],
            ['key'=>'entregas',  'href'=>"$base/entregas.php",  'label'=>'Entregas', 'icon'=>'✅'],
            ['key'=>'perfil',    'href'=>"$base/perfil.php",    'label'=>'Perfil',   'icon'=>'👤'],
        ];
    }
?>
<nav class="bottom-nav">
  <?php foreach ($items as $it): ?>
    <a href="<?= e($it['href']) ?>" class="<?= $nav_active === $it['key'] ? 'active' : '' ?>">
      <span class="icon"><?= $it['icon'] ?></span>
      <span><?= e($it['label']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ($u): ?>
<!-- Banner "Instalar app" (PWA). Aparece só quando dá pra instalar e ainda não foi dispensado. -->
<div id="pwa_install" hidden>
  <span class="pwa-ico">📲</span>
  <span class="pwa-txt"><b>Instalar o app Dite Ads</b><small>Acesso rápido pela tela inicial</small></span>
  <button type="button" class="btn btn-brand small" id="pwa_install_btn">Instalar</button>
  <button type="button" class="pwa-x" id="pwa_install_close" aria-label="Dispensar">✕</button>
</div>
<div id="pwa_ios_help" hidden>
  📲 No iPhone: toque em <strong>Compartilhar</strong> (quadradinho com a setinha ↑) na barra do Safari e depois em <strong>"Adicionar à Tela de Início"</strong>.
  <div style="text-align:right; margin-top:10px;"><button type="button" class="btn btn-ghost small" id="pwa_ios_ok">Entendi</button></div>
</div>
<script>
(function () {
  var KEY = 'pwa_install_dismissed';
  var banner = document.getElementById('pwa_install');
  var iosHelp = document.getElementById('pwa_ios_help');
  if (!banner) return;
  var deferred = null;

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }
  function dismissed() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
  function esconde() { banner.hidden = true; }
  function naoMostrarMais() { esconde(); try { localStorage.setItem(KEY, '1'); } catch (e) {} }

  if (isStandalone() || dismissed()) return; // já instalado ou já dispensado

  // Android/Chrome/Edge: evento nativo de instalação
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    banner.hidden = false;
  });
  window.addEventListener('appinstalled', naoMostrarMais);

  // iPhone (Safari) não dispara beforeinstallprompt → mostra o banner que abre a dica manual
  var ua = navigator.userAgent || '';
  var isIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isSafari = /safari/i.test(ua) && !/crios|fxios|edgios|chrome/i.test(ua);
  if (isIOS && isSafari) banner.hidden = false;

  document.getElementById('pwa_install_btn').addEventListener('click', function () {
    if (deferred) {
      deferred.prompt();
      deferred.userChoice.then(function () { deferred = null; esconde(); });
    } else if (isIOS && iosHelp) {
      iosHelp.hidden = false;
      esconde();
    }
  });
  document.getElementById('pwa_install_close').addEventListener('click', naoMostrarMais);
  var iosOk = document.getElementById('pwa_ios_ok');
  if (iosOk) iosOk.addEventListener('click', function () { iosHelp.hidden = true; naoMostrarMais(); });
})();
</script>
<?php endif; ?>

<?php if ($u && defined('ONESIGNAL_APP_ID') && ONESIGNAL_APP_ID !== ''): ?>
<!-- Push notifications via OneSignal. Worker isolado em /push/onesignal/ pra não conflitar com o sw.js do PWA. -->
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function (OneSignal) {
    await OneSignal.init({
      appId: "<?= e(ONESIGNAL_APP_ID) ?>",
      serviceWorkerParam: { scope: "/push/onesignal/" },
      serviceWorkerPath: "push/onesignal/OneSignalSDKWorker.js"
    });
    // Vincula a inscrição ao usuário logado, pra poder enviar avisos direcionados.
    try { await OneSignal.login(String(<?= (int)$u['id'] ?>)); } catch (e) {}
    // Mostra o convite pra ativar notificações (OneSignal não repete se já respondido).
    try { OneSignal.Slidedown.promptPush(); } catch (e) {}
  });
</script>
<?php endif; ?>
</body>
</html>
