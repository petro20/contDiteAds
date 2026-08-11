-- ============================================================
-- auditoria_orfaos.sql — caça a "dados soltos" (read-only)
-- ------------------------------------------------------------
-- SÓ SELECTs. Não altera nada. Cole o arquivo inteiro no
-- phpMyAdmin (banco u788472657_contditeads) e rode.
-- Cada bloco devolve: um rótulo, a contagem e uma amostra de IDs.
-- Se "qtd" = 0 em todos, não há dados soltos.
--
-- Legenda do "tipo":
--   ORFAO FISICO  = FK inexistente no schema; pode apontar p/ id que sumiu
--   ORFAO LOGICO  = FK ON DELETE SET NULL; pai foi apagado, filho ficou NULL
--   INCONSISTENTE = viola uma regra de negócio (soma, status, par de colunas)
--   INFO          = provável cadastro solto; revisar, não necessariamente erro
-- ============================================================

-- 1) dite_eventos apontando cobrança que não existe  (sem FK — migration_019)
SELECT 'ORFAO FISICO' tipo, 'dite_eventos.cobranca_id inexistente' checagem,
       COUNT(*) qtd, GROUP_CONCAT(d.id ORDER BY d.id SEPARATOR ',') amostra_ids
FROM dite_eventos d
LEFT JOIN cobrancas c ON c.id = d.cobranca_id
WHERE d.cobranca_id IS NOT NULL AND c.id IS NULL;

-- 2) dite_eventos apontando pagamento que não existe
SELECT 'ORFAO FISICO', 'dite_eventos.pagamento_id inexistente',
       COUNT(*), GROUP_CONCAT(d.id ORDER BY d.id SEPARATOR ',')
FROM dite_eventos d
LEFT JOIN pagamentos_cliente p ON p.id = d.pagamento_id
WHERE d.pagamento_id IS NOT NULL AND p.id IS NULL;

-- 3) wise_eventos apontando cobrança que não existe  (FK SET NULL — checagem de sanidade)
SELECT 'ORFAO FISICO', 'wise_eventos.cobranca_id inexistente',
       COUNT(*), GROUP_CONCAT(w.id ORDER BY w.id SEPARATOR ',')
FROM wise_eventos w
LEFT JOIN cobrancas c ON c.id = w.cobranca_id
WHERE w.cobranca_id IS NOT NULL AND c.id IS NULL;

-- 4) wise_eventos apontando pagamento que não existe
SELECT 'ORFAO FISICO', 'wise_eventos.pagamento_id inexistente',
       COUNT(*), GROUP_CONCAT(w.id ORDER BY w.id SEPARATOR ',')
FROM wise_eventos w
LEFT JOIN pagamentos_cliente p ON p.id = w.pagamento_id
WHERE w.pagamento_id IS NOT NULL AND p.id IS NULL;

-- 5) cobrança sem NENHUM item (fatura vazia)
SELECT 'INCONSISTENTE', 'cobranca sem itens',
       COUNT(*), GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',')
FROM cobrancas c
LEFT JOIN cobranca_itens ci ON ci.cobranca_id = c.id
WHERE ci.id IS NULL AND c.status <> 'cancelada';

-- 6) cobrança cujo valor_total != soma dos subtotais dos itens
SELECT 'INCONSISTENTE', 'valor_total != soma(itens)',
       COUNT(*), GROUP_CONCAT(t.id ORDER BY t.id SEPARATOR ',')
FROM (
  SELECT c.id, c.valor_total, COALESCE(SUM(ci.subtotal),0) soma
  FROM cobrancas c
  LEFT JOIN cobranca_itens ci ON ci.cobranca_id = c.id
  WHERE c.status <> 'cancelada'
  GROUP BY c.id, c.valor_total
) t
WHERE ABS(t.valor_total - t.soma) > 0.01;

-- 7) item de cobrança apontando assinatura que não existe
SELECT 'ORFAO FISICO', 'cobranca_itens.assinatura_id inexistente',
       COUNT(*), GROUP_CONCAT(ci.id ORDER BY ci.id SEPARATOR ',')
FROM cobranca_itens ci
LEFT JOIN assinaturas a ON a.id = ci.assinatura_id
WHERE ci.assinatura_id IS NOT NULL AND a.id IS NULL;

