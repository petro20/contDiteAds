<?php
declare(strict_types=1);

require_once __DIR__ . '/configuracoes.php';

/**
 * Cotações USD → BRL e USD → EUR com cache diário.
 *
 * - Fonte: AwesomeAPI (economia.awesomeapi.com.br) — gratuita, sem chave,
 *   atualiza em tempo real.
 * - Cache: lê config 'cotacao_usd_brl', 'cotacao_usd_eur', 'cotacao_data'.
 *   Se 'cotacao_data' for hoje, usa o cache. Senão busca da API e atualiza.
 * - Fallback: se API falhar, mantém o último valor conhecido.
 */

function cotacao_buscar_remoto(): ?array {
    $url = 'https://economia.awesomeapi.com.br/json/last/USD-BRL,USD-EUR';
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (!$data) return null;
    $brl = isset($data['USDBRL']['bid']) ? (float)$data['USDBRL']['bid'] : null;
    $eur = isset($data['USDEUR']['bid']) ? (float)$data['USDEUR']['bid'] : null;
    if (!$brl || !$eur) return null;
    return ['BRL' => $brl, 'EUR' => $eur];
}

/**
 * Retorna ['BRL'=>X, 'EUR'=>Y, 'data'=>'YYYY-MM-DD', 'fonte'=>'api|cache|fallback'].
 * Sempre tenta usar cache do dia. Se cache expirado, busca remoto. Se falhar, fallback.
 */
function cotacao_atual(PDO $db, bool $forcar_refresh = false): array {
    $hoje = date('Y-m-d');
    $brl  = (float)config_get($db, 'cotacao_usd_brl', '0');
    $eur  = (float)config_get($db, 'cotacao_usd_eur', '0');
    $data = config_get($db, 'cotacao_data', '');

    if (!$forcar_refresh && $data === $hoje && $brl > 0 && $eur > 0) {
        return ['BRL' => $brl, 'EUR' => $eur, 'data' => $data, 'fonte' => 'cache'];
    }

    $remoto = cotacao_buscar_remoto();
    if ($remoto !== null) {
        config_set($db, 'cotacao_usd_brl', (string)$remoto['BRL']);
        config_set($db, 'cotacao_usd_eur', (string)$remoto['EUR']);
        config_set($db, 'cotacao_data', $hoje);
        return ['BRL' => $remoto['BRL'], 'EUR' => $remoto['EUR'], 'data' => $hoje, 'fonte' => 'api'];
    }

    // Fallback: usa último conhecido (mesmo que defasado)
    if ($brl > 0 && $eur > 0) {
        return ['BRL' => $brl, 'EUR' => $eur, 'data' => $data ?: '(?)', 'fonte' => 'fallback'];
    }
    // Último recurso: valores neutros
    return ['BRL' => 1.0, 'EUR' => 1.0, 'data' => '(?)', 'fonte' => 'erro'];
}

/**
 * Converte valor em USD para outra moeda. Se moeda já for USD, retorna o valor.
 */
function usd_para(PDO $db, float $valor_usd, string $moeda): float {
    $moeda = strtoupper($moeda);
    if ($moeda === 'USD') return $valor_usd;
    $c = cotacao_atual($db);
    if (!isset($c[$moeda]) || $c[$moeda] <= 0) return $valor_usd; // fallback: não converte
    return round($valor_usd * $c[$moeda], 2);
}
