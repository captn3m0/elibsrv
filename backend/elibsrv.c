/*
 * elibsrv - a light OPDS indexing server for EPUB ebooks.
 * http://elibsrv.sourceforge.net
 *
 * Copyright (C) 2014-2016 Mateusz Viste
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


#include <epub.h>   /* libepub headers */
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>

#include "crc32.h"
#include "libsql.h"
#include "libgetconf.h"
#include "pdfmeta.h"


enum EBOOKFORMATS {
  FORMAT_EPUB = 0,
  FORMAT_PDF = 1
};


/* returns the format of an ebook */
static int getformat(char *filename) {
  char *ext;
  /* identify the extension first */
  ext = strrchr(filename, '.');
  if (ext == NULL) return(-1);
  ext += 1;
  /* */
  if (strcasecmp("epub", ext) == 0) {
    return(FORMAT_EPUB);
  } else if (strcasecmp("pdf", ext) == 0) {
    return(FORMAT_PDF);
  } else { /* else it's an unknown format */
    return(-1);
  }
}


/* strips the 'type' and 'comment' parts of an epub entry, if any. example:
 * creator: Jules Vernes (author)  ->  Jules Vernes */
static void striptype(char *dst, char *src) {
  char *s;
  s = strstr((char *) src, ": ");
  if (s != NULL) sprintf(dst, "%s", s + 2);
  s = strstr(dst, "(");
  if (s != NULL) *s = 0;
}


/* returns 0 str doesn't start with one of the strings listed in the 'start'
 * array, non-zero otherwise */
static int stringstartswith(char **list, char *s) {
  int i;
  if (list == NULL) return(1);
  for (i = 0; list[i] != NULL; i++) {
    char *str = s;
    for (;;) {
      if (*list[i] == 0) return(1);
      if (*list[i] != *str) break;
      list[i] += 1;
      str += 1;
    }
  }
  return(0);
}


static char **get_epub_data(struct epub *epub, int type, int maxepubentries, char **filter) {
  unsigned char **meta;
  int metacount, i, rescount = 0;
  char *buff, **result = NULL;
  if (maxepubentries < 1) return(NULL);
  result = calloc(maxepubentries + 1, sizeof(char *));
  if (result == NULL) return(NULL);

  meta = epub_get_metadata(epub, type, &metacount);

  if (meta == NULL) return(NULL);

  /* copy values to new buffers */
  for (i = 0; i < metacount; i++) {
    if (meta[i] != NULL) {
      buff = strdup((char *) meta[i]);
      /* if filter set, and no match, ignore */
      if (stringstartswith(filter, buff) == 0) {
        free(buff);
        continue;
      }
      /* if we are dealing with a CREATOR or DATE, then strip leading type and extract the 'fileAs' part */
      if ((type == EPUB_CREATOR) || (type = EPUB_DATE)) {
        striptype(buff, (char *)meta[i]);
      }
      /* if result is not empty, save it (and ltrim at the same occasion) */
      if (buff[0] != 0) {
        int firstchar = 0;
        while (buff[firstchar] == ' ' || buff[firstchar] == '\t') firstchar++;
        result[rescount++] = strdup(buff + firstchar);
      }
      free(buff);
      if (rescount >= maxepubentries) break;
    }
  }

  /* free the meta allocated memory */
  while (metacount > 0) {
    metacount -= 1;
    if (meta[metacount] != NULL) free(meta[metacount]);
  }
  free(meta);
  result[rescount] = NULL;
  return(result);
}


static char *get_epub_single_data(struct epub *epub, int type, char *ifempty, char **filter) {
  char **res, *singleres = NULL;
  int i;
  res = get_epub_data(epub, type, 1, filter);
  /* compute the result */
  if ((res != NULL) && (res[0] != NULL)) {
    singleres = strdup(res[0]);
  } else {
    if (ifempty != NULL) {
      singleres = strdup(ifempty);
    } else {
      singleres = strdup("");
    }
  }
  if (res != NULL) {
    /* free all results */
    for (i = 0;; i++) {
      if (res[i] != NULL) {
        free(res[i]);
      } else {
        break;
      }
    }
    free(res);
  }
  /* return the final result */
  return(singleres);
}


static void trimlf(char *str) {
  while (*str != 0) {
    if ((*str == '\n') || (*str == '\r')) break;
    str += 1;
  }
  *str = 0;
}


