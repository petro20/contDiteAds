<?php
/**
 * Backup diário do banco MySQL — comprimido, retenção de 14 dias.
 *
 * Configurar na Hostinger (hPanel → Avançado → Cron Jobs):
 *   Comando: php /home/u788472657/domains/cont.diteads.com/public_html/cron/backup_db.php
 *   Frequência: diária às 04:00 (0 4 * * *)
 *
 * Pra testar manualmente via CLI:
 *   php cron/backup_db.php
 *
 * Estratégia:
 *   - Usa mysqldump nativo (mais rápido e completo que PDO query-by-query)
 *   - Gzip aplicado pra reduzir tamanho (~80% economia em texto SQL)
 *   - Arquivos vão pra uploads/.backups/ (protegida via .htaccess)
 *   - Mantém últimos 14 backups; mais antigos são apagados automaticamente
 *   - Idempotente: re-execução no mesmo dia sobrescreve o arquivo daquele dia
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só roda em CLI.');
}

require_once __DIR__ . '/../includes/config.php';

// Lock pra impedir dois backups simultâneos (cron lento + manual)
$lockFile = sys_get_temp_dir() . '/contditeads_backup.lock';
$fpLock = fopen($lockFile, 'c');
if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Outro backup já está rodando. Saindo.\n";
    exit(0);
}

$dir = __DIR__ . '/../uploads/.backups';
if (!is_dir($dir)) {
    if (!@mkdir($dir, 0750, true)) {
        echo "Erro: não consegui criar $dir\n";
        exit(1);
    }
}

// Garante .htaccess bloqueando acesso HTTP
$htaccess = $dir . '/.htaccess';
if (!is_file($htaccess)) {
    file_put_contents($htaccess, "Require all denied\n");
}

$data_str = date('Y-m-d');
$arquivo = $dir . "/db_$data_str.sql.gz";

echo "[" . date('Y-m-d H:i:s') . "] Iniciando backup de " . DB_NAME . " → $arquivo\n";

// Comando mysqldump. --single-transaction garante consistência sem lock de tabela.
// --no-tablespaces evita erro de permissão em shared hosting.
// --routines / --triggers preservam stored procedures e triggers (se houver).
$cmd = sprintf(
    'mysqldump --no-tablespaces --single-transaction --quick --routines --triggers ' .
    '--host=%s --user=%s --password=%s %s 2>&1',
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME)
);

// Pipeline: mysqldump | gzip > arquivo
$dump_cmd = $cmd . ' | gzip -9 > ' . escapeshellarg($arquivo);
exec($dump_cmd, $output, $rc);

if ($rc !== 0 || !is_file($arquivo) || filesize($arquivo) < 1024) {
    echo "ERRO no mysqldump (exit=$rc):\n";
    echo implode("\n", $output) . "\n";
    if (is_file($arquivo)) @unlink($arquivo);
    flock($fpLock, LOCK_UN); fclose($fpLock);
    exit(1);
}

$tamanho_mb = round(filesize($arquivo) / 1024 / 1024, 2);
echo "OK: backup gerado, $tamanho_mb MB\n";

// Limpeza: mantém últimos 14 arquivos
$arquivos = glob($dir . '/db_*.sql.gz') ?: [];
sort($arquivos);
$total = count($arquivos);
$max = 14;
if ($total > $max) {
    $excedente = array_slice($arquivos, 0, $total - $max);
    foreach ($excedente as $f) {
        if (@unlink($f)) echo "Apagado antigo: " . basename($f) . "\n";
    }
}

echo "Backups mantidos: " . min($total, $max) . "/" . $max . "\n";

flock($fpLock, LOCK_UN);
fclose($fpLock);
exit(0);
