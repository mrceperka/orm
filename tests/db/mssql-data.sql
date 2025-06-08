DELETE FROM books_x_tags;
DELETE FROM publishers_x_tags;
DELETE FROM books;
DELETE FROM eans;
DELETE FROM tags;
DELETE FROM authors;
DELETE FROM publishers;
DELETE FROM tag_followers;
DELETE FROM contents;
DELETE FROM users_x_users;
DELETE FROM user_stats;
DELETE FROM users;
DELETE FROM logs;

SET IDENTITY_INSERT authors ON;
INSERT INTO authors (id, name, web, born_on) VALUES (1, 'Writer 1', 'http://example.com/1', NULL);
INSERT INTO authors (id, name, web, born_on) VALUES (2, 'Writer 2', 'http://example.com/2', NULL);
SET IDENTITY_INSERT authors OFF;

DBCC checkident ('authors', reseed, 2) WITH NO_INFOMSGS;

SET IDENTITY_INSERT publishers ON;
INSERT INTO publishers (publisher_id, name) VALUES (1, 'Nextras publisher A');
INSERT INTO publishers (publisher_id, name) VALUES (2, 'Nextras publisher B');
INSERT INTO publishers (publisher_id, name) VALUES (3, 'Nextras publisher C');
SET IDENTITY_INSERT publishers OFF;

DBCC checkident ('publishers', reseed, 3) WITH NO_INFOMSGS;

SET IDENTITY_INSERT tags ON;
INSERT INTO tags (id, name, is_global) VALUES (1, 'Tag 1', 'y');
INSERT INTO tags (id, name, is_global) VALUES (2, 'Tag 2', 'y');
INSERT INTO tags (id, name, is_global) VALUES (3, 'Tag 3', 'n');
SET IDENTITY_INSERT tags OFF;

DBCC checkident ('tags', reseed, 3) WITH NO_INFOMSGS;

SET IDENTITY_INSERT books ON;
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, genre, price, price_currency) VALUES (1, 1, 1, 'Book 1', NULL, 1, '2021-12-14 21:10:04', 'sciFi', 50, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, genre, price, price_currency) VALUES (2, 1, NULL, 'Book 2', NULL, 2, '2021-12-14 21:10:02', 'horror', 150, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, genre, price, price_currency) VALUES (3, 2, 2, 'Book 3', NULL, 3, '2021-12-14 21:10:03', 'thriller', 20, 'CZK');
INSERT INTO books (id, author_id, translator_id, title, next_part, publisher_id, published_at, genre, price, price_currency) VALUES (4, 2, 2, 'Book 4', 3, 1, '2021-12-14 21:10:01', 'romance', 220, 'CZK');
SET IDENTITY_INSERT books OFF;

DBCC checkident ('books', reseed, 4) WITH NO_INFOMSGS;

DBCC checkident ('eans', reseed, 1) WITH NO_INFOMSGS;

INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 1);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (1, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 2);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (2, 3);
INSERT INTO books_x_tags (book_id, tag_id) VALUES (3, 3);

INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (1, 1, '2014-01-01 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (3, 1, '2014-01-02 00:10:00');
INSERT INTO tag_followers (tag_id, author_id, created_at) VALUES (2, 2, '2014-01-03 00:10:00');

INSERT INTO contents (id, type, thread_id, replied_at) VALUES (1, 'thread', NULL, NULL);
INSERT INTO contents (id, type, thread_id, replied_at) VALUES (2, 'comment', 1, '2020-01-01 12:00:00');
INSERT INTO contents (id, type, thread_id, replied_at) VALUES (3, 'comment', 1, '2020-01-02 12:00:00');
