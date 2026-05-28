<?php
/**
 * Backup diário do banco MySQL — PHP puro (não depende de mysqldump nem exec).
 *
 * Configurar na Hostinger (hPanel → Avançado → Cron Jobs):
 *   Comando: php /home/u788472657/domains/cont.diteads.com/public_html/cron/backup_db.php
 *   Frequência: diária às 04:00 (0 4 * * *)
 *
 * Pra testar manualmente via CLI ou HTTP:
 *   php cron/backup_db.php
 *
 * Estratégia (sem exec — funciona em shared hosting):
 *   - SHOW TABLES → CREATE TABLE → SELECT * + INSERT linha a linha
 *   - Comprime em tempo real com gzopen/gzwrite (sem RAM full)
 *   - Salva em uploads/.backups/db_YYYY-MM-DD.sql.gz
 *   - Mantém últimos 14 backups; mais antigos são apagados
 */

declare(strict_types=1);

// Detecta se foi chamado via CLI ou via HTTP (do botão "Gerar agora")
$is_cli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Se HTTP e não foi incluído de outra página (que já autenticou), exige sadmin.
if (!$is_cli) {
    require_once __DIR__ . '/../includes/auth.php';
    if (!is_sadmin()) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    // Só seta header se ainda não foi enviado (suporta include de backups.php)
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
}

// Lock pra impedir dois backups simultâneos
$lockFile = sys_get_temp_dir() . '/contditeads_backup.lock';
$fpLock = @fopen($lockFile, 'c');
if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Outro backup já está rodando. Saindo.\n";
    exit(0);
}

$dir = __DIR__ . '/../uploads/.backups';
if (!is_dir($dir)) {
    if (!@mkdir($dir, 0750, true)) {
        echo "Erro: não consegui criar $dir\n";
        flock($fpLock, LOCK_UN); fclose($fpLock);
        exit(1);
    }
}

// Garante .htaccess bloqueando acesso HTTP
$htaccess = $dir . '/.htaccess';
if (!is_file($htaccess)) {
    @file_put_contents($htaccess, "Require all denied\n");
}

$data_str = date('Y-m-d');
$arquivo = $dir . "/db_$data_str.sql.gz";

echo "[" . date('Y-m-d H:i:s') . "] Iniciando backup de " . DB_NAME . " → $arquivo\n";
flush();

try {
    $db = db();
    $gz = gzopen($arquivo . '.tmp', 'wb6'); // nível 6 = bom equilíbrio velocidade/tamanho
    if (!$gz) throw new RuntimeException('Não consegui abrir arquivo de saída pra escrita.');

    // Cabeçalho do dump
    gzwrite($gz, "-- contDiteAds backup\n");
    gzwrite($gz, "-- Database: " . DB_NAME . "\n");
    gzwrite($gz, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    gzwrite($gz, "-- PHP version: " . PHP_VERSION . "\n\n");
    gzwrite($gz, "SET NAMES utf8mb4;\n");
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 0;\n");
    gzwrite($gz, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    // 1. Lista todas as tabelas
    $tabelas = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $total_linhas = 0;

    foreach ($tabelas as $tab) {
        echo " · $tab ... ";
        flush();

        // 2. CREATE TABLE
        $stmt = $db->query("SHOW CREATE TABLE `$tab`");
        $row = $stmt->fetch();
        $create_sql = $row['Create Table'] ?? '';
        gzwrite($gz, "\n-- ---------------- $tab ----------------\n");
        gzwrite($gz, "DROP TABLE IF EXISTS `$tab`;\n");
        gzwrite($gz, $create_sql . ";\n\n");

        // 3. Linhas (batched pra evitar memória estourar com tabelas grandes)
        $count = (int)$db->query("SELECT COUNT(*) FROM `$tab`")->fetchColumn();
        if ($count > 0) {
            $batch_size = 500;
            $offset = 0;
            while ($offset < $count) {
                $stmt = $db->prepare("SELECT * FROM `$tab` LIMIT $batch_size OFFSET $offset");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols = array_map(fn($c) => "`$c`", array_keys($row));
                    $vals = [];
                    foreach ($row as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $db->quote((string)$v);
                        }
                    }
                    gzwrite($gz, "INSERT INTO `$tab` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
                }
                $offset += $batch_size;
            }
        }
        $total_linhas += $count;
        echo "$count linhas\n";
        flush();
    }

    gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
    gzclose($gz);

    // Move atomically
    rename($arquivo . '.tmp', $arquivo);

    $tamanho_kb = round(filesize($arquivo) / 1024, 1);
    echo "\nOK: " . count($tabelas) . " tabelas, $total_linhas linhas, $tamanho_kb KB comprimido\n";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    @unlink($arquivo . '.tmp');
    flock($fpLock, LOCK_UN); fclose($fpLock);
    exit(1);
}

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
