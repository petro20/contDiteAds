<?php
declare(strict_types=1);

require_once __DIR__ . '/configuracoes.php';

/**
 * Integração com Wise API.
 *
 * Endpoints (api.wise.com):
 *  - GET /v2/profiles                            → lista de profiles (BUSINESS/PERSONAL)
 *  - GET /v3/profiles/{id}/balances?types=STANDARD → lista de balances/moedas
 *  - GET /v3/profiles/{pid}/balance-statements/{bid}/statement.json?currency=USD&intervalStart=...&intervalEnd=...&type=COMPACT
 *                                                → extrato de transações
 *
 * Auth: header `Authorization: Bearer <api_key>`
 */

function wise_http_get(string $path, string $api_key): ?array {
    if (!function_exists('curl_init')) return null;
    $url = 'https://api.wise.com' . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_USERAGENT      => 'DiteAds-WiseSync/1.0',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code < 200 || $code >= 300) {
        return ['__error' => 'HTTP ' . $code . ' — ' . substr((string)$resp, 0, 300)];
    }
    $data = json_decode($resp, true);
    return is_array($data) ? $data : ['__error' => 'JSON inválido'];
}

/**
 * Busca transações CREDIT (recebimentos) de uma moeda específica no intervalo.
 * Retorna array de transações ou ['__error' => '...'] em caso de falha.
 */
function wise_buscar_creditos(PDO $db, string $moeda, string $de_iso, string $ate_iso): array {
    $key = config_get($db, 'wise_api_key');
    $pid = config_get($db, 'wise_profile_id');
    if (!$key) return ['__error' => 'API key da Wise não configurada.'];
    if (!$pid) return ['__error' => 'Profile ID da Wise não configurado.'];

    // Tenta v4 (atual) → fallback v3 (antigo) → fallback /v1/borderless-accounts
    $balances = wise_http_get('/v4/profiles/' . (int)$pid . '/balances?types=STANDARD', $key);
    if (isset($balances['__error'])) {
        // tenta v3 sem filtro
        $alt = wise_http_get('/v3/profiles/' . (int)$pid . '/balances', $key);
        if (!isset($alt['__error'])) $balances = $alt;
    }
    if (isset($balances['__error'])) {
        // tenta /v1/borderless-accounts (formato antigo)
        $alt = wise_http_get('/v1/borderless-accounts?profileId=' . (int)$pid, $key);
        if (!isset($alt['__error']) && is_array($alt) && !empty($alt[0]['balances'])) {
            // Converte formato v1 (borderless tem array de balances dentro)
            $conv = [];
            foreach ($alt[0]['balances'] as $b) {
                $conv[] = ['id' => $b['id'] ?? null, 'currency' => $b['currency'] ?? null];
            }
            $balances = $conv;
            $borderless_id = $alt[0]['id'] ?? null;
        } else {
            return ['__error' => 'Não foi possível listar balances. ' . ($balances['__error'] ?? '')];
        }
    }

    $balance_id = null;
    foreach ((array)$balances as $b) {
        if (isset($b['currency']) && strtoupper($b['currency']) === strtoupper($moeda)) {
            $balance_id = $b['id'] ?? null; break;
        }
    }
    if (!$balance_id) return ['__error' => "Balance em $moeda não encontrado na sua conta Wise. Verifique se você tem saldo nessa moeda."];

    // Tenta endpoint v1 de statement (mais estável)
    $path = sprintf(
        '/v1/profiles/%d/balance-statements/%d/statement.json?currency=%s&intervalStart=%s&intervalEnd=%s&type=COMPACT',
        (int)$pid, (int)$balance_id, urlencode($moeda),
        urlencode($de_iso), urlencode($ate_iso)
    );
    $res = wise_http_get($path, $key);
    if (isset($res['__error'])) {
        // fallback v3
        $alt_path = sprintf(
            '/v3/profiles/%d/balance-statements/%d/statement.json?currency=%s&intervalStart=%s&intervalEnd=%s&type=COMPACT',
            (int)$pid, (int)$balance_id, urlencode($moeda),
            urlencode($de_iso), urlencode($ate_iso)
        );
        $alt = wise_http_get($alt_path, $key);
        if (!isset($alt['__error'])) $res = $alt;
        else return $res; // mantém erro original
    }

    $txs = $res['transactions'] ?? [];
    // Filtra só CREDIT (entradas)
    return array_values(array_filter($txs, fn($t) => isset($t['type']) && strtoupper($t['type']) === 'CREDIT'));
}

/**
 * Lista os profiles disponíveis na conta Wise (pra ajudar sadmin a descobrir o ID).
 */
function wise_profiles(PDO $db): array {
    $key = config_get($db, 'wise_api_key');
    if (!$key) return [];
    $r = wise_http_get('/v2/profiles', $key);
    if (isset($r['__error'])) return $r;
    return is_array($r) ? $r : [];
}
