START TRANSACTION;
INSERT INTO "public"."authors" ("name", "born_on", "web", "favorite_author_id") VALUES ('The Imp', '2000-01-01'::date, 'localhost', NULL);
SELECT CURRVAL('public.authors_id_seq');
INSERT INTO "publishers" ("name") VALUES ('Valyria');
SELECT CURRVAL('public.publishers_publisher_id_seq');
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('The Wall', 3, 3, NULL, NULL, 4, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (3) ORDER BY "books"."id" DESC;
SELECT
  "books_x_tags"."tag_id",
  "books_x_tags"."book_id"
FROM
  "tags" AS "tags"
  LEFT JOIN "books_x_tags" AS "books_x_tags" ON (
    "books_x_tags"."tag_id" = "tags"."id"
  )
WHERE
  "books_x_tags"."book_id" IN (5);

SELECT "books".* FROM "books" AS "books" WHERE "books"."next_part" IN (5);
START TRANSACTION;
DELETE FROM "books" WHERE "id" = 5;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (3) ORDER BY "books"."id" DESC;
COMMIT;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (3) ORDER BY "books"."id" DESC;
START TRANSACTION;
INSERT INTO "books" ("title", "author_id", "translator_id", "next_part", "ean_id", "publisher_id", "genre", "published_at", "printed_at", "thread_id", "price", "price_currency", "orig_price_cents", "orig_price_currency") VALUES ('The Wall III', 3, NULL, NULL, NULL, 4, 'fantasy', '2021-12-31 23:59:59.000000'::timestamp, NULL, NULL, NULL, NULL, NULL, NULL);
SELECT CURRVAL('public.books_id_seq');
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (3) ORDER BY "books"."id" DESC;
