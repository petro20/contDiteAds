<?php
/**
 * Cron diário — gera cobranças consolidadas para clientes cujo dia_cobranca == hoje.
 *
 * Configurar na Hostinger (hPanel → Avançado → Cron Jobs):
 *   Comando: php /home/u788472657/domains/cont.diteads.com/public_html/cron/gerar_cobrancas.php
 *   Frequência: diária às 05:00 (0 5 * * *)
 *
 * Para rodar manualmente via CLI local (testando):
 *   php cron/gerar_cobrancas.php [YYYY-MM-DD]
 */

declare(strict_types=1);

// Aceita acesso só via CLI (seguro contra abuso HTTP)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só roda em CLI.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/cobrancas.php';

// Lock exclusivo: impede 2 invocações simultâneas (cron lento + nova invocação,
// ou admin disparando "gerar manual" enquanto cron roda). Sem isso podem
// acontecer deadlocks ou erros em race condition.
$lockFile = sys_get_temp_dir() . '/contditeads_gerar_cobr.lock';
$fpLock = fopen($lockFile, 'c');
if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Outra execução de gerar_cobrancas já está rodando. Saindo.\n";
    exit(0);
}

$dataArg = $argv[1] ?? null;
$hoje = $dataArg ? new DateTimeImmutable($dataArg) : new DateTimeImmutable('today');

echo "[" . date('Y-m-d H:i:s') . "] Gerando cobranças para " . $hoje->format('Y-m-d') . "\n";

$log = executar_geracao_diaria(db(), $hoje);

echo "Avaliados: {$log['avaliados']}\n";
echo "Criadas:   {$log['criadas']}\n";
echo "Puladas:   {$log['puladas']} (já existiam)\n";
echo "Vazias:    {$log['vazias']} (sem assinaturas)\n";
echo "Erros:     {$log['erros']}\n";

foreach ($log['detalhes'] as $d) {
    echo " - cliente {$d['cliente_id']}: {$d['status']}";
    if (!empty($d['cobranca_id'])) echo " (cobr #{$d['cobranca_id']})";
    if (!empty($d['mensagem']))    echo " — {$d['mensagem']}";
    echo "\n";
}

// Libera o lock (será liberado de qualquer forma ao fim do script, mas explícito é melhor)
flock($fpLock, LOCK_UN);
fclose($fpLock);

exit($log['erros'] > 0 ? 1 : 0);
