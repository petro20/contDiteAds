-- ============================================================
-- migration_018: cobrança fixa mensal por assinatura (override)
-- ============================================================
-- Permite marcar UMA assinatura específica para entrar na cobrança
-- mensal como VALOR FIXO, ignorando a regra de "entregas do mês anterior"
-- dos itens por_unidade.
--
-- Caso de uso: item por_unidade que, para um cliente específico, é
-- faturado como mensalidade fixa (ex: "CFA - Criativo Fotográfico" do
-- cliente Odonto Florida LLC). Não altera o catálogo nem outros clientes.
--
-- Aplicar via phpMyAdmin (banco u788472657_contditeads).

ALTER TABLE assinaturas
    ADD COLUMN IF NOT EXISTS cobrar_fixo_mensal TINYINT(1) NOT NULL DEFAULT 0 AFTER valor_cobrado;