/* compute CRC32 of a file. returns filesize on success, negative value otherwise. */
static long computecrc32(unsigned long *crc32, char *filename) {
  #define crcbuflen 8192
  unsigned char buf[crcbuflen];
  long fsize = 0;
  FILE *fd;
  size_t len;
  *crc32 = crc32_init();
  fd = fopen(filename, "rb");
  if (fd == NULL) return(-1);
  for (;;) {
    len = fread(buf, 1, crcbuflen, fd);
    if (len == 0) break; /* eof */
    fsize += len;
    crc32_feed(crc32, buf, len);
  }
  fclose(fd);
  crc32_finish(crc32);
  return(fsize);
}


/* validate lang - returns 0 if seems correct, non-zero otherwise */
static int validlang(char *lang) {
  if (lang == NULL) return(-1);
  if ((lang[0] < 'a') || (lang[1] > 'z')) return(-2);
  /* detect xx-xx countries, and simplify to xx */
  if ((lang[2] == '-') && (lang[3] >= 'a') && (lang[3] <= 'z') && (lang[4] >= 'a') && (lang[4] <= 'z') && (lang[5] == 0)) {
    lang[2] = 0;
  }
  if (lang[2] != 0) return(-3);
  return(0);
}


int main(int argc, char **argv) {
  #define SQLBUFLEN 1024 * 1024
  #define FILENAMELEN 1024
  int verbosemode = 0;
  char *title, *sqltitle;
  char *desc, *sqldesc;
  char *author, *sqlauthor;
  char *publisher, *sqlpublisher;
  char *pubdate, *sqlpubdate;
  char *moddate, *sqlmoddate;
  char *lang, *sqllang;
  char *sqlbuf;
  char **tags;
  char ebookfilename[FILENAMELEN];
  char *sqlepubfilename;
  unsigned long crc32;
  char *dbfile;
  int i;
  int format; /* 0 = epub ; 1 = pdf */
  char *configfile;

  /* SQL schema */
  char *sqlschema[] = {
    "CREATE TABLE IF NOT EXISTS books (crc32 INTEGER PRIMARY KEY, file TEXT NOT NULL UNIQUE, format INTEGER NOT NULL, author TEXT NOT NULL, title TEXT NOT NULL, language TEXT NOT NULL, description TEXT NOT NULL, publisher TEXT NOT NULL, pubdate TEXT NOT NULL, moddate TEXT NOT NULL, filesize INTEGER NOT NULL, modtime INTEGER NOT NULL);",
    "CREATE INDEX IF NOT EXISTS books_author_idx ON books(author);",
    "CREATE INDEX IF NOT EXISTS books_title_idx ON books(title);",
    "CREATE INDEX IF NOT EXISTS books_language_idx ON books(language);",
    "CREATE INDEX IF NOT EXISTS books_modtime_idx ON books(modtime);",
    "CREATE TABLE IF NOT EXISTS tags (book INTEGER NOT NULL, tag TEXT NOT NULL, FOREIGN KEY(book) REFERENCES books(crc32));",
    "CREATE INDEX IF NOT EXISTS tags_book_idx ON tags(book);",
    "CREATE INDEX IF NOT EXISTS tags_book_tag ON tags(tag);",
    "CREATE TEMP TABLE tempbooks (crc32 INTEGER PRIMARY KEY, file TEXT NOT NULL UNIQUE, present INTEGER NOT NULL);",
    NULL };

  /* elibsrv must be called with exactly one parameter, and the parameter can't be empty or start with a dash */
  if ((argc < 2) || (argc > 3) || (argv[1][0] == '-') || (argv[1][0] == 0)) {
    puts("Usage example: find /pathtoepubs/ -iname *\\.epub | elibsrv /etc/elibsrv.conf [-v]");
    return(1);
  }

  if (argc == 3) {
    if ((argv[2][0] != '-') || (argv[2][1] != 'v') || (argv[2][2] != 0)) {
      puts("Unknown command-line parameter received.");
      return(1);
    } else {
      verbosemode = 1;
    }
  }

  configfile = argv[1];

  /* read configuration */
  if (loadconf(configfile) != 0) {
    fprintf(stderr, "Error: failed to read configuration file at %s\n", configfile);
    return(1);
  } else if (verbosemode != 0) {
    printf("Loaded configuration file at %s\n", configfile);
  }
  dbfile = getconf_findvalue(NULL, "dbfile");
  if ((dbfile == NULL) || (*dbfile == 0)) {
    fprintf(stderr, "Error: no dbfile set in config file\n");
    return(1);
  }

  sqlbuf = malloc(SQLBUFLEN);

  /* connect to the sql db */
  if (libsql_connect(dbfile) != 0) {
    fprintf(stderr, "Error: failed to connect to the sql database '%s' (defined in %s).\n", dbfile, configfile);
    return(1);
  } else if (verbosemode != 0) {
    printf("Connected to db '%s'\n", dbfile);
  }

  /* SQL schema refresh */
  for (i = 0; sqlschema[i] != NULL; i++) {
    if (libsql_sendreq(sqlschema[i], 1) != 0) {
      fprintf(stderr, "Error: failed to init SQL schema\n");
      return(1);
    }
  }

  /* copy all CRCs into the temp table */
  if (libsql_sendreq("INSERT INTO tempbooks (crc32,file,present) SELECT crc32,file,0 FROM books;", 1) != 0) {
    fprintf(stderr, "Error: unexpected SQL failure\n");
    return(1);
  }
  for (;;) {
    long fsize;
    int present;
    char *presentfile;

    /* fetch the filename to process from stdin */
    if (fgets(ebookfilename, FILENAMELEN, stdin) == NULL) break;
    trimlf(ebookfilename); /* trim the possible LF trailer from the filename (fgets appends it) */

    /* compute crc32 and get filesize */
    fsize = computecrc32(&crc32, ebookfilename);
    if (fsize < 0) {
      fprintf(stderr, "Failed to access file: %s\n", ebookfilename);
      continue;
    } else if (verbosemode != 0) {
      printf("Will analyze file '%s' (CRC32 %08lX)\n", ebookfilename, crc32);
    }

    /* First of all, check if the file is not already in db */
    snprintf(sqlbuf, SQLBUFLEN, "SELECT file,present FROM tempbooks WHERE crc32=%lu;", crc32);
    if (libsql_sendreq(sqlbuf, 0) != 0) {
      fprintf(stderr, "SQL ERROR when trying this (please check your SQL logs): %s\n", sqlbuf);
      break;
    }
    present = -1;
    presentfile = NULL;
    if (libsql_nextresult() == 0) {
      present = atoi(libsql_getresult(1));
      presentfile = strdup(libsql_getresult(0));
    }
    libsql_freeresult();

    if (present > 0) { /* file already exists in db */
      fprintf(stderr, "WARNING: file '%s' is an exact copy of '%s'. The former won't be indexed.\n", ebookfilename, presentfile);
      free(presentfile);
      continue;
    } else if (present == 0) { /* file was already existing, simply mark it as found, and update the filename if needed */
      sqlepubfilename = libsql_escape_string(ebookfilename);
      snprintf(sqlbuf, SQLBUFLEN, "UPDATE tempbooks SET present=1,file=%s WHERE crc32=%lu;", sqlepubfilename, crc32);
      libsql_sendreq(sqlbuf, 1);
      if (strcmp(ebookfilename, presentfile) != 0) {
        snprintf(sqlbuf, SQLBUFLEN, "UPDATE books SET file=%s WHERE crc32=%lu;", sqlepubfilename, crc32);
        libsql_sendreq(sqlbuf, 1);
      }
      free(sqlepubfilename);
      free(presentfile);
      continue;
    }

    /* if I'm here, it means this is a completely NEW file */

    /* what format is it? */
    format = getformat(ebookfilename);
    if (format < 0) {
      fprintf(stderr, "WARNING: file '%s' is of unknown format\n", ebookfilename);
      continue;
    }

    /* fetch ebook's metadata, depending on what format it is */
    title = NULL;
    author = NULL;
    lang = NULL;
    desc = NULL;
    tags = NULL;
    publisher = NULL;
    pubdate = NULL;
    moddate = NULL;

    if (format == FORMAT_EPUB) { /* EPUB */
      char *pubfilter[] = { "publication:", "original-publication:", "18", "19", "20", NULL };
      char *modfilter[] = { "modification:", NULL };
      struct epub *epubfile;
      epubfile = epub_open(ebookfilename, 0 /* debug */);
      if (epubfile == NULL) {
        fprintf(stderr, "Failed to analyze file: %s\n", ebookfilename);
        continue;
      }
      /* fetch metadata */
      title = get_epub_single_data(epubfile, EPUB_TITLE, "UNTITLED", NULL);
      author = get_epub_single_data(epubfile, EPUB_CREATOR, "UNKNOWN", NULL);
      lang = get_epub_single_data(epubfile, EPUB_LANG, "UND", NULL);
      desc = get_epub_single_data(epubfile, EPUB_DESCRIPTION, NULL, NULL);
      tags = get_epub_data(epubfile, EPUB_SUBJECT, 64, NULL);
      publisher = get_epub_single_data(epubfile, EPUB_PUBLISHER, NULL, NULL);
      pubdate = get_epub_single_data(epubfile, EPUB_DATE, NULL, pubfilter);
      moddate = get_epub_single_data(epubfile, EPUB_DATE, NULL, modfilter);
      epub_close(epubfile);
      epub_cleanup();
    } else if (format == FORMAT_PDF) { /* PDF */
      struct pdfmeta *pdf;
      pdf = pdfmeta_get(ebookfilename);
      if (pdf != NULL) {
        if (pdf->title != NULL) title = strdup(pdf->title);
        if (pdf->author != NULL) author = strdup(pdf->author);
        if (pdf->subject != NULL) desc = strdup(pdf->subject);
        pdfmeta_free(pdf);
      }
    }

    /* validate the lang */
    if (validlang(lang) != 0) {
      free(lang);
      lang = NULL;
    }

    if (verbosemode != 0) {
      printf("------[ processing: %s ]------\n", ebookfilename);
      printf("Title: %s\n", title);
      printf("Author: %s\n", author);
      printf("Tags: ");
      for (i = 0; (tags != NULL) && (tags[i] != NULL); i++) {
        if (i > 0) printf(", ");
        printf("%s", tags[i]);
      }
      printf("\n");
      printf("Lang: %s\n", lang);
      printf("Desc: %s\n", desc);
    }

    sqlepubfilename = libsql_escape_string(ebookfilename);
    sqltitle = libsql_escape_string(title);
    sqlauthor = libsql_escape_string(author);
    sqllang = libsql_escape_string(lang);
    sqldesc = libsql_escape_string(desc);
    sqlpublisher = libsql_escape_string(publisher);
    sqlpubdate = libsql_escape_string(pubdate);
    sqlmoddate = libsql_escape_string(moddate);

    /* free original strings */
    free(title);
    free(author);
    free(lang);
    free(desc);
    free(publisher);
    free(pubdate);
    free(moddate);

    /* add the file to the tempbook table */
    snprintf(sqlbuf, SQLBUFLEN, "INSERT INTO tempbooks (crc32,file,present) VALUES (%lu,%s,1);", crc32, sqlepubfilename);
    libsql_sendreq(sqlbuf, 1);

    /* Insert the meta values into SQL */
    snprintf(sqlbuf, SQLBUFLEN, "INSERT INTO books (crc32,format,file,author,title,language,description,modtime,publisher,pubdate,moddate,filesize) VALUES (%lu,%d,%s,%s,%s,lower(%s),%s,strftime('%%s','now'),%s,%s,%s,%ld);", crc32, format, sqlepubfilename, sqlauthor, sqltitle, sqllang, sqldesc, sqlpublisher, sqlpubdate, sqlmoddate, fsize);
    if (libsql_sendreq(sqlbuf, 1) != 0) {
      fprintf(stderr, "SQL ERROR when trying this (please check your SQL logs): %s\n", sqlbuf);
      break;
    }

    /* free the sql strings */
    free(sqltitle);
    free(sqlauthor);
    free(sqllang);
    free(sqldesc);
    free(sqlpublisher);
    free(sqlpubdate);

    /* insert and free tags */
    for (i = 0; (tags != NULL) && (tags[i] != NULL); i++) {
      char *sqltag;
      int skip;
      skip = 0;
      if (strstr(tags[i], "http://") == tags[i]) skip = 1; /* skip tags that are web urls */
      if (skip == 0) {
        sqltag = libsql_escape_string(tags[i]);
        snprintf(sqlbuf, SQLBUFLEN, "INSERT INTO tags (book,tag) VALUES (%lu,lower(%s));", crc32, sqltag);
        if (libsql_sendreq(sqlbuf, 1) != 0) {
          fprintf(stderr, "SQL ERROR when trying this / please check your SQL logs: %s\n", sqlbuf);
          break;
        }
        free(sqltag);
      }
      free(tags[i]);
    }
    free(tags);
  }

  /* remove all ebooks that are no longer present on disk (along with their tags) */
  libsql_sendreq("DELETE FROM tags WHERE book IN (SELECT crc32 FROM tempbooks WHERE present=0);", 1);
  libsql_sendreq("DELETE FROM books WHERE crc32 IN (SELECT crc32 FROM tempbooks WHERE present=0);", 1);

  /* remove the temporary worker table - not really necessary for a TEMP table, but let's do it anyway just for completness */
  libsql_sendreq("DROP TABLE tempbooks;", 1);

  /* execute a VACUUM action so the db optimize itself */
  libsql_sendreq("VACUUM;", 1);

  free(sqlbuf);
  libsql_disconnect(); /* disconnect from the sql db */
  return(0);
}
