<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

function config_get(PDO $db, string $chave, string $default = ''): string {
    try {
        $stmt = $db->prepare('SELECT valor FROM configuracoes WHERE chave = ?');
        $stmt->execute([$chave]);
        $v = $stmt->fetchColumn();
        return $v !== false && $v !== null ? (string)$v : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function config_set(PDO $db, string $chave, string $valor): void {
    $stmt = $db->prepare('INSERT INTO configuracoes (chave, valor) VALUES (?,?)
                          ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $stmt->execute([$chave, $valor]);
}

/** Retorna as configs de pagamento como array. */
function config_pagamento(PDO $db): array {
    $qr = config_get($db, 'pagamento_zelle_qr');
    return [
        'zelle_email' => config_get($db, 'pagamento_zelle_email'),
        'zelle_qr'    => $qr,
        'zelle_qr_url'=> $qr ? (APP_BASE_URL . '/uploads/' . $qr) : '',
        'instrucoes'  => config_get($db, 'pagamento_instrucoes'),
    ];
}
