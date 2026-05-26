<?php
require_once __DIR__ . '/auth.php';
$u = current_user();
$page         = $page ?? 'contDiteAds';
$page_sub     = $page_sub     ?? null;
$show_back    = $show_back    ?? false;
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
    <a class="back-btn" href="<?= e($back_to ?? (APP_BASE_URL . '/dashboard.php')) ?>" aria-label="Voltar">←</a>
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
    <a href="<?= e(APP_BASE_URL) ?>/perfil.php" aria-label="Perfil">👤</a>
  </div>
</header>
<?php endif; ?>
<main class="container<?= $u && !$hide_nav ? '' : ' no-bottom-nav' ?>">
