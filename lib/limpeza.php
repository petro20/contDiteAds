<?php
declare(strict_types=1);

/**
 * Funções de limpeza/manutenção do banco.
 *
 * Cada função retorna um array com:
 *   ['tabela' => '...', 'apagadas' => N, 'motivo' => '...']
 *
 * Não dispara erro se a tabela não existir (try/catch silencioso) — útil
 * pra ambientes onde migrations ainda não foram aplicadas.
 */

require_once __DIR__ . '/../includes/db.php';

/**
 * Executa todas as limpezas mensais e retorna o relatório.
 */
function limpeza_executar(PDO $db): array {
    $log = [
        'inicio'   => date('Y-m-d H:i:s'),
        'detalhes' => [],
        'apagadas_total' => 0,
        'erros' => 0,
    ];

    // 1. Audit log > 18 meses (compliance: mantém ano + meio)
    $log['detalhes'][] = limpeza_runner($db, 'audit_log', 'criado_em', '18 MONTH',
        'Logs de auditoria mais antigos que 18 meses');

    // 2. Eventos Wise já processados > 6 meses (mantém recentes pra diagnóstico)
    $log['detalhes'][] = limpeza_runner($db, 'wise_eventos', 'recebido_em', '6 MONTH',
        "Eventos Wise > 6 meses (status casado/sem_cobranca)",
        "status IN ('casado','sem_cobranca')");

    // 3. Régua eventos > 1 ano
    $log['detalhes'][] = limpeza_runner($db, 'regua_eventos', 'criado_em', '12 MONTH',
        'Eventos de régua de cobrança > 1 ano');

    // 4. Resets de senha já usados > 30 dias (tokens consumidos)
    $log['detalhes'][] = limpeza_runner($db, 'senha_resets', 'usado_em', '30 DAY',
        'Tokens de reset de senha já consumidos > 30 dias',
        'usado_em IS NOT NULL');

    // 5. Backup codes 2FA já usados > 90 dias
    $log['detalhes'][] = limpeza_runner($db, 'totp_backup_codes', 'usado_em', '90 DAY',
        'Backup codes 2FA já consumidos > 90 dias',
        'usado_em IS NOT NULL');

    // 6. Convites usados > 30 dias
    $log['detalhes'][] = limpeza_runner($db, 'convites', 'usado_em', '30 DAY',
        'Convites já aceitos > 30 dias',
        'usado_em IS NOT NULL');

    // Soma totais
    foreach ($log['detalhes'] as $d) {
        $log['apagadas_total'] += (int)($d['apagadas'] ?? 0);
        if (!empty($d['erro'])) $log['erros']++;
    }

    // 7. OPTIMIZE TABLE nas tabelas mais ativas — recupera espaço em disco
    // (MySQL não libera espaço de DELETE automaticamente em InnoDB)
    $tabelas_otimizar = ['audit_log', 'wise_eventos', 'regua_eventos', 'senha_resets'];
    $log['otimizadas'] = [];
    foreach ($tabelas_otimizar as $t) {
        try {
            $db->exec("OPTIMIZE TABLE `$t`");
            $log['otimizadas'][] = $t;
        } catch (PDOException $e) {
            // Tabela pode não existir ou usuário não tem permissão — ignora
        }
    }

    $log['fim'] = date('Y-m-d H:i:s');
    return $log;
}

/**
 * Executa um DELETE de manutenção com contagem antes/depois.
 * @internal
 */
function limpeza_runner(PDO $db, string $tabela, string $coluna_data, string $intervalo, string $motivo, string $where_extra = ''): array {
    $resultado = [
        'tabela'    => $tabela,
        'apagadas'  => 0,
        'motivo'    => $motivo,
        'corte'     => "$coluna_data < DATE_SUB(NOW(), INTERVAL $intervalo)",
    ];
    try {
        $where = "$coluna_data < DATE_SUB(NOW(), INTERVAL $intervalo)";
        if ($where_extra) $where .= " AND $where_extra";

        // Conta antes (pra log)
        $stmt = $db->query("SELECT COUNT(*) FROM `$tabela` WHERE $where");
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) {
            $db->exec("DELETE FROM `$tabela` WHERE $where");
        }
        $resultado['apagadas'] = $count;
    } catch (PDOException $e) {
        $resultado['erro'] = $e->getMessage();
    }
    return $resultado;
}

/**
 * Formata o log de limpeza pra texto humano-legível (usado por cron e UI).
 */
function limpeza_log_to_text(array $log): string {
    $out = "[" . $log['inicio'] . "] Limpeza mensal iniciada\n\n";
    foreach ($log['detalhes'] as $d) {
        if (!empty($d['erro'])) {
            $out .= " · {$d['tabela']}: ERRO — {$d['erro']}\n";
        } else {
            $n = (int)$d['apagadas'];
            $emoji = $n > 0 ? '🗑' : '✓';
            $out .= " · {$d['tabela']}: $emoji $n linha(s) apagada(s) — {$d['motivo']}\n";
        }
    }
    if (!empty($log['otimizadas'])) {
        $out .= "\n📦 OPTIMIZE TABLE em: " . implode(', ', $log['otimizadas']) . "\n";
    }
    $out .= "\n[" . ($log['fim'] ?? '?') . "] TOTAL: " . $log['apagadas_total'] . " linha(s) apagada(s), " . $log['erros'] . " erro(s)\n";
    return $out;
}
