<?php
declare(strict_types=1);

/**
 * Funções de hard delete em cascata.
 *
 * Estratégia:
 *  - Tudo que é "do" cliente/funcionário (cobranças, pagamentos recebidos,
 *    entregas executadas, capacidades, valores etc.) é apagado.
 *  - Tudo que apenas FOI CRIADO/REGISTRADO POR esse usuário (audit, criador
 *    de cobrança, etc.) é reatribuído ao sadmin que está executando o delete,
 *    pra preservar histórico financeiro.
 */

function hard_delete_cliente(PDO $db, int $cliente_id): void {
    if ($cliente_id <= 0) throw new InvalidArgumentException('cliente_id inválido.');

    $db->beginTransaction();
    try {
        // 1. Cobranças desse cliente → CASCADE em cobranca_itens, pagamentos_cliente, regua_eventos
        $db->prepare('DELETE FROM cobrancas WHERE cliente_id = ?')->execute([$cliente_id]);

        // 2. Assinaturas desse cliente → CASCADE em entregas
        $db->prepare('DELETE FROM assinaturas WHERE cliente_id = ?')->execute([$cliente_id]);

        // 3. Usuário(s) com role='cliente' vinculados a esse cliente (login do cliente)
        //    O FK usuarios.cliente_id é SET NULL, então precisamos apagar explicitamente.
        $db->prepare("DELETE FROM usuarios WHERE cliente_id = ? AND role = 'cliente'")->execute([$cliente_id]);

        // 4. O próprio cliente
        $db->prepare('DELETE FROM clientes WHERE id = ?')->execute([$cliente_id]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function hard_delete_usuario(PDO $db, int $user_id, int $acting_user_id): void {
    if ($user_id <= 0) throw new InvalidArgumentException('user_id inválido.');
    if ($user_id === $acting_user_id) throw new RuntimeException('Não dá pra apagar a si mesmo.');

    $db->beginTransaction();
    try {
        // ============================================================
        // 1. REATRIBUI registros "criado por" ao sadmin atual
        //    (preserva histórico financeiro)
        // ============================================================
        $reatrib = [
            'UPDATE cobrancas SET criado_por = ? WHERE criado_por = ?',
            'UPDATE pagamentos_cliente SET registrado_por = ? WHERE registrado_por = ?',
            'UPDATE pagamentos_funcionario SET criado_por = ? WHERE criado_por = ?',
            'UPDATE pagamentos_socio SET criado_por = ? WHERE criado_por = ?',
            'UPDATE despesas SET criado_por = ? WHERE criado_por = ?',
            'UPDATE convites SET criado_por = ? WHERE criado_por = ?',
        ];
        foreach ($reatrib as $sql) {
            try {
                $db->prepare($sql)->execute([$acting_user_id, $user_id]);
            } catch (PDOException $e) {
                // Tabela pode não existir (migration ainda não aplicada) — ignora.
            }
        }

        // ============================================================
        // 2. APAGA registros "do" usuário (dados pessoais/financeiros)
        // ============================================================

        // Pagamentos recebidos como funcionário → CASCADE em pagamento_funcionario_itens
        try {
            $db->prepare('DELETE FROM pagamentos_funcionario WHERE funcionario_id = ?')->execute([$user_id]);
        } catch (PDOException $e) {}

        // Pagamentos recebidos como sócio
        try {
            $db->prepare('DELETE FROM pagamentos_socio WHERE socio_id = ?')->execute([$user_id]);
        } catch (PDOException $e) {}

        // Entregas que ele executou (FK RESTRICT — precisa apagar antes do usuário)
        try {
            $db->prepare('DELETE FROM entregas WHERE funcionario_id = ?')->execute([$user_id]);
        } catch (PDOException $e) {}

        // Capacidade declarada e valores por item (FKs já são CASCADE, mas explicitar não dói)
        try { $db->prepare('DELETE FROM capacidade_funcionario WHERE funcionario_id = ?')->execute([$user_id]); } catch (PDOException $e) {}
        try { $db->prepare('DELETE FROM func_servico_pagamento WHERE funcionario_id = ?')->execute([$user_id]); } catch (PDOException $e) {}

        // Resets de senha pendentes (CASCADE)
        try { $db->prepare('DELETE FROM password_resets WHERE usuario_id = ?')->execute([$user_id]); } catch (PDOException $e) {}

        // ============================================================
        // 3. Finalmente apaga o usuário
        //    assinaturas.funcionario_id é SET NULL → automático
        //    cobrancas.funcionario_id é SET NULL → automático
        //    audit_log.usuario_id é SET NULL → automático
        // ============================================================
        $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$user_id]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
