<?php
declare(strict_types=1);

/**
 * Implementação minimalista de TOTP RFC 6238 (HMAC-SHA1, 30s, 6 dígitos).
 * Compatible com Google Authenticator, Authy, 1Password, etc.
 */

function totp_base32_encode(string $bytes): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $bits = str_pad($bits, ceil(strlen($bits) / 5) * 5, '0');
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alphabet[bindec($chunk)];
    return $out;
}

function totp_base32_decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $i = strpos($alphabet, $c);
        if ($i === false) continue;
        $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    return $out;
}

function totp_gerar_secret(): string {
    return totp_base32_encode(random_bytes(20)); // 160 bits
}

function totp_codigo(string $secret_b32, ?int $time = null, int $window = 0): string {
    $time = $time ?? time();
    $counter = intdiv($time, 30) + $window;
    $key = totp_base32_decode($secret_b32);
    $counter_bin = pack('N*', 0) . pack('N*', $counter);
    $hmac = hash_hmac('sha1', $counter_bin, $key, true);
    $offset = ord($hmac[19]) & 0xf;
    $code = (
        ((ord($hmac[$offset]) & 0x7f) << 24) |
        ((ord($hmac[$offset + 1]) & 0xff) << 16) |
        ((ord($hmac[$offset + 2]) & 0xff) << 8) |
        (ord($hmac[$offset + 3]) & 0xff)
    ) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function totp_verificar(string $secret_b32, string $codigo_user): bool {
    $codigo_user = preg_replace('/\D/', '', $codigo_user);
    if (strlen($codigo_user) !== 6) return false;
    foreach ([-1, 0, 1] as $w) {
        if (hash_equals(totp_codigo($secret_b32, null, $w), $codigo_user)) return true;
    }
    return false;
}

function totp_otpauth_uri(string $label, string $secret_b32, string $issuer = 'Dite Ads'): string {
    return sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s',
        rawurlencode($issuer), rawurlencode($label), $secret_b32, rawurlencode($issuer));
}
