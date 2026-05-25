<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/money.php';

/**
 * Cálculo do impacto das despesas num mês específico.
 *
 * Regras:
 *  - recorrencia=unica: conta só no mês de data_inicio
 *  - recorrencia=mensal: conta todo mês entre data_inicio e data_fim (data_fim NULL = pra sempre)
 *  - recorrencia=anual: conta só nos meses que batem com o aniversário de data_inicio
 *  - despesa inativa não conta
 */
function despesas_do_mes(PDO $db, string $competencia): array {
    // YYYY-MM
    $stmt = $db->prepare("SELECT * FROM despesas WHERE ativo = 1 AND data_inicio <= ?");
    $fim_mes = date('Y-m-t', strtotime($competencia . '-01'));
    $stmt->execute([$fim_mes]);
    $linhas = $stmt->fetchAll();

    $total = ['BRL'=>0.0,'USD'=>0.0,'EUR'=>0.0];
    $detalhes = [];
    $comp_dt = DateTimeImmutable::createFromFormat('Y-m-d', $competencia . '-01');
    $ano_comp = (int)$comp_dt->format('Y');
    $mes_comp = (int)$comp_dt->format('n');

    foreach ($linhas as $d) {
        // Verifica data_fim
        if ($d['data_fim'] && strtotime($d['data_fim']) < strtotime($competencia . '-01')) continue;

        $inicio = new DateTimeImmutable($d['data_inicio']);
        $ano_ini = (int)$inicio->format('Y');
        $mes_ini = (int)$inicio->format('n');

        $conta = false;
        if ($d['recorrencia'] === 'unica') {
            $conta = ($ano_ini === $ano_comp && $mes_ini === $mes_comp);
        } elseif ($d['recorrencia'] === 'mensal') {
            $conta = true; // já filtrou que está dentro do range
        } elseif ($d['recorrencia'] === 'anual') {
            $conta = ($mes_ini === $mes_comp && $ano_comp >= $ano_ini);
        }

        if ($conta) {
            $total[$d['moeda']] += (float)$d['valor'];
            $detalhes[] = $d;
        }
    }

    return ['totais' => $total, 'detalhes' => $detalhes];
}
