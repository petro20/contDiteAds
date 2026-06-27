<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

/**
 * Integração com o Dite Gateway (pay.diteads.com).
 * O gateway processa cartão/transferência e confirma por webhook —
 * este site NÃO processa cartão. Só cria o pagamento e recebe a confirmação.
 */

/** Integração está configurada (chaves presentes)? */
function dite_habilitado(): bool {
    return DITE_API_KEY !== '' && DITE_WEBHOOK_SECRET !== '';
}

/**
 * Verifica a assinatura HMAC-SHA256 do webhook (comparação timing-safe).
 * Header X-Dite-Signature = "sha256=" + hmac_sha256(corpo_bruto, WEBHOOK_SECRET).
 */
function dite_assinatura_valida(string $rawBody, string $signatureHeader): bool {
    if (DITE_WEBHOOK_SECRET === '' || $signatureHeader === '') return false;
    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, DITE_WEBHOOK_SECRET);
    return hash_equals($expected, $signatureHeader);
}

/**
 * POST autenticado na API do gateway. Retorna o JSON decodificado.
 * Lança RuntimeException em erro de conexão ou status != 2xx.
 */
function dite_api_post(string $path, array $body, ?string $idempotencyKey = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('cURL indisponível no servidor.');
    if (DITE_API_KEY === '') throw new RuntimeException('DITE_API_KEY não configurada.');

    $url = DITE_BASE_URL . $path;
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Api-Key: ' . DITE_API_KEY,
    ];
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new RuntimeException('Falha na conexão com o gateway: ' . $cerr);
    $data = json_decode((string)$resp, true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($data) ? ($data['error'] ?? $data['message'] ?? (string)$resp) : (string)$resp;
        throw new RuntimeException('Gateway retornou HTTP ' . $code . ': ' . $msg);
    }
    return is_array($data) ? $data : [];
}

/**
 * Cria um pagamento avulso. Retorna ['payment_id'=>?, 'pay_url'=>?, 'raw'=>array].
 * Redirecione o cliente para 'pay_url'.
 */
function dite_criar_pagamento(
    float $amount, string $currency, string $title, array $customer,
    string $externalRef, string $successUrl, string $cancelUrl, ?string $idempotencyKey = null
): array {
    $body = [
        'amount'             => round($amount, 2),
        'currency'           => strtoupper($currency),
        'title'              => $title,
        'customer'           => $customer,
        'external_reference' => $externalRef,
        'success_url'        => $successUrl,
        'cancel_url'         => $cancelUrl,
    ];
    $r = dite_api_post('/api/v1/payments', $body, $idempotencyKey);
    // A API embrulha a resposta em "data" (ex: { "data": { "pay_url": ... } }).
    $d = is_array($r['data'] ?? null) ? $r['data'] : $r;
    return [
        'payment_id' => $d['payment_id'] ?? ($d['id'] ?? null),
        'pay_url'    => $d['pay_url'] ?? ($d['url'] ?? ($d['checkout_url'] ?? null)),
        'raw'        => $r,
    ];
}
