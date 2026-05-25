<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/**
 * Helpers de entregas (Sprint 3).
 *
 * Regras adotadas:
 *  - Funcionário pode marcar/desmarcar a qualquer momento.
 *  - Admin NÃO precisa aprovar (marcação do func = válida).
 *  - Cliente vê em tempo real.
 *
 * Tipos de UI:
 *  - e_pacote=1 (ex: POSTAGEM 7D/5D/2D)   → checkbox por dia no calendário
 *  - tipo=por_unidade (CTF, CTV, CTI)     → tally (lista de marcações, sem limite)
 *  - tipo=unico                            → 1 checkbox "entregue"
 *  - tipo=mensal sem pacote (Meta/Google) → sem checkbox (read-only "ativo")
 */

function entregas_modo_ui(array $item): string {
    if ((int)$item['e_pacote'] === 1) return 'calendar';
    if ($item['tipo'] === 'por_unidade') return 'tally';
    if ($item['tipo'] === 'unico')       return 'single';
    return 'info'; // mensal sem pacote
}

/**
 * Lista as entregas de uma assinatura no mês.
 */
function entregas_do_mes(PDO $db, int $assinatura_id, string $competencia): array {
    $stmt = $db->prepare('SELECT id, data_marcada, indice, criado_em FROM entregas WHERE assinatura_id = ? AND competencia_mes = ? ORDER BY data_marcada, indice, id');
    $stmt->execute([$assinatura_id, $competencia]);
    return $stmt->fetchAll();
}

/**
 * Conta marcações de uma assinatura no mês.
 */
function entregas_count(PDO $db, int $assinatura_id, string $competencia): int {
    $stmt = $db->prepare('SELECT COUNT(*) FROM entregas WHERE assinatura_id = ? AND competencia_mes = ?');
    $stmt->execute([$assinatura_id, $competencia]);
    return (int)$stmt->fetchColumn();
}

/**
 * Marca um dia (calendar mode). Idempotente.
 */
function entregas_toggle_dia(PDO $db, int $assinatura_id, string $competencia, string $data, int $funcionario_id): array {
    $stmt = $db->prepare('SELECT id FROM entregas WHERE assinatura_id = ? AND competencia_mes = ? AND data_marcada = ? LIMIT 1');
    $stmt->execute([$assinatura_id, $competencia, $data]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $stmt = $db->prepare('DELETE FROM entregas WHERE id = ?');
        $stmt->execute([$existing]);
        return ['action' => 'removed', 'id' => (int)$existing];
    }
    $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, data_marcada, funcionario_id) VALUES (?,?,?,?)');
    $stmt->execute([$assinatura_id, $competencia, $data, $funcionario_id]);
    return ['action' => 'added', 'id' => (int)$db->lastInsertId()];
}

/**
 * Adiciona 1 unidade (tally mode). Sem dedup — cada chamada = +1.
 */
function entregas_add_unidade(PDO $db, int $assinatura_id, string $competencia, int $funcionario_id): int {
    $stmt = $db->prepare('SELECT COALESCE(MAX(indice),0) FROM entregas WHERE assinatura_id = ? AND competencia_mes = ?');
    $stmt->execute([$assinatura_id, $competencia]);
    $idx = (int)$stmt->fetchColumn() + 1;
    $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, indice, funcionario_id) VALUES (?,?,?,?)');
    $stmt->execute([$assinatura_id, $competencia, $idx, $funcionario_id]);
    return (int)$db->lastInsertId();
}

/**
 * Marca/desmarca o "entregue" de item único.
 */
function entregas_toggle_unico(PDO $db, int $assinatura_id, string $competencia, int $funcionario_id): array {
    $stmt = $db->prepare('SELECT id FROM entregas WHERE assinatura_id = ? AND competencia_mes = ? LIMIT 1');
    $stmt->execute([$assinatura_id, $competencia]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $stmt = $db->prepare('DELETE FROM entregas WHERE id = ?');
        $stmt->execute([$existing]);
        return ['action' => 'removed'];
    }
    $stmt = $db->prepare('INSERT INTO entregas (assinatura_id, competencia_mes, funcionario_id) VALUES (?,?,?)');
    $stmt->execute([$assinatura_id, $competencia, $funcionario_id]);
    return ['action' => 'added'];
}

/**
 * Remove uma entrega específica por id (qualquer modo).
 */
function entregas_remover(PDO $db, int $entrega_id): void {
    $stmt = $db->prepare('DELETE FROM entregas WHERE id = ?');
    $stmt->execute([$entrega_id]);
}

/**
 * Lista assinaturas que aparecem na agenda do funcionário no mês.
 * Filtra assinaturas ativas onde ele é responsável.
 */
function agenda_assinaturas(PDO $db, int $funcionario_id, string $competencia): array {
    $sql = 'SELECT a.id AS assinatura_id, a.variante, a.valor_cobrado,
                   cl.id AS cliente_id, cl.nome_empresa, cl.moeda,
                   i.id AS item_id, i.nome AS item_nome, i.tipo, i.e_pacote
            FROM assinaturas a
            JOIN clientes cl ON cl.id = a.cliente_id
            JOIN itens_catalogo i ON i.id = a.item_id
            WHERE a.funcionario_id = ? AND a.status = "ativa"
            ORDER BY cl.nome_empresa, i.nome';
    $stmt = $db->prepare($sql);
    $stmt->execute([$funcionario_id]);
    return $stmt->fetchAll();
}

/**
 * Lista assinaturas de um cliente (para o cliente ver entregas).
 */
function agenda_assinaturas_cliente(PDO $db, int $cliente_id, string $competencia): array {
    $sql = 'SELECT a.id AS assinatura_id, a.variante, a.valor_cobrado,
                   i.id AS item_id, i.nome AS item_nome, i.tipo, i.e_pacote,
                   u.nome AS funcionario_nome
            FROM assinaturas a
            JOIN itens_catalogo i ON i.id = a.item_id
            LEFT JOIN usuarios u ON u.id = a.funcionario_id
            WHERE a.cliente_id = ? AND a.status = "ativa"
            ORDER BY i.nome';
    $stmt = $db->prepare($sql);
    $stmt->execute([$cliente_id]);
    return $stmt->fetchAll();
}

/**
 * Constrói uma matriz semana×dia para renderizar calendário do mês.
 */
function calendario_do_mes(string $competencia): array {
    $first = DateTime::createFromFormat('Y-m-d', $competencia . '-01');
    $days = (int)$first->format('t');
    $startDow = (int)$first->format('w'); // 0=dom .. 6=sáb
    $matrix = [];
    $week = array_fill(0, 7, null);
    for ($d = 1; $d <= $days; $d++) {
        $col = ($startDow + $d - 1) % 7;
        $week[$col] = sprintf('%s-%02d', $competencia, $d);
        if ($col === 6 || $d === $days) {
            $matrix[] = $week;
            $week = array_fill(0, 7, null);
        }
    }
    return $matrix;
}
