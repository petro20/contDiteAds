-- Migration 015 — Remove o campo de link público Wise
--
-- Motivo: webhook da Wise agora detecta pagamentos automaticamente.
-- O link manual deixou de ter utilidade. Cliente paga por Zelle ou por
-- transferência bancária direta (IBAN) e o webhook capta sozinho.
--
-- Esta migration:
--  1. Remove a chave 'pagamento_wise_link' da tabela configuracoes
--  2. Limpa referências a {link_wise} nos templates existentes
--     (substitui pela string vazia pra não quebrar templates legados)

DELETE FROM configuracoes WHERE chave = 'pagamento_wise_link';

-- Remove a variável {link_wise} dos templates ativos (substitui por vazio)
UPDATE templates_mensagem
   SET corpo = REPLACE(corpo, '{link_wise}', '')
 WHERE corpo LIKE '%{link_wise}%';
