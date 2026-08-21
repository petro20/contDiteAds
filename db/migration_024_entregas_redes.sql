-- migration_024_entregas_redes.sql
-- Agenda: registrar em quais redes sociais o conteúdo de cada dia foi postado.
-- Um dia marcado continua sendo UMA linha em `entregas`; a coluna guarda os slugs
-- das redes separados por vírgula na ordem canônica, ex.: "ig,fb,tiktok".
-- Aditiva: dias já marcados ficam com redes = NULL (renderizam sem ícones).
--
-- Rodar manualmente no phpMyAdmin (migrations não são automáticas neste projeto).

ALTER TABLE entregas
  ADD COLUMN redes VARCHAR(60) NULL AFTER data_marcada;
