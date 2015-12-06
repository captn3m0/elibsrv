BEGIN;

DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS books;

CREATE TABLE books (file VARCHAR NOT NULL,
                    crc32 BIGINT NOT NULL PRIMARY KEY,
                    author VARCHAR NOT NULL,
                    title VARCHAR NOT NULL,
                    language VARCHAR NOT NULL,
                    description VARCHAR NOT NULL,
                    publisher VARCHAR NOT NULL,
                    pubdate VARCHAR NOT NULL,
                    modtime TIMESTAMP WITHOUT TIME ZONE NOT NULL);
CREATE INDEX books_author_idx ON books(author);
CREATE INDEX books_title_idx ON books(title);
CREATE INDEX books_language_idx ON books(language);
CREATE INDEX books_modtime_idx ON books(modtime);

CREATE TABLE tags (book BIGINT NOT NULL REFERENCES books(crc32), tag VARCHAR NOT NULL);
CREATE INDEX tags_book_idx ON tags(book);
CREATE INDEX tags_book_tag ON tags(tag);

COMMIT;
