START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('Author', '2021-03-21'::date, 'localhost', NULL);
SELECT CURRVAL('public.authors_id_seq');
