<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/whatsapp.php';

/**
 * Régua de cobrança automática (Sprint 5 — N14).
 *
 * Para cada cobrança com status='aberta' e silenciada=0:
 *   - Para cada etapa ativa, calcula se hoje = vencimento + dias_apos_vencimento.
 *   - Se sim e ainda não disparou esta etapa: dispara.
 *     - email: envia já via SMTP
 *     - whatsapp: cria regua_evento pendente (admin dispara via wa.me na tela)
 *
 * Idempotente: evita duplicidade checando regua_eventos.
 */

function regua_etapas_ativas(PDO $db): array {
    return $db->query('SELECT * FROM regua_etapas WHERE ativa = 1 ORDER BY ordem')->fetchAll();
}

function regua_ja_disparou(PDO $db, int $cobranca_id, int $etapa_id, string $canal): bool {
    $stmt = $db->prepare('SELECT 1 FROM regua_eventos WHERE cobranca_id=? AND etapa_id=? AND canal=? LIMIT 1');
    $stmt->execute([$cobranca_id, $etapa_id, $canal]);
    return (bool)$stmt->fetchColumn();
}

function regua_registrar_evento(PDO $db, int $cobranca_id, int $etapa_id, string $canal, ?DateTimeImmutable $enviado_em = null): int {
    $stmt = $db->prepare('INSERT INTO regua_eventos (cobranca_id, etapa_id, canal, enviado_em) VALUES (?,?,?,?)');
    $stmt->execute([$cobranca_id, $etapa_id, $canal, $enviado_em ? $enviado_em->format('Y-m-d H:i:s') : null]);
    return (int)$db->lastInsertId();
}

/**
 * Roda a régua para um momento específico (hoje por padrão).
 *
 * @return array Log de execução.
 */
function regua_executar(PDO $db, DateTimeImmutable $hoje): array {
    $hoje_str = $hoje->format('Y-m-d');
    $etapas = regua_etapas_ativas($db);
    $log = ['data' => $hoje_str, 'cobrancas_avaliadas' => 0, 'emails_enviados' => 0, 'whatsapp_pendentes' => 0, 'erros' => 0, 'detalhes' => []];

    if (!$etapas) return $log;

    $stmt = $db->prepare("SELECT c.id, c.cliente_id, c.vencimento, c.silenciada, cl.email, cl.telefone, cl.nome_empresa, cl.nome_contato
                          FROM cobrancas c JOIN clientes cl ON cl.id = c.cliente_id
                          WHERE c.status = 'aberta' AND c.silenciada = 0");
    $stmt->execute();
    $cobrancas = $stmt->fetchAll();
    $log['cobrancas_avaliadas'] = count($cobrancas);

    foreach ($cobrancas as $cob) {
        $venc = new DateTimeImmutable($cob['vencimento']);
        // diferença em dias (positivo = atraso, negativo = ainda vai vencer)
        $dias_atraso = (int)$venc->diff($hoje)->format('%r%a');

        $vars = wa_vars_cobranca($db, (int)$cob['id']);

        foreach ($etapas as $etapa) {
            // etapa.dias_apos_vencimento pode ser negativo (envio ANTES do vencimento)
            // ou positivo (depois). Só dispara quando bate exato.
            if ((int)$etapa['dias_apos_vencimento'] !== $dias_atraso) continue;

            // EMAIL
            if ($etapa['template_email_id'] && $cob['email']) {
                if (!regua_ja_disparou($db, (int)$cob['id'], (int)$etapa['id'], 'email')) {
                    $stmt2 = $db->prepare('SELECT assunto, corpo FROM templates_mensagem WHERE id = ?');
                    $stmt2->execute([(int)$etapa['template_email_id']]);
                    $tpl = $stmt2->fetch();
                    if ($tpl) {
                        $assunto = wa_render($tpl['assunto'] ?: 'Lembrete de cobrança', $vars);
                        $corpo_txt = wa_render($tpl['corpo'], $vars);
                        $html = '<pre style="font-family:inherit;white-space:pre-wrap;">' . htmlspecialchars($corpo_txt) . '</pre>';
                        $r = email_enviar($cob['email'], $assunto, $html, $corpo_txt);
                        if ($r === true) {
                            regua_registrar_evento($db, (int)$cob['id'], (int)$etapa['id'], 'email', $hoje);
                            $log['emails_enviados']++;
                        } else {
                            $log['erros']++;
                            $log['detalhes'][] = ['cobranca' => (int)$cob['id'], 'etapa' => (int)$etapa['id'], 'erro' => (string)$r];
                        }
                    }
                }
            }

            // WHATSAPP — só cria evento pendente; admin envia manualmente
            if ($etapa['template_whatsapp_id'] && $cob['telefone']) {
                if (!regua_ja_disparou($db, (int)$cob['id'], (int)$etapa['id'], 'whatsapp')) {
                    regua_registrar_evento($db, (int)$cob['id'], (int)$etapa['id'], 'whatsapp', null);
                    $log['whatsapp_pendentes']++;
                }
            }
        }
    }
    return $log;
}

/**
 * Lista tarefas WhatsApp pendentes (régua) para o admin disparar.
 */
function regua_tarefas_whatsapp_pendentes(PDO $db): array {
    $sql = "SELECT re.id AS evento_id, re.cobranca_id, re.criado_em,
                   c.vencimento, c.valor_total, c.moeda, c.competencia_mes,
                   cl.nome_empresa, cl.telefone,
                   t.codigo AS template_codigo, t.corpo AS template_corpo,
                   e.dias_apos_vencimento
            FROM regua_eventos re
            JOIN cobrancas c ON c.id = re.cobranca_id
            JOIN clientes cl ON cl.id = c.cliente_id
            JOIN regua_etapas e ON e.id = re.etapa_id
            JOIN templates_mensagem t ON t.id = e.template_whatsapp_id
            WHERE re.canal = 'whatsapp'
              AND re.enviado_em IS NULL
              AND c.status = 'aberta'
              AND c.silenciada = 0
            ORDER BY re.criado_em";
    return $db->query($sql)->fetchAll();
}

function regua_marcar_evento_enviado(PDO $db, int $evento_id, int $marcado_por): void {
    $stmt = $db->prepare('UPDATE regua_eventos SET enviado_em = NOW(), marcado_por = ? WHERE id = ?');
    $stmt->execute([$marcado_por, $evento_id]);
}
