-- Откат миграции: удаление таблиц модуля обратной связи LIMIS
DROP TABLE IF EXISTS limis_tagasiside_nonce;
DROP TABLE IF EXISTS limis_tagasiside;
