<?php
/**
 * Helper que usa o token salvo pra listar os profiles da conta Wise.
 * Retorna JSON com os IDs disponíveis (personal/business) pra escolher.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/wise.php';
header('Content-Type: application/json; charset=utf-8');
require_sadmin();

$profiles = wise_profiles(db());
if (isset($profiles['__error'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $profiles['__error']]); exit;
}

$out = [];
foreach ((array)$profiles as $p) {
    $out[] = [
        'id'     => $p['id']     ?? null,
        'type'   => $p['type']   ?? '?',
        'nome'   => trim(($p['details']['firstName'] ?? '') . ' ' . ($p['details']['lastName'] ?? '')) ?:
                    ($p['details']['name'] ?? '?'),
    ];
}
echo json_encode(['ok' => true, 'profiles' => $out]);