-- 8) item avulso (sem assinatura) com funcionário preenchido mas SEM valor a pagar,
--    ou com valor a pagar mas SEM funcionário — par quebrado (migration_022)
SELECT 'INCONSISTENTE', 'item avulso: funcionario x pagamento_func_usd descasados',
       COUNT(*), GROUP_CONCAT(ci.id ORDER BY ci.id SEPARATOR ',')
FROM cobranca_itens ci
WHERE ci.assinatura_id IS NULL
  AND ( (ci.funcionario_id IS NOT NULL AND ci.pagamento_func_usd IS NULL)
     OR (ci.funcionario_id IS NULL AND ci.pagamento_func_usd IS NOT NULL) );

-- 9) assinatura ATIVA sem funcionário responsável (SET NULL / nunca atribuído)
SELECT 'ORFAO LOGICO', 'assinatura ativa sem funcionario_id',
       COUNT(*), GROUP_CONCAT(a.id ORDER BY a.id SEPARATOR ',')
FROM assinaturas a
WHERE a.funcionario_id IS NULL AND a.status = 'ativa';

-- 10) usuário com papel 'cliente' porém sem cliente_id vinculado
SELECT 'ORFAO LOGICO', 'usuario role=cliente sem cliente_id',
       COUNT(*), GROUP_CONCAT(u.id ORDER BY u.id SEPARATOR ',')
FROM usuarios u
WHERE u.role = 'cliente' AND u.cliente_id IS NULL;

-- 11) usuário com cliente_id que aponta para cliente inexistente
SELECT 'ORFAO FISICO', 'usuario.cliente_id inexistente',
       COUNT(*), GROUP_CONCAT(u.id ORDER BY u.id SEPARATOR ',')
FROM usuarios u
LEFT JOIN clientes cl ON cl.id = u.cliente_id
WHERE u.cliente_id IS NOT NULL AND cl.id IS NULL;

-- 12) pagamento de lucro a sócio com sócio nulo (sócio apagado — SET NULL)
SELECT 'ORFAO LOGICO', 'pagamentos_socio.socio_id NULL',
       COUNT(*), GROUP_CONCAT(ps.id ORDER BY ps.id SEPARATOR ',')
FROM pagamentos_socio ps
WHERE ps.socio_id IS NULL;

-- 13) cobrança marcada como PAGA sem nenhum pagamento_cliente registrado
SELECT 'INCONSISTENTE', 'cobranca paga sem pagamento_cliente',
       COUNT(*), GROUP_CONCAT(c.id ORDER BY c.id SEPARATOR ',')
FROM cobrancas c
LEFT JOIN pagamentos_cliente p ON p.cobranca_id = c.id
WHERE c.status = 'paga' AND p.id IS NULL;

-- 14) rateio de pagamento de funcionário apontando item de cobrança inexistente
SELECT 'ORFAO FISICO', 'pagamento_funcionario_itens.cobranca_item_id inexistente',
       COUNT(*), GROUP_CONCAT(pfi.id ORDER BY pfi.id SEPARATOR ',')
FROM pagamento_funcionario_itens pfi
LEFT JOIN cobranca_itens ci ON ci.id = pfi.cobranca_item_id
WHERE pfi.cobranca_item_id IS NOT NULL AND ci.id IS NULL;

-- 15) cliente ATIVO sem NENHUMA assinatura e sem NENHUMA cobrança (cadastro solto)
SELECT 'INFO', 'cliente ativo sem assinatura e sem cobranca',
       COUNT(*), GROUP_CONCAT(cl.id ORDER BY cl.id SEPARATOR ',')
FROM clientes cl
WHERE cl.ativo = 1
  AND NOT EXISTS (SELECT 1 FROM assinaturas a WHERE a.cliente_id = cl.id)
  AND NOT EXISTS (SELECT 1 FROM cobrancas  c WHERE c.cliente_id = cl.id);

-- 16) TABELAS LEGADAS: nenhum código PHP usa 'pagamentos' nem 'servicos'
--     (o sistema usa pagamentos_cliente e itens_catalogo). Se houver linhas,
--     são dados mortos que sobraram do modelo antigo (migration_001).
--     Obs: se a tabela não existir no seu banco, o SELECT dá erro — ignore/pule.
SELECT 'INFO', 'linhas na tabela legada pagamentos', COUNT(*), NULL FROM pagamentos;
SELECT 'INFO', 'linhas na tabela legada servicos',   COUNT(*), NULL FROM servicos;
