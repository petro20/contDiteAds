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
    // (int) cast: ceil() retorna float, str_pad() em PHP 8.1+ exige int.
    $bits = str_pad($bits, (int)(ceil(strlen($bits) / 5) * 5), '0');
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

/**
 * Gera N backup codes (default 8) no formato XXXX-XXXX (alfanumérico maiúsculo).
 * Salva o HASH no banco e retorna os códigos em plaintext UMA ÚNICA VEZ
 * (mostrar pro usuário guardar — impressão, gerenciador de senhas etc).
 *
 * Apaga códigos antigos não-usados do mesmo usuário antes.
 *
 * @return string[] Códigos em plaintext (mostrar ao usuário)
 */
function totp_gerar_backup_codes(PDO $db, int $usuario_id, int $quantos = 8): array {
    // Limpa códigos antigos não-usados (regeneração descarta os anteriores)
    try {
        $db->prepare('DELETE FROM totp_backup_codes WHERE usuario_id = ? AND usado_em IS NULL')->execute([$usuario_id]);
    } catch (PDOException $e) { /* tabela ainda não existe */ }

    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0/O/1/I pra reduzir ambiguidade
    $clen = strlen($chars);
    $plain = [];
    $stmt = $db->prepare('INSERT INTO totp_backup_codes (usuario_id, codigo_hash) VALUES (?, ?)');
    for ($i = 0; $i < $quantos; $i++) {
        $codigo = '';
        for ($k = 0; $k < 8; $k++) $codigo .= $chars[random_int(0, $clen - 1)];
        // Formato XXXX-XXXX pra facilitar leitura
        $codigo = substr($codigo, 0, 4) . '-' . substr($codigo, 4);
        $hash = password_hash($codigo, PASSWORD_BCRYPT);
        $stmt->execute([$usuario_id, $hash]);
        $plain[] = $codigo;
    }
    return $plain;
}

/**
 * Tenta consumir um backup code. Retorna true se conseguiu (e marca como usado).
 * Aceita formato com ou sem hífen, case-insensitive.
 */
function totp_consumir_backup_code(PDO $db, int $usuario_id, string $codigo_user): bool {
    $codigo_user = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $codigo_user));
    if (strlen($codigo_user) !== 8) return false;
    $codigo_norm = substr($codigo_user, 0, 4) . '-' . substr($codigo_user, 4);

    try {
        $stmt = $db->prepare('SELECT id, codigo_hash FROM totp_backup_codes WHERE usuario_id = ? AND usado_em IS NULL');
        $stmt->execute([$usuario_id]);
        foreach ($stmt->fetchAll() as $row) {
            if (password_verify($codigo_norm, $row['codigo_hash'])) {
                $upd = $db->prepare('UPDATE totp_backup_codes SET usado_em = NOW() WHERE id = ?');
                $upd->execute([(int)$row['id']]);
                return true;
            }
        }
    } catch (PDOException $e) { /* tabela não existe */ }
    return false;
}

/**
 * Conta backup codes ainda válidos pra um usuário.
 */
function totp_backup_codes_restantes(PDO $db, int $usuario_id): int {
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM totp_backup_codes WHERE usuario_id = ? AND usado_em IS NULL');
        $stmt->execute([$usuario_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
