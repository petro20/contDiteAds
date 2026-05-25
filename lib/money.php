<?php
declare(strict_types=1);

/**
 * Helpers de formatação monetária.
 * Sempre passa pelo ENUM ('USD','BRL','EUR'). Sem conversão entre moedas.
 */

function money_simbolo(string $moeda): string {
    return match (strtoupper($moeda)) {
        'USD' => '$',
        'BRL' => 'R$',
        'EUR' => '€',
        default => $moeda . ' ',
    };
}

/**
 * Formata um valor numérico para exibição na moeda.
 * Ex: money_fmt(1234.5, 'BRL') => "R$ 1.234,50"
 *     money_fmt(1234.5, 'USD') => "$1,234.50"
 */
function money_fmt(?float $valor, string $moeda): string {
    if ($valor === null) return '—';
    $m = strtoupper($moeda);
    if ($m === 'BRL') {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
    if ($m === 'USD') {
        return '$' . number_format($valor, 2, '.', ',');
    }
    if ($m === 'EUR') {
        return '€' . number_format($valor, 2, ',', '.');
    }
    return money_simbolo($m) . number_format($valor, 2, '.', ',');
}

/**
 * Pega o preço do item na moeda do cliente.
 * Retorna NULL se o item não tem preço cadastrado naquela moeda
 * (admin deve usar override por cliente).
 *
 * @param array $item Linha de itens_catalogo
 * @param string $moeda 'USD'|'BRL'|'EUR'
 * @param bool $variante_ia Se o cliente escolheu variante com IA
 */
function preco_catalogo(array $item, string $moeda, bool $variante_ia = false): ?float {
    $m = strtolower($moeda);
    $col = $variante_ia && (int)$item['tem_variante_ia'] === 1
        ? "preco_ia_$m"
        : "preco_$m";
    $v = $item[$col] ?? null;
    return $v === null ? null : (float)$v;
}

/**
 * Lista de moedas suportadas (em ordem fixa para UIs).
 */
function moedas_suportadas(): array {
    return ['BRL', 'USD', 'EUR'];
}
