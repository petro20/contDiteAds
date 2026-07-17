<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/cobrancas.php'; // db_coluna_existe()

/**
 * Lógica de pagamentos (Sprint 4).
 *
 * Conceito-chave:
 *  - "Liberado mas não pago" para funcionário = itens de cobrança PAGA
 *    onde a assinatura tem o funcionário como responsável, e o item ainda
 *    não foi incluído em nenhum pagamento_funcionario_itens.
 *  - Fila é CALCULADA (não persistida). Cobrar duas vezes não acontece
 *    porque os itens são marcados via pagamento_funcionario_itens.
 */

/**
 * Atualiza o status da cobrança baseado nos pagamentos.
 * Chama-se sempre que adiciona/remove pagamento.
 */
function atualiza_status_cobranca(PDO $db, int $cobranca_id): void {
    // Envolve em transação com SELECT ... FOR UPDATE pra evitar TOCTOU
    // (webhook + admin agindo na mesma cobrança ao mesmo tempo).
    $in_tx = $db->inTransaction();
    if (!$in_tx) $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT valor_total, status FROM cobrancas WHERE id = ? FOR UPDATE');
        $stmt->execute([$cobranca_id]);
        $c = $stmt->fetch();
        if (!$c || $c['status'] === 'cancelada') {
            if (!$in_tx) $db->commit();
            return;
        }

        // Só conta pagamentos CONFIRMADOS (pendente=0). Tolera schema antigo sem coluna pendente.
        try {
            $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos_cliente WHERE cobranca_id = ? AND pendente = 0');
            $stmt->execute([$cobranca_id]);
        } catch (PDOException $e) {
            $stmt = $db->prepare('SELECT COALESCE(SUM(valor_pago), 0) FROM pagamentos_cliente WHERE cobranca_id = ?');
            $stmt->execute([$cobranca_id]);
        }
        $pago_confirmado = (float)$stmt->fetchColumn();

        // Tem comprovante pendente? Se sim e não está paga ainda, status = em_analise
        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM pagamentos_cliente WHERE cobranca_id = ? AND pendente = 1');
            $stmt->execute([$cobranca_id]);
            $tem_pendente = (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $tem_pendente = false;
        }

        // Proteção: se a cobrança JÁ ESTÁ paga e tem dinheiro confirmado em cima,
        // não permite degradar pra 'cancelada' nem 'aberta' por causa de remoção
        // de item ou outro evento — preserva o histórico de pagamento.
        if ($c['status'] === 'paga' && $pago_confirmado > 0) {
            if (!$in_tx) $db->commit();
            return;
        }

        if ((float)$c['valor_total'] <= 0) {
            // Cobrança zerada (sem itens). Se NÃO tem pagamento confirmado,
            // DELETA automaticamente (não deixa lixo no histórico). Se por algum
            // motivo tiver pagamento confirmado, só cancela (preserva o registro).
            if ($pago_confirmado <= 0) {
                // Apaga linhas-filhas antes (evita erro de foreign key)
                try { $db->prepare('DELETE FROM cobranca_itens WHERE cobranca_id = ?')->execute([$cobranca_id]); } catch (Throwable $e) {}
                try { $db->prepare('DELETE FROM regua_eventos WHERE cobranca_id = ?')->execute([$cobranca_id]); } catch (Throwable $e) {}
                try { $db->prepare('DELETE FROM pagamentos_cliente WHERE cobranca_id = ?')->execute([$cobranca_id]); } catch (Throwable $e) {}
                try { $db->prepare('UPDATE wise_eventos SET cobranca_id = NULL WHERE cobranca_id = ?')->execute([$cobranca_id]); } catch (Throwable $e) {}
                $db->prepare('DELETE FROM cobrancas WHERE id = ?')->execute([$cobranca_id]);
                if (!$in_tx) $db->commit();
                return;
            }
            $novo = 'cancelada';
        } elseif ($pago_confirmado >= (float)$c['valor_total']) {
            $novo = 'paga';
        } elseif ($tem_pendente) {
            $novo = 'em_analise';
        } else {
            $novo = 'aberta';
        }

        if ($novo !== $c['status']) {
            $stmt = $db->prepare('UPDATE cobrancas SET status = ? WHERE id = ?');
            $stmt->execute([$novo, $cobranca_id]);
        }

        if (!$in_tx) $db->commit();
    } catch (Throwable $e) {
        if (!$in_tx && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/**
 * Registra um pagamento do cliente, opcionalmente com comprovante.
 * Retorna o ID do pagamento.
 */
function registrar_pagamento_cliente(
    PDO $db,
    int $cobranca_id,
    float $valor,
    string $data,
    ?string $metodo,
    ?string $observacao,
    ?string $comprovante_path,
    int $registrado_por,
    bool $pendente = false
): int {
    try {
        $stmt = $db->prepare('INSERT INTO pagamentos_cliente (cobranca_id, valor_pago, data_pagamento, metodo, observacao, comprovante_path, registrado_por, pendente) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$cobranca_id, $valor, $data, $metodo, $observacao, $comprovante_path, $registrado_por, $pendente ? 1 : 0]);
    } catch (PDOException $e) {
        // Schema antigo sem coluna pendente
        $stmt = $db->prepare('INSERT INTO pagamentos_cliente (cobranca_id, valor_pago, data_pagamento, metodo, observacao, comprovante_path, registrado_por) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$cobranca_id, $valor, $data, $metodo, $observacao, $comprovante_path, $registrado_por]);
    }
    $pid = (int)$db->lastInsertId();
    atualiza_status_cobranca($db, $cobranca_id);
    // Notifica cliente quando cobrança fica totalmente paga
    $stmt = $db->prepare('SELECT status FROM cobrancas WHERE id = ?');
    $stmt->execute([$cobranca_id]);
    if ($stmt->fetchColumn() === 'paga') {
        require_once __DIR__ . '/cobrancas.php';
        notificar_cliente_email($db, $cobranca_id, 'pagamento_confirmado');
    }
    return $pid;
}

/**
 * Retorna a fila de pagamentos pendentes consolidada por funcionário.
 * [{funcionario_id, nome, wisetag, total_usd, itens_count}]
 */
function fila_pagamentos_funcionarios(PDO $db): array {
    // Funcionário efetivo e valor unitário vêm da assinatura (+func_servico_pagamento)
    // OU, para itens avulsos, direto do próprio cobranca_item (migration 022).
    $has   = db_coluna_existe($db, 'cobranca_itens', 'funcionario_id');
    $vu    = $has ? '(CASE WHEN ci.assinatura_id IS NOT NULL THEN fsp.valor_usd ELSE ci.pagamento_func_usd END)' : 'fsp.valor_usd';
    $efunc = $has ? 'COALESCE(a.funcionario_id, ci.funcionario_id)' : 'a.funcionario_id';
    $sql = "
      SELECT
        u.id AS funcionario_id,
        u.nome,
        u.wisetag,
        COUNT(*) AS itens_count,
        COALESCE(SUM(ci.quantidade * COALESCE($vu, 0)), 0) AS total_usd,
        SUM(CASE WHEN $vu IS NULL THEN 1 ELSE 0 END) AS sem_valor_def
      FROM cobranca_itens ci
      JOIN cobrancas c       ON c.id = ci.cobranca_id
      LEFT JOIN assinaturas a ON a.id = ci.assinatura_id
      JOIN usuarios u        ON u.id = $efunc
      LEFT JOIN func_servico_pagamento fsp
              ON fsp.funcionario_id = a.funcionario_id AND fsp.item_id = a.item_id
      LEFT JOIN pagamento_funcionario_itens pfi ON pfi.cobranca_item_id = ci.id
      WHERE c.status = 'paga'
        AND $efunc IS NOT NULL
        AND pfi.id IS NULL
      GROUP BY u.id, u.nome, u.wisetag
      HAVING itens_count > 0
      ORDER BY total_usd DESC, u.nome";
    return $db->query($sql)->fetchAll();
}

/**
 * Itens pendentes de pagamento para um funcionário específico.
 */
function itens_pendentes_funcionario(PDO $db, int $funcionario_id): array {
    $has   = db_coluna_existe($db, 'cobranca_itens', 'funcionario_id');
    $vu    = $has ? '(CASE WHEN ci.assinatura_id IS NOT NULL THEN fsp.valor_usd ELSE ci.pagamento_func_usd END)' : 'fsp.valor_usd';
    $efunc = $has ? 'COALESCE(a.funcionario_id, ci.funcionario_id)' : 'a.funcionario_id';
    $sql = "
      SELECT
        ci.id, ci.descricao, ci.quantidade,
        c.competencia_mes,
        cl.nome_empresa,
        COALESCE(i.nome, ci.descricao) AS item_nome,
        i.tipo,
        COALESCE($vu, 0) AS valor_unitario_usd,
        (ci.quantidade * COALESCE($vu, 0)) AS subtotal_usd,
        ($vu IS NULL) AS sem_valor
      FROM cobranca_itens ci
      JOIN cobrancas c    ON c.id = ci.cobranca_id
      JOIN clientes cl    ON cl.id = c.cliente_id
      LEFT JOIN assinaturas a  ON a.id = ci.assinatura_id
      LEFT JOIN itens_catalogo i ON i.id = a.item_id
      LEFT JOIN func_servico_pagamento fsp
              ON fsp.funcionario_id = a.funcionario_id AND fsp.item_id = a.item_id
      LEFT JOIN pagamento_funcionario_itens pfi ON pfi.cobranca_item_id = ci.id
      WHERE c.status = 'paga'
        AND $efunc = ?
        AND pfi.id IS NULL
      ORDER BY c.competencia_mes DESC, cl.nome_empresa, item_nome";
    $stmt = $db->prepare($sql);
    $stmt->execute([$funcionario_id]);
    return $stmt->fetchAll();
}

/**
 * Histórico de pagamentos a um funcionário.
 */
function historico_pagamentos_funcionario(PDO $db, int $funcionario_id, ?string $de = null, ?string $ate = null): array {
    $sql = 'SELECT id, valor_usd, data_pagamento, comprovante_pdf_path, criado_em
            FROM pagamentos_funcionario
            WHERE funcionario_id = ?';
    $params = [$funcionario_id];
    if ($de)  { $sql .= ' AND data_pagamento >= ?'; $params[] = $de; }
    if ($ate) { $sql .= ' AND data_pagamento <= ?'; $params[] = $ate; }
    $sql .= ' ORDER BY data_pagamento DESC, id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Detalhamento de um pagamento (linhas).
 */
function detalhes_pagamento_funcionario(PDO $db, int $pagamento_id): array {
    $sql = 'SELECT pfi.*, ci.competencia_mes_cobranca, c.competencia_mes, cl.nome_empresa
            FROM pagamento_funcionario_itens pfi
            LEFT JOIN cobranca_itens ci ON ci.id = pfi.cobranca_item_id
            LEFT JOIN cobrancas c       ON c.id = ci.cobranca_id
            LEFT JOIN clientes cl       ON cl.id = c.cliente_id
            WHERE pfi.pagamento_id = ?
            ORDER BY pfi.id';
    // competencia_mes_cobranca alias not really needed; correção rápida:
    $sql = 'SELECT pfi.id, pfi.descricao, pfi.quantidade, pfi.valor_unitario_usd, pfi.subtotal_usd,
                   c.competencia_mes, cl.nome_empresa
            FROM pagamento_funcionario_itens pfi
            LEFT JOIN cobranca_itens ci ON ci.id = pfi.cobranca_item_id
            LEFT JOIN cobrancas c       ON c.id = ci.cobranca_id
            LEFT JOIN clientes cl       ON cl.id = c.cliente_id
            WHERE pfi.pagamento_id = ?
            ORDER BY pfi.id';
    $stmt = $db->prepare($sql);
    $stmt->execute([$pagamento_id]);
    return $stmt->fetchAll();
}

/**
 * Cria um pagamento ao funcionário marcando os cobranca_itens incluídos.
 *
 * @param array $cobranca_item_ids IDs dos itens a incluir
 * @return int Pagamento ID
 */
function criar_pagamento_funcionario(
    PDO $db,
    int $funcionario_id,
    array $cobranca_item_ids,
    string $data_pagamento,
    int $criado_por
): int {
    if (!$cobranca_item_ids) {
        throw new RuntimeException(t('Nenhum item selecionado.'));
    }
    // Carrega cada item + valor USD do funcionário
    $has   = db_coluna_existe($db, 'cobranca_itens', 'funcionario_id');
    $vu    = $has ? '(CASE WHEN ci.assinatura_id IS NOT NULL THEN fsp.valor_usd ELSE ci.pagamento_func_usd END)' : 'fsp.valor_usd';
    $efunc = $has ? 'COALESCE(a.funcionario_id, ci.funcionario_id)' : 'a.funcionario_id';
    $place = implode(',', array_fill(0, count($cobranca_item_ids), '?'));
    $sql = "SELECT ci.id, ci.descricao, ci.quantidade,
                   COALESCE($vu, 0) AS valor_unitario_usd,
                   (ci.quantidade * COALESCE($vu, 0)) AS subtotal_usd
            FROM cobranca_itens ci
            LEFT JOIN assinaturas a ON a.id = ci.assinatura_id
            LEFT JOIN func_servico_pagamento fsp
              ON fsp.funcionario_id = a.funcionario_id AND fsp.item_id = a.item_id
            LEFT JOIN pagamento_funcionario_itens pfi ON pfi.cobranca_item_id = ci.id
            WHERE ci.id IN ($place)
              AND $efunc = ?
              AND pfi.id IS NULL";
    $stmt = $db->prepare($sql);
    $params = $cobranca_item_ids; $params[] = $funcionario_id;
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    if (!$items) throw new RuntimeException(t('Nenhum item válido pra incluir.'));

    $total = (float)array_sum(array_column($items, 'subtotal_usd'));

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO pagamentos_funcionario (funcionario_id, valor_usd, data_pagamento, criado_por) VALUES (?,?,?,?)');
        $stmt->execute([$funcionario_id, $total, $data_pagamento, $criado_por]);
        $pid = (int)$db->lastInsertId();

        $ins = $db->prepare('INSERT INTO pagamento_funcionario_itens (pagamento_id, cobranca_item_id, descricao, quantidade, valor_unitario_usd, subtotal_usd) VALUES (?,?,?,?,?,?)');
        foreach ($items as $it) {
            $ins->execute([$pid, (int)$it['id'], $it['descricao'], (int)$it['quantidade'], (float)$it['valor_unitario_usd'], (float)$it['subtotal_usd']]);
        }
        $db->commit();
        return $pid;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Salva ou atualiza um valor USD para (funcionário, item).
 */
function definir_valor_funcionario_item(PDO $db, int $funcionario_id, int $item_id, float $valor_usd): void {
    $stmt = $db->prepare('INSERT INTO func_servico_pagamento (funcionario_id, item_id, valor_usd) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor_usd = VALUES(valor_usd)');
    $stmt->execute([$funcionario_id, $item_id, $valor_usd]);
}
