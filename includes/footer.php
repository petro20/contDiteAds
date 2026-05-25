</main>
<?php
$u = current_user();
if ($u && empty($hide_nav)):
    $nav_active = $nav_active ?? '';
    $base = APP_BASE_URL;

    if ($u['role'] === 'admin') {
        $items = [
            ['key'=>'painel',   'href'=>"$base/painel.php",    'label'=>'Painel',   'icon'=>'📊'],
            ['key'=>'clientes', 'href'=>"$base/clientes.php",  'label'=>'Clientes', 'icon'=>'👥'],
            ['key'=>'catalogo', 'href'=>"$base/catalogo.php",  'label'=>'Catálogo', 'icon'=>'📦'],
            ['key'=>'perfil',   'href'=>"$base/perfil.php",    'label'=>'Perfil',   'icon'=>'👤'],
        ];
    } elseif ($u['role'] === 'funcionario') {
        $items = [
            ['key'=>'agenda',     'href'=>"$base/agenda.php",            'label'=>'Agenda',   'icon'=>'📅'],
            ['key'=>'clientes',   'href'=>"$base/clientes.php",          'label'=>'Clientes', 'icon'=>'👥'],
            ['key'=>'pagamentos', 'href'=>"$base/meus_pagamentos.php",   'label'=>'Pagamentos','icon'=>'💵'],
            ['key'=>'perfil',     'href'=>"$base/perfil.php",            'label'=>'Perfil',   'icon'=>'👤'],
        ];
    } else { // cliente
        $items = [
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
</body>
</html>
