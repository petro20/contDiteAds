<?php
/**
 * Cron: avisa funcionários que ainda não marcaram entregas de POSTAGEM
 * nesta semana (segunda → hoje).
 *
 * Configurar na Hostinger (hPanel → Avançado → Cron Jobs):
 *   Comando:    php /home/u788472657/domains/cont.diteads.com/public_html/cron/alerta_postagens.php
 *   Frequência: quarta e sexta às 09:00 — cron expression "0 9 * * 3,5"
 *
 * Pode rodar manual via CLI ou HTTP (sadmin):
 *   php cron/alerta_postagens.php           # envia emails de verdade
 *   php cron/alerta_postagens.php --dry-run # só lista quem seria notificado
 */

declare(strict_types=1);

$is_cli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/alertas.php';
require_once __DIR__ . '/../lib/email.php';
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

// Modo dry-run: detecta mas não envia (útil pra testar)
$dry_run = $is_cli
    ? in_array('--dry-run', $argv ?? [], true)
    : isset($_GET['dry_run']);

// Lock
$lockFile = sys_get_temp_dir() . '/contditeads_alerta_postagens.lock';
$fpLock = @fopen($lockFile, 'c');
if (!$fpLock || !flock($fpLock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Outro alerta já está rodando. Saindo.\n";
    exit(0);
}

$hoje = new DateTimeImmutable('today');
$dow = (int)$hoje->format('N'); // 1=seg ... 7=dom

echo "[" . date('Y-m-d H:i:s') . "] Alerta de postagens · hoje=" . $hoje->format('Y-m-d') . " (dia $dow)";
echo $dry_run ? " [DRY-RUN]\n" : "\n";

try {
    $db = db();
    $pendentes = alertas_postagens_pendentes($db, $hoje);
    $total = count($pendentes);

    if ($total === 0) {
        echo "✓ Nenhum funcionário com POSTAGEM pendente esta semana. Time tá em dia!\n";
        flock($fpLock, LOCK_UN); fclose($fpLock);
        exit(0);
    }

    echo "Detectados $total funcionário(s) com pendência:\n\n";

    $enviados = 0;
    $falhas = 0;
    foreach ($pendentes as $p) {
        $n_assin = count($p['assinaturas']);
        echo " · {$p['nome']} <{$p['email']}> — $n_assin assinatura(s) sem marcação:\n";
        foreach ($p['assinaturas'] as $a) {
            echo "     - {$a['cliente_nome']} ({$a['item_nome']})\n";
        }

        if ($dry_run) continue;

        // Envia o email
        $assunto = sprintf('🔔 Lembrete: %d assinatura(s) de POSTAGEM sem marcação esta semana', $n_assin);
        $html = alertas_email_corpo($p, $hoje);
        $r = email_enviar($p['email'], $assunto, $html);

        if ($r === true) {
            $enviados++;
            echo "   ✓ Email enviado\n";
        } else {
            $falhas++;
            echo "   ✗ Falha: " . (string)$r . "\n";
        }
    }

    if (!$dry_run) {
        echo "\nTOTAL: $enviados enviado(s), $falhas falha(s)\n";
        try {
            audit_log('alerta.postagens_pendentes', 'sistema', $enviados);
        } catch (Throwable $e) {}
    }

} catch (Throwable $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    flock($fpLock, LOCK_UN); fclose($fpLock);
    exit(1);
}

flock($fpLock, LOCK_UN);
fclose($fpLock);
exit(0);
