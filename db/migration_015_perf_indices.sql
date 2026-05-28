-- Migration 015 — Índices de performance
--
-- Resolve dois gargalos identificados na auditoria:
--
-- 1. Listagem de cobranças pra funcionário (cobrancas.php:750)
--    SELECT ... FROM cobrancas WHERE EXISTS (... WHERE a.funcionario_id = ?)
--    Sem índice em assinaturas(funcionario_id), o MySQL fazia scan completo
--    da tabela inteira em cada cobrança da listagem.
--
-- 2. Cálculo de pagamentos por cobrança (chamado em N lugares)
--    SELECT SUM(valor_pago) FROM pagamentos_cliente WHERE cobranca_id = ? AND pendente = 0
--    Sem índice composto, MySQL faz full scan da tabela mesmo com cobranca_id já indexado.
--
-- Os IF NOT EXISTS evitam erro caso o índice já exista. MySQL 8 suporta sintaxe
-- direta; fallback via stored procedure pra compatibilidade.

-- 1. Índice em assinaturas.funcionario_id
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'assinaturas'
                  AND index_name = 'ix_assinaturas_funcionario');
SET @sql := IF(@exists = 0,
    'CREATE INDEX ix_assinaturas_funcionario ON assinaturas(funcionario_id, id)',
    'SELECT "ix_assinaturas_funcionario já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Índice composto em cobranca_itens (acelera EXISTS join com assinaturas)
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'cobranca_itens'
                  AND index_name = 'ix_cobitens_assin_cob');
SET @sql := IF(@exists = 0,
    'CREATE INDEX ix_cobitens_assin_cob ON cobranca_itens(assinatura_id, cobranca_id)',
    'SELECT "ix_cobitens_assin_cob já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Índice composto em pagamentos_cliente (filtrar por cobrança + pendente)
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'pagamentos_cliente'
                  AND index_name = 'ix_pagcliente_cob_pendente');
SET @sql := IF(@exists = 0,
    'CREATE INDEX ix_pagcliente_cob_pendente ON pagamentos_cliente(cobranca_id, pendente, valor_pago)',
    'SELECT "ix_pagcliente_cob_pendente já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Índice em cobrancas(status, vencimento) — usado em dashboard pra cobranças vencidas
SET @exists := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'cobrancas'
                  AND index_name = 'ix_cobrancas_status_venc');
SET @sql := IF(@exists = 0,
    'CREATE INDEX ix_cobrancas_status_venc ON cobrancas(status, vencimento)',
    'SELECT "ix_cobrancas_status_venc já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
