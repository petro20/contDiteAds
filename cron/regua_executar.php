<?php
/**
 * Cron diário — executa a régua de cobrança.
 *
 * Hostinger Cron Jobs:
 *   Comando: php /home/u788472657/domains/cont.diteads.com/public_html/cron/regua_executar.php
 *   Frequência: diária às 06:00 (0 6 * * *)
 *
 * CLI local pra testar:
 *   php cron/regua_executar.php [YYYY-MM-DD]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só roda em CLI.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/regua.php';

$dataArg = $argv[1] ?? null;
$hoje = $dataArg ? new DateTimeImmutable($dataArg) : new DateTimeImmutable('today');

echo "[" . date('Y-m-d H:i:s') . "] Executando régua para " . $hoje->format('Y-m-d') . "\n";

$log = regua_executar(db(), $hoje);

echo "Cobranças avaliadas: {$log['cobrancas_avaliadas']}\n";
echo "Emails enviados:     {$log['emails_enviados']}\n";
echo "WhatsApp pendentes:  {$log['whatsapp_pendentes']}\n";
echo "Erros:               {$log['erros']}\n";

foreach ($log['detalhes'] as $d) {
    echo " - cobrança {$d['cobranca']} etapa {$d['etapa']}: {$d['erro']}\n";
}

exit($log['erros'] > 0 ? 1 : 0);
