<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/money.php';

/**
 * Distribuição de lucro entre sócios (sadmin + admin) + empresa.
 *
 * Regras:
 *  - Cada cliente paga em sua moeda; o "lucro" fica na moeda da cobrança (sem conversão).
 *  - Quotas iguais: cada sócio ativo (sadmin/admin) tem 1 quota; "empresa" tem mais 1 quota.
 *  - N = quantidade de sócios ativos; total de quotas = N + 1 (a +1 é empresa).
 *  - Parte de cada sócio = receita_moeda / (N+1).
 *  - Funcionários continuam recebendo em USD à parte (fora desta distribuição).
 */

function socios_ativos(PDO $db): array {
    return $db->query("SELECT id, nome, role FROM usuarios WHERE ativo = 1 AND role IN ('sadmin','admin') ORDER BY FIELD(role,'sadmin','admin'), nome")->fetchAll();
}

function quotas_total(PDO $db): int {
    return count(socios_ativos($db)) + 1; // +1 = empresa
}

function receita_por_moeda(PDO $db, ?string $de = null, ?string $ate = null): array {
    $sql = "SELECT c.moeda, COALESCE(SUM(p.valor_pago), 0) AS total
            FROM pagamentos_cliente p
            JOIN cobrancas c ON c.id = p.cobranca_id
            WHERE 1=1";
    $params = [];
    if ($de)  { $sql .= ' AND p.data_pagamento >= ?'; $params[] = $de; }
    if ($ate) { $sql .= ' AND p.data_pagamento <= ?'; $params[] = $ate; }
    $sql .= ' GROUP BY c.moeda';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $r = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach ($stmt->fetchAll() as $row) $r[$row['moeda']] = (float)$row['total'];
    return $r;
}

/**
 * Receita do mês corrente.
 */
function receita_mes(PDO $db, ?string $competencia = null): array {
    $competencia = $competencia ?: date('Y-m');
    $ini = $competencia . '-01';
    $fim = date('Y-m-t', strtotime($ini));
    return receita_por_moeda($db, $ini, $fim);
}

/**
 * Saldo ACUMULADO não distribuído até o FIM do mês anterior ao competência dado,
 * por moeda (COM SINAL: negativo = distribuiu a mais no passado, desconta agora).
 *
 * saldo[m] = lucro_acumulado(≤ mês anterior)[m] − distribuído_acumulado(competência ≤ mês anterior)[m]
 *   - lucro_acumulado = receita − despesas − pagamentos a funcionários (USD), tudo somado até o mês anterior
 *   - distribuído_acumulado = pagamentos_socio com competência até o mês anterior
 *
 * É ACUMULADO (não só o mês anterior isolado) de propósito: assim o saldo "rola"
 * corretamente mesmo quando um mês distribui o que veio de trás. Fonte única de
 * verdade, usada tanto na trava de pagamento quanto na exibição.
 */
function saldo_distribuicao_mes_anterior(PDO $db, string $competencia): array {
    $prev_mes = date('Y-m', strtotime($competencia . '-01 -1 month'));
    $prev_end = date('Y-m-t', strtotime($prev_mes . '-01'));

    // Receita acumulada até o fim do mês anterior (por data de pagamento)
    $rec_ac = receita_por_moeda($db, null, $prev_end);

    // Pagamentos a funcionários acumulados até o fim do mês anterior (USD)
    $pf_ac = 0.0;
    try {
        $st = $db->prepare("SELECT COALESCE(SUM(valor_usd),0) FROM pagamentos_funcionario WHERE data_pagamento <= ?");
        $st->execute([$prev_end]);
        $pf_ac = (float)$st->fetchColumn();
    } catch (PDOException $e) {}

    // Despesas acumuladas até o mês anterior (por moeda) — soma mês a mês desde a 1ª despesa
    // (despesas_do_mes já resolve recorrência mensal/anual/única).
    $desp_ac = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    $ini_desp = null;
    try { $ini_desp = $db->query("SELECT MIN(data_inicio) FROM despesas")->fetchColumn(); } catch (PDOException $e) {}
    if ($ini_desp && function_exists('despesas_do_mes')) {
        $cur = date('Y-m', strtotime((string)$ini_desp));
        for ($i = 0; $i < 120 && $cur <= $prev_mes; $i++) {
            $dm = despesas_do_mes($db, $cur);
            foreach (['BRL','USD','EUR'] as $m) $desp_ac[$m] += (float)($dm['totais'][$m] ?? 0);
            $cur = date('Y-m', strtotime($cur . '-01 +1 month'));
        }
    }

    // Distribuído acumulado (competência ≤ mês anterior), por moeda
    $dist_ac = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    try {
        $st = $db->prepare("SELECT moeda, COALESCE(SUM(valor),0) AS t FROM pagamentos_socio WHERE competencia_mes <= ? GROUP BY moeda");
        $st->execute([$prev_mes]);
        foreach ($st->fetchAll() as $r) $dist_ac[$r['moeda']] = (float)$r['t'];
    } catch (PDOException $e) {}

    $saldo = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    foreach (['BRL','USD','EUR'] as $m) {
        $lucro_ac = (float)$rec_ac[$m] - $desp_ac[$m];
        if ($m === 'USD') $lucro_ac -= $pf_ac;
        $saldo[$m] = round($lucro_ac - $dist_ac[$m], 2);
    }
    return $saldo;
}

/**
 * Cobranças pagas recentes (para histórico).
 */
function cobrancas_pagas_recentes(PDO $db, int $limit = 30): array {
    $sql = "SELECT c.id, c.valor_total, c.moeda, c.competencia_mes,
                   cl.nome_empresa,
                   (SELECT MAX(p.data_pagamento) FROM pagamentos_cliente p WHERE p.cobranca_id = c.id) AS data_quitacao,
                   -- Quanto já foi pago à equipe pelos itens DESTA cobrança (sempre em USD)
                   (SELECT COALESCE(SUM(pfi.subtotal_usd), 0)
                      FROM pagamento_funcionario_itens pfi
                      JOIN cobranca_itens ci2 ON ci2.id = pfi.cobranca_item_id
                     WHERE ci2.cobranca_id = c.id) AS pago_equipe_usd
            FROM cobrancas c
            JOIN clientes cl ON cl.id = c.cliente_id
            WHERE c.status = 'paga'
            ORDER BY data_quitacao DESC LIMIT $limit";
    return $db->query($sql)->fetchAll();
}
