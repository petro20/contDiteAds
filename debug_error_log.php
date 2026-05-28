<?php
// Endpoint TEMPORÁRIO de debug — apaga depois!
// Mostra as últimas linhas do error_log do servidor.
require_once __DIR__ . '/includes/auth.php';
require_sadmin();

header('Content-Type: text/plain; charset=utf-8');

$candidatos = [
    __DIR__ . '/error_log',
    __DIR__ . '/../error_log',
    '/home/' . get_current_user() . '/error_log',
    ini_get('error_log'),
];

foreach ($candidatos as $f) {
    if ($f && is_file($f) && is_readable($f)) {
        echo "=== $f ===\n\n";
        $linhas = @file($f);
        if ($linhas) {
            $ultimas = array_slice($linhas, -80);
            echo implode('', $ultimas);
        }
        exit;
    }
}

echo "Nenhum error_log encontrado nos caminhos testados.\n\n";
echo "Caminhos verificados:\n";
foreach ($candidatos as $f) echo " - " . ($f ?: '(vazio)') . "\n";
echo "\nphp ini_get('error_log'): " . ini_get('error_log') . "\n";
echo "phpinfo error_log section (parcial):\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
