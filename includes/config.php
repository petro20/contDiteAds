<?php
declare(strict_types=1);

function load_env(string $path): void {
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

load_env(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_BASE_URL', rtrim(getenv('APP_BASE_URL') ?: '', '/'));

// OneSignal (push notifications). App ID é público (vai no JS do cliente).
// REST API Key é SECRETA (só pra enviar do servidor) — manter no .env, nunca no repo.
// Enquanto ONESIGNAL_APP_ID estiver vazio, o push fica desligado.
define('ONESIGNAL_APP_ID',  getenv('ONESIGNAL_APP_ID')  ?: '73f3f9f0-4e28-4aad-b85f-b8b675af448c');
define('ONESIGNAL_REST_KEY', getenv('ONESIGNAL_REST_KEY') ?: '');

// Dite Gateway (pay.diteads.com) — processa cartão/transferência e confirma por webhook.
// API_KEY e WEBHOOK_SECRET são SECRETAS: só no .env do servidor, nunca no repo.
// Enquanto DITE_API_KEY/DITE_WEBHOOK_SECRET estiverem vazias, a integração fica desligada.
define('DITE_BASE_URL',       rtrim(getenv('DITE_BASE_URL') ?: 'https://pay.diteads.com', '/'));
define('DITE_API_KEY',        getenv('DITE_API_KEY')        ?: '');
define('DITE_WEBHOOK_SECRET', getenv('DITE_WEBHOOK_SECRET') ?: '');

define('SMTP_HOST',       getenv('SMTP_HOST')       ?: 'smtp.hostinger.com');
define('SMTP_PORT',       (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_SECURE',     getenv('SMTP_SECURE')     ?: 'ssl');
define('SMTP_USER',       getenv('SMTP_USER')       ?: '');
define('SMTP_PASS',       getenv('SMTP_PASS')       ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'contact@diteads.com');
define('SMTP_FROM_NAME',  getenv('SMTP_FROM_NAME')  ?: 'Dite Ads');

define('UPLOAD_DIR', __DIR__ . '/../' . (getenv('UPLOAD_DIR') ?: 'uploads'));

if (APP_ENV === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('CONTDITEADS');
    session_start();
}
