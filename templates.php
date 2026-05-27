<?php
// Templates de mensagem foram movidos para regua.php?aba=templates (Comunicação)
require_once __DIR__ . '/includes/auth.php';
require_sadmin();
$id = (int)($_GET['id'] ?? 0);
$novo = isset($_GET['novo']);
$dest = APP_BASE_URL . '/regua.php?aba=templates';
if ($id) $dest .= '&edit_tpl=' . $id;
elseif ($novo) $dest .= '&novo_tpl=1';
header('Location: ' . $dest, true, 301); exit;
