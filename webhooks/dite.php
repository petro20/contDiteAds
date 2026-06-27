<?php
/**
 * Webhook do Dite Gateway. URL pública: https://cont.diteads.com/webhooks/dite
 * (rota mapeada no .htaccess para este arquivo).
 *
 * Segurança:
 *   - Valida a assinatura HMAC-SHA256 (header X-Dite-Signature) com o CORPO BRUTO.
 *   - Idempotente: ignora event_id já processado (tabela dite_eventos).
 *   - Responde 2xx rápido; o gateway reenvia em caso de falha.
 *
 * Eventos tratados:
 *   payment.paid     -> registra pagamento na cobrança (external_reference = "cob_<id>")
 *   payment.failed/refunded/disputed, subscription.* -> só registra (admin trata)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../lib/dite.php';
require_once __DIR__ . '/../lib/pagamentos.php';
require_once __DIR__ . '/../lib/audit.php';

function dite_responder(int $code, string $msg = 'OK'): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK · Dite Gateway webhook endpoint · use POST.';
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_X_DITE_SIGNATURE'] ?? '';

if (!dite_habilitado())                 dite_responder(503, 'Webhook nao configurado');
if (!dite_assinatura_valida($raw, $sig)) dite_responder(401, 'Invalid signature');

$payload = json_decode($raw ?: 'null', true);
if (!is_array($payload)) dite_responder(400, 'Payload invalido');

$db = db();

$event_type = (string)($payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? 'unknown');
$event_id   = (string)($payload['id'] ?? $payload['event_id'] ?? ($_SERVER['HTTP_X_DITE_DELIVERY'] ?? ''));
if ($event_id === '') $event_id = 'sha_' . hash('sha256', $raw); // fallback estável p/ idempotência

$data  = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
$ext   = (string)($data['external_reference'] ?? $payload['external_reference'] ?? '');
$valor = (float)($data['amount'] ?? $payload['amount'] ?? 0);
$moeda = strtoupper((string)($data['currency'] ?? $payload['currency'] ?? ''));

// Idempotência: já processado? sai 200.
try {
    $stmt = $db->prepare('SELECT id FROM dite_eventos WHERE event_id = ?');
    $stmt->execute([$event_id]);
    if ($stmt->fetch()) dite_responder(200, 'Ja processado');
} catch (Throwable $e) { /* tabela ausente: segue e tenta inserir depois */ }

// cobranca_id vem do external_reference ("cob_123" ou só "123")
$cob_id = null;
if (preg_match('/^cob_(\d+)$/', $ext, $mm)) $cob_id = (int)$mm[1];
elseif (ctype_digit($ext))                   $cob_id = (int)$ext;

$pag_id = null; $status = 'recebido'; $erro = null;

if ($event_type === 'payment.paid') {
    if (!$cob_id) {
        $status = 'sem_referencia';
    } else {
        try {
            $stmt = $db->prepare('SELECT id, valor_total FROM cobrancas WHERE id = ?');
            $stmt->execute([$cob_id]);
            $cob = $stmt->fetch();
            if (!$cob) {
                $status = 'cobranca_nao_encontrada';
            } else {
                $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos_cliente WHERE cobranca_id = ?');
                $stmt->execute([$cob_id]);
                $pago  = (float)$stmt->fetchColumn();
                $saldo = (float)$cob['valor_total'] - $pago;
                $vpag  = $valor > 0 ? $valor : $saldo;
                if ($vpag > 0.001) {
                    // Assinatura do webhook já validada → pagamento confiável (pendente=false).
                    $obs = 'Dite Gateway · ' . $event_id;
                    $pag_id = registrar_pagamento_cliente($db, $cob_id, $vpag, date('Y-m-d'), 'Cartão (Dite)', $obs, null, 0, false);
                    $status = 'pago';
                } else {
                    $status = 'sem_saldo';
                }
            }
        } catch (Throwable $e) {
            $status = 'erro';
            $erro   = $e->getMessage();
            error_log('Dite webhook payment.paid: ' . $e->getMessage());
        }
    }
} elseif (in_array($event_type, ['payment.failed', 'payment.refunded', 'payment.disputed'], true)) {
    $status = str_replace('payment.', '', $event_type); // failed | refunded | disputed (admin trata manualmente)
} elseif (strpos($event_type, 'subscription.') === 0) {
    $status = 'assinatura_' . str_replace('subscription.', '', $event_type); // fase 2
}

try {
    $stmt = $db->prepare('INSERT INTO dite_eventos (event_id, event_type, payload_json, status, cobranca_id, pagamento_id, valor, moeda, erro) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$event_id, $event_type, $raw, $status, $cob_id, $pag_id, $valor ?: null, $moeda ?: null, $erro]);
    audit_log('dite.webhook', 'dite_eventos', (int)$db->lastInsertId());
} catch (Throwable $e) {
    // Provável corrida (event_id duplicado) ou tabela ausente — idempotência garante segurança.
    error_log('Dite webhook insert: ' . $e->getMessage());
}

dite_responder(200, 'OK');
