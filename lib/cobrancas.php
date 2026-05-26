<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/money.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/whatsapp.php';

/**
 * Notifica o cliente por email sobre uma cobrança nova/paga.
 * Falha silenciosamente — não bloqueia a operação principal.
 */
function notificar_cliente_email(PDO $db, int $cobranca_id, string $codigo_template): void {
    try {
        $stmt = $db->prepare('SELECT cl.email FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ?');
        $stmt->execute([$cobranca_id]);
        $email = $stmt->fetchColumn();
        if (!$email) return;

        $tpl = wa_template($db, $codigo_template, 'email');
        if (!$tpl) return;

        $vars = wa_vars_cobranca($db, $cobranca_id);
        $assunto = wa_render($tpl['assunto'] ?: 'Notificação Dite Ads', $vars);
        $corpo_txt = wa_render($tpl['corpo'], $vars);
        $html = '<pre style="font-family:inherit;white-space:pre-wrap;">' . htmlspecialchars($corpo_txt) . '</pre>';
        email_enviar($email, $assunto, $html, $corpo_txt);
    } catch (Throwable $e) {
        error_log('notificar_cliente_email falhou: ' . $e->getMessage());
    }
}

/**
 * Lógica central de geração de cobranças (Sprint 2).
 *
 * Regras:
 *  - Cobrança consolidada por (cliente, mês) — UNIQUE no schema.
 *  - Itens MENSAL/pacote: incluídos pelo valor_cobrado da assinatura.
 *  - Itens POR_UNIDADE: incluídos com qtd = entregas do mês anterior;
 *    se zero, NÃO entram na cobrança.
 *  - Itens ÚNICOS: NÃO entram nas mensais — são cobrados na criação da assinatura.
 *  - Vencimento padrão = data de geração + 5 dias.
 */

function competencia_de_data(string $iso_date): string {
    return substr($iso_date, 0, 7); // "YYYY-MM"
}

function mes_anterior(string $competencia): string {
    $dt = DateTime::createFromFormat('Y-m', $competencia);
    if (!$dt) return $competencia;
    $dt->modify('-1 month');
    return $dt->format('Y-m');
}

/**
 * Dado um cliente e uma data de "hoje", retorna o dia efetivo de
 * cobrança (cuidando do caso "dia 31 em fevereiro" — cai no último dia).
 */
function dia_cobranca_efetivo(int $client_day, DateTimeImmutable $today): int {
    $days_in_month = (int)$today->format('t');
    return min($client_day, $days_in_month);
}

/**
 * Se a assinatura é a primeira do cliente, define o dia_cobranca = dia do início.
 */
function ensure_dia_cobranca(PDO $db, int $cliente_id, string $iniciada_em): void {
    $stmt = $db->prepare('SELECT dia_cobranca FROM clientes WHERE id = ?');
    $stmt->execute([$cliente_id]);
    $cur = $stmt->fetchColumn();
    if ($cur === null || (int)$cur === 0) {
        $day = (int)(new DateTimeImmutable($iniciada_em))->format('d');
        if ($day < 1) $day = 1;
        if ($day > 31) $day = 31;
        $stmt = $db->prepare('UPDATE clientes SET dia_cobranca = ? WHERE id = ?');
        $stmt->execute([$day, $cliente_id]);
    }
}

/**
 * Cria cobrança avulsa de item ÚNICO no momento da contratação.
 * Retorna o ID da cobrança criada, ou null se item não é único.
 */
