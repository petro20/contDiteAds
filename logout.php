<?php
require_once __DIR__ . '/includes/auth.php';
logout();
header('Location: ' . APP_BASE_URL . '/login.php');
exit;
