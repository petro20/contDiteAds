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

/**
 * HTTP GET com cURL (preferido) ou file_get_contents (fallback).
 * Retorna corpo da resposta ou null em caso de erro.
 */
function cotacao_http_get(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Dite Ads Cotacao/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code < 200 || $code >= 300) return null;
        return (string)$resp;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $resp = @file_get_contents($url, false, $ctx);
        return $resp === false ? null : $resp;
    }
    return null;
}

function cotacao_buscar_remoto(): ?array {
    // Tenta primeiro AwesomeAPI (Brasil, sem chave, tempo real)
    $resp = cotacao_http_get('https://economia.awesomeapi.com.br/json/last/USD-BRL,USD-EUR');
    if ($resp !== null) {
        $data = json_decode($resp, true);
        if ($data && isset($data['USDBRL']['bid'], $data['USDEUR']['bid'])) {
            return ['BRL' => (float)$data['USDBRL']['bid'], 'EUR' => (float)$data['USDEUR']['bid']];
        }
    }
    // Fallback: Frankfurter (ECB, sem chave, gratuita)
    $resp = cotacao_http_get('https://api.frankfurter.dev/v1/latest?base=USD&symbols=BRL,EUR');
    if ($resp !== null) {
        $data = json_decode($resp, true);
        if ($data && isset($data['rates']['BRL'], $data['rates']['EUR'])) {
            return ['BRL' => (float)$data['rates']['BRL'], 'EUR' => (float)$data['rates']['EUR']];
        }
    }
    return null;
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