function gerar_cobranca_avulsa_unico(PDO $db, int $assinatura_id, int $criado_por): ?int {
    $sql = 'SELECT a.*, c.moeda, i.nome AS item_nome, i.tipo
            FROM assinaturas a
            JOIN clientes c ON c.id = a.cliente_id
            JOIN itens_catalogo i ON i.id = a.item_id
            WHERE a.id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute([$assinatura_id]);
    $row = $stmt->fetch();
    if (!$row || $row['tipo'] !== 'unico') return null;

    // Cobrança avulsa: competência = mês da contratação, vencimento = +5 dias
    $hoje = new DateTimeImmutable($row['iniciada_em']);
    $competencia = $hoje->format('Y-m');
    $venc = $hoje->modify('+5 days')->format('Y-m-d');

    // Verifica se já há cobrança aberta para esse cliente no mês
    $stmt = $db->prepare('SELECT id FROM cobrancas WHERE cliente_id = ? AND competencia_mes = ?');
    $stmt->execute([$row['cliente_id'], $competencia]);
    $cobrId = $stmt->fetchColumn();

    $db->beginTransaction();
    try {
        if (!$cobrId) {
            $stmt = $db->prepare('INSERT INTO cobrancas (cliente_id, competencia_mes, valor_total, moeda, vencimento, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$row['cliente_id'], $competencia, (float)$row['valor_cobrado'], $row['moeda'], $venc, 'aberta']);
            $cobrId = (int)$db->lastInsertId();
        } else {
            // Soma ao total existente
            $stmt = $db->prepare('UPDATE cobrancas SET valor_total = valor_total + ? WHERE id = ?');
            $stmt->execute([(float)$row['valor_cobrado'], $cobrId]);
        }

        $stmt = $db->prepare('INSERT INTO cobranca_itens (cobranca_id, assinatura_id, descricao, quantidade, valor_unitario, subtotal) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            $cobrId, $assinatura_id, $row['item_nome'], 1,
            (float)$row['valor_cobrado'], (float)$row['valor_cobrado']
        ]);

        $db->commit();
        return (int)$cobrId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Gera (ou regera) a cobrança consolidada de um cliente para um mês.
 * Idempotente: se já existe cobrança no mês, NÃO regera. Retorna ['cobranca_id' => N, 'status' => 'created|exists|empty'].
 */
function gerar_cobranca_mensal(PDO $db, int $cliente_id, string $competencia, ?string $vencimento_override = null): array {
    // Já existe?
    $stmt = $db->prepare('SELECT id FROM cobrancas WHERE cliente_id = ? AND competencia_mes = ?');
    $stmt->execute([$cliente_id, $competencia]);
    $existente = $stmt->fetchColumn();
    if ($existente) {
        return ['cobranca_id' => (int)$existente, 'status' => 'exists'];
    }

    // Dados do cliente
    $stmt = $db->prepare('SELECT moeda, dia_cobranca FROM clientes WHERE id = ?');
    $stmt->execute([$cliente_id]);
    $cli = $stmt->fetch();
    if (!$cli) return ['cobranca_id' => null, 'status' => 'cliente_nao_encontrado'];

    $moeda = $cli['moeda'] ?: 'BRL';

    // Assinaturas ativas no mês da competência (respeita data_inicio e data_fim)
    $primeiro_dia = $competencia . '-01';
    $ultimo_dia = date('Y-m-t', strtotime($primeiro_dia));
    $sql = 'SELECT a.id, a.item_id, a.valor_cobrado, i.nome AS item_nome, i.tipo
            FROM assinaturas a
            JOIN itens_catalogo i ON i.id = a.item_id
            WHERE a.cliente_id = ?
              AND a.status = "ativa"
              AND a.iniciada_em <= ?
              AND (a.encerrada_em IS NULL OR a.encerrada_em >= ?)
              AND i.tipo IN ("mensal","por_unidade")';
    $stmt = $db->prepare($sql);
    $stmt->execute([$cliente_id, $ultimo_dia, $primeiro_dia]);
    $assinaturas = $stmt->fetchAll();

    if (!$assinaturas) return ['cobranca_id' => null, 'status' => 'empty'];

    $mes_anterior = mes_anterior($competencia);

    $linhas = [];
    foreach ($assinaturas as $a) {
        $valor = (float)$a['valor_cobrado'];
        if ($a['tipo'] === 'mensal') {
            $linhas[] = [
                'assinatura_id' => (int)$a['id'],
                'descricao'     => $a['item_nome'],
                'quantidade'    => 1,
                'valor_unitario'=> $valor,
                'subtotal'      => $valor,
            ];
        } else { // por_unidade
            $stmt = $db->prepare('SELECT COUNT(*) FROM entregas WHERE assinatura_id = ? AND competencia_mes = ?');
            $stmt->execute([(int)$a['id'], $mes_anterior]);
            $qtd = (int)$stmt->fetchColumn();
            if ($qtd > 0) {
                $linhas[] = [
                    'assinatura_id' => (int)$a['id'],
                    'descricao'     => $a['item_nome'] . ' (' . $qtd . ' un. de ' . $mes_anterior . ')',
                    'quantidade'    => $qtd,
                    'valor_unitario'=> $valor,
                    'subtotal'      => $qtd * $valor,
                ];
            }
        }
    }

    if (!$linhas) return ['cobranca_id' => null, 'status' => 'empty'];

    $total = array_sum(array_column($linhas, 'subtotal'));
    // Vencimento padrão: dia_cobranca do cliente + 5 dias (ou override)
    if ($vencimento_override) {
        $vencimento = $vencimento_override;
    } else {
        // Padrão: vencimento = dia_cobranca do cliente no mês da competência
        // (Para geração manual; o cron passa override = data exata do ciclo)
        $dia = max(1, min(31, (int)($cli['dia_cobranca'] ?: 1)));
        $base = DateTime::createFromFormat('Y-m-d', $competencia . '-01');
        $eff = min($dia, (int)$base->format('t'));
        $base->setDate((int)$base->format('Y'), (int)$base->format('m'), $eff);
        $vencimento = $base->format('Y-m-d');
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO cobrancas (cliente_id, competencia_mes, valor_total, moeda, vencimento, status) VALUES (?,?,?,?,?, "aberta")');
        $stmt->execute([$cliente_id, $competencia, $total, $moeda, $vencimento]);
        $cobrId = (int)$db->lastInsertId();

        $ins = $db->prepare('INSERT INTO cobranca_itens (cobranca_id, assinatura_id, descricao, quantidade, valor_unitario, subtotal) VALUES (?,?,?,?,?,?)');
        foreach ($linhas as $l) {
            $ins->execute([$cobrId, $l['assinatura_id'], $l['descricao'], $l['quantidade'], $l['valor_unitario'], $l['subtotal']]);
        }
        $db->commit();
        notificar_cliente_email($db, $cobrId, 'cobranca_nova');
        return ['cobranca_id' => $cobrId, 'status' => 'created'];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Roda a geração para TODOS os clientes elegíveis numa data específica.
 * Usado pelo cron diário.
 *
 * Regra: cobrança é EMITIDA 7 dias antes da data de cobrança do cliente.
 *  - Hoje + 7 dias = data alvo (será o vencimento)
 *  - Se hoje+7 cai no dia_cobranca efetivo do cliente, gera agora
 *  - Vencimento da cobrança = hoje + 7 dias
 *
 * @return array Log de execução com contagens.
 */
function executar_geracao_diaria(PDO $db, DateTimeImmutable $hoje): array {
    $target = $hoje->modify('+7 days'); // emitir 7 dias antes do vencimento
    $target_month = $target->format('Y-m');
    $target_day = (int)$target->format('d');

    $sql = 'SELECT id, dia_cobranca FROM clientes WHERE ativo = 1 AND dia_cobranca IS NOT NULL';
    $clientes = $db->query($sql)->fetchAll();

    $log = [
        'data' => $hoje->format('Y-m-d'),
        'alvo_vencimento' => $target->format('Y-m-d'),
        'avaliados' => count($clientes),
        'criadas' => 0, 'puladas' => 0, 'vazias' => 0, 'erros' => 0,
        'detalhes' => []
    ];

    foreach ($clientes as $c) {
        $eff = dia_cobranca_efetivo((int)$c['dia_cobranca'], $target);
        if ($eff !== $target_day) continue; // não é o cliente desse ciclo
        try {
            $r = gerar_cobranca_mensal($db, (int)$c['id'], $target_month, $target->format('Y-m-d'));
            $log['detalhes'][] = ['cliente_id' => (int)$c['id'], 'status' => $r['status'], 'cobranca_id' => $r['cobranca_id']];
            if ($r['status'] === 'created') $log['criadas']++;
            elseif ($r['status'] === 'exists') $log['puladas']++;
            elseif ($r['status'] === 'empty') $log['vazias']++;
        } catch (Throwable $e) {
            $log['erros']++;
            $log['detalhes'][] = ['cliente_id' => (int)$c['id'], 'status' => 'erro', 'mensagem' => $e->getMessage()];
            error_log('Geração cobrança falhou cliente=' . (int)$c['id'] . ': ' . $e->getMessage());
        }
    }
    return $log;
}
