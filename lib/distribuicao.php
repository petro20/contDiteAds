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
 * Cobranças pagas recentes (para histórico).
 */
function cobrancas_pagas_recentes(PDO $db, int $limit = 30): array {
    $sql = "SELECT c.id, c.valor_total, c.moeda, c.competencia_mes,
                   cl.nome_empresa,
                   (SELECT MAX(p.data_pagamento) FROM pagamentos_cliente p WHERE p.cobranca_id = c.id) AS data_quitacao
            FROM cobrancas c
            JOIN clientes cl ON cl.id = c.cliente_id
            WHERE c.status = 'paga'
            ORDER BY data_quitacao DESC LIMIT $limit";
    return $db->query($sql)->fetchAll();
}
