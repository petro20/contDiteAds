<?php
require_once __DIR__ . '/auth.php';
$u = current_user();
$page = $page ?? 'contDiteAds';
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page) ?> · contDiteAds</title>
<link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<?php if ($u): ?>
<nav class="topbar">
  <a class="brand" href="<?= e(APP_BASE_URL) ?>/dashboard.php">contDiteAds</a>
  <div class="nav-links">
    <a href="<?= e(APP_BASE_URL) ?>/dashboard.php">Início</a>
    <a href="<?= e(APP_BASE_URL) ?>/cobrancas.php">Cobranças</a>
    <?php if (is_admin()): ?>
      <a href="<?= e(APP_BASE_URL) ?>/clientes.php">Clientes</a>
      <a href="<?= e(APP_BASE_URL) ?>/funcionarios.php">Funcionários</a>
      <a href="<?= e(APP_BASE_URL) ?>/servicos.php">Serviços</a>
    <?php endif; ?>
  </div>
  <div class="user-box">
    <span><?= e($u['nome']) ?> · <?= e($u['role']) ?></span>
    <a href="<?= e(APP_BASE_URL) ?>/logout.php">Sair</a>
  </div>
</nav>
<?php endif; ?>
<main class="container">
