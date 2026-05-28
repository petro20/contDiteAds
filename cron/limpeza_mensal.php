<?php
/**
 * Cron mensal de limpeza/manutenção do banco.
 *
 * Configurar na Hostinger (hPanel → Avançado → Cron Jobs):
 *   Comando: php /home/u788472657/domains/cont.diteads.com/public_html/cron/limpeza_mensal.php
 *   Frequência: dia 1 de cada mês às 03:00 (0 3 1 * *)
 *
 * Apaga:
 *   - audit_log > 18 meses
 *   - wise_eventos (processados) > 6 meses
 *   - regua_eventos > 1 ano
 *   - senha_resets já usados > 30 dias
 *   - totp_backup_codes já usados > 90 dias
 *   - convites já aceitos > 30 dias
 *
 * Depois roda OPTIMIZE TABLE pra recuperar espaço de disco do MySQL.
 *
 * Pode ser chamado via HTTP da tela admin (limpeza.php) também.
 */

declare(strict_types=1);

$is_cli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/limpeza.php';
require_once __DIR__ . '/../lib/audit.php';

// Se HTTP, exige sadmin
if (!$is_cli) {
    require_once __DIR__ . '/../includes/auth.php';
    if (!is_sadmin()) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
}

// Lock pra impedir execuções concorrentes
$lockFile = sys_get_temp_dir() . '/contditeads_limpeza.lock';
$fpLock = @fopen($lockFile, 'c');
if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Outra limpeza já está rodando. Saindo.\n";
    exit(0);
}

try {
    $db = db();
    $log = limpeza_executar($db);
    echo limpeza_log_to_text($log);

    // Registra no audit_log pra histórico (sadmin pode ver depois em /auditoria.php)
    try {
        audit_log('limpeza.mensal', 'sistema', $log['apagadas_total']);
    } catch (Throwable $e) {}

} catch (Throwable $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    flock($fpLock, LOCK_UN); fclose($fpLock);
    exit(1);
}

flock($fpLock, LOCK_UN);
fclose($fpLock);
exit(0);
