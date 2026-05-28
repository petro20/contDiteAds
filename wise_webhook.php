<?php
/**
 * Endpoint público que recebe POST do Wise quando eventos acontecem.
 *
 * Configuração no painel Wise (Settings → Webhooks):
 *   - URL: https://cont.diteads.com/wise_webhook.php
 *   - Event: balances#credit (e/ou transfers#state-change)
 *
 * Validação:
 *   - Wise envia header X-Signature-SHA256 com assinatura RSA do payload
 *   - Header X-Test-Notification: true se for teste
 *   - Header X-Delivery-Id pra idempotência
 *
 * Pra cada credit recebido:
 *   - Salva o evento em wise_eventos
 *   - Tenta casar com cobrança aberta (mesma moeda, valor exato)
 *   - Se casa, registra pagamento_cliente automaticamente
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/lib/pagamentos.php';
require_once __DIR__ . '/lib/audit.php';

// Sempre retorna 200 OK rapidamente (Wise reenvia se falhar)
function responder(int $code, string $msg = 'OK'): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Status check pra ver se URL está acessível
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK · Wise webhook endpoint · use POST com payload Wise.";
    exit;
}

$db = db();
$payload_raw = file_get_contents('php://input');
$payload = json_decode($payload_raw ?: 'null', true);
$is_test = ($_SERVER['HTTP_X_TEST_NOTIFICATION'] ?? '') === 'true';
$delivery = $_SERVER['HTTP_X_DELIVERY_ID']   ?? null;
$assinatura = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? '';

// --- VALIDAÇÃO DE ASSINATURA (Wise public key) ---
// Wise mantém uma chave pública pública em https://api.transferwise.com/v1/notifications/public-key
// Pra produção, validamos com openssl_verify; pra simulação/dev, pulamos.
$pub_key_path = __DIR__ . '/wise_public_key.pem';
$assinatura_ok = false;
if (file_exists($pub_key_path) && $assinatura && $payload_raw) {
    $pub = @file_get_contents($pub_key_path);
    if ($pub) {
        $signature_bin = base64_decode($assinatura);
        $r = openssl_verify($payload_raw, $signature_bin, $pub, OPENSSL_ALGO_SHA256);
        $assinatura_ok = ($r === 1);
    }
}
// Bypass de assinatura: apenas em ambiente NÃO-produção (dev/staging).
// Em produção, a flag no banco é IGNORADA — sempre exigimos assinatura válida.
// Isso impede que um admin (ou um atacante com acesso à UI) desligue a validação
// e abra o endpoint pra POSTs falsificados.
require_once __DIR__ . '/lib/configuracoes.php';
$skip_sig = (APP_ENV !== 'production') && config_get($db, 'wise_skip_signature') === '1';

if (!$is_test && !$assinatura_ok && !$skip_sig) {
    // Loga mesmo se assinatura falhar (pra debug)
    try {
        $stmt = $db->prepare('INSERT INTO wise_eventos (event_type, delivery_id, payload_json, status, erro) VALUES (?,?,?,?,?)');
        $stmt->execute([
            'INVALID_SIGNATURE',
            $delivery,
            (string)$payload_raw,
            'assinatura_invalida',
            'Assinatura SHA256 não bateu com a chave pública',
        ]);
    } catch (Throwable $e) {}
    responder(401, 'Invalid signature');
}

// --- PROCESSA EVENTO ---
$event_type = $payload['event_type'] ?? 'unknown';

// Evita duplicar pela delivery_id
if ($delivery) {
    $stmt = $db->prepare('SELECT id FROM wise_eventos WHERE delivery_id = ?');
    $stmt->execute([$delivery]);
    if ($stmt->fetch()) responder(200, 'Já processado');
}

// Logs sempre (mesmo eventos não processáveis)
$insert = $db->prepare('INSERT INTO wise_eventos (event_type, delivery_id, payload_json, status, valor, moeda, payer_nome) VALUES (?,?,?,?,?,?,?)');

// Eventos de crédito (recebimento)
// Wise schema típico: { event_type: 'balances#credit', data: { resource: {...}, occurred_at, balance_id, amount, currency, ... } }
if (strpos($event_type, 'credit') !== false || strpos($event_type, 'balances') !== false) {
    $data = $payload['data'] ?? [];
    $valor = (float)($data['amount']         ?? ($data['post_transaction_balance_amount'] ?? 0));
    $moeda = strtoupper((string)($data['currency'] ?? ''));
    $payer = (string)($data['source']['name']    ?? ($payload['sender_name'] ?? ''));

    // Tenta casar com cobrança em aberto (moeda + valor exato)
    $cob_id = null; $pag_id = null; $status = 'sem_cobranca'; $erro = null;
    if ($valor > 0 && $moeda) {
        try {
            $stmt = $db->prepare("SELECT id, valor_total FROM cobrancas
                                  WHERE status IN ('aberta','em_analise')
                                  AND moeda = ?
                                  ORDER BY vencimento ASC");
            $stmt->execute([$moeda]);
            foreach ($stmt->fetchAll() as $c) {
                // Considera saldo (valor_total - já pago)
                $stmt2 = $db->prepare('SELECT COALESCE(SUM(valor_pago),0) FROM pagamentos_cliente WHERE cobranca_id = ?');
                $stmt2->execute([(int)$c['id']]);
                $pago = (float)$stmt2->fetchColumn();
                $saldo = (float)$c['valor_total'] - $pago;
                if (abs($saldo - $valor) < 0.01) {
                    $cob_id = (int)$c['id']; break;
                }
            }
            if ($cob_id) {
                $obs = 'Wise webhook · ' . ($delivery ?? '') . ' · pagador: ' . $payer;
                // pendente=true: admin precisa reconciliar/confirmar.
                // Evita marcar cobrança como 'paga' automaticamente sem revisão humana
                // (proteção contra payload falsificado ou casamento errado por valor).
                try {
                    $pag_id = registrar_pagamento_cliente($db, $cob_id, $valor, date('Y-m-d'), 'Wise', $obs, null, 0, true);
                    $status = 'casado';
                } catch (Throwable $e) {
                    // Notificação ou outro side-effect pode falhar; ainda assim o pagamento
                    // já foi inserido no DB. Loga o erro mas mantém o evento como 'casado'
                    // pra Wise não reenviar.
                    error_log('Wise webhook side-effect: ' . $e->getMessage());
                    $status = 'casado';
                }
            }
        } catch (Throwable $e) {
            $status = 'erro';
            $erro = $e->getMessage();
        }
    }

    try {
        $insert->execute([$event_type, $delivery, (string)$payload_raw, $status, $valor, $moeda ?: null, $payer ?: null]);
        $ev_id = (int)$db->lastInsertId();
        if ($cob_id || $pag_id) {
            $db->prepare('UPDATE wise_eventos SET cobranca_id = ?, pagamento_id = ?, erro = ? WHERE id = ?')
               ->execute([$cob_id, $pag_id, $erro, $ev_id]);
        }
        audit_log('wise.webhook', 'wise_eventos', $ev_id);
    } catch (Throwable $e) {
        error_log('Wise webhook insert: ' . $e->getMessage());
    }
} else {
    // Outros eventos: só loga
    try {
        $insert->execute([$event_type, $delivery, (string)$payload_raw, 'recebido', null, null, null]);
    } catch (Throwable $e) {}
}

responder(200, 'OK');
