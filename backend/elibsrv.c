/*
 * elibsrv - a light OPDS indexing server for EPUB ebooks.
 *
 * Copyright (C) Mateusz Viste 2014
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
#include "libsql.h"
#include "libgetconf.h"
#include "crc32.h"


static char **get_epub_data(struct epub *epub, int type, int maxepubentries) {
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
      /* if we are dealing with a CREATOR, then strip leading type and extract the 'fileAs' part */
      if (type == EPUB_CREATOR) {
        char *s;
        s = strstr((char *) meta[i], ": ");
        if (s != NULL) sprintf(buff, "%s", s + 2);
        s = strstr(buff, "(");
        if (s != NULL) *s = 0;
      }
      /* if result is not empty, save it */
      if (buff[0] != 0) {
        result[rescount++] = strdup(buff);
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

static char *get_epub_single_data(struct epub *epub, int type, char *ifempty) {
  char **res, *singleres = NULL;
  int i;
  res = get_epub_data(epub, type, 1);
  /* compute the result */
  if ((res != NULL) && (res[0] != NULL)) {
      singleres = strdup(res[0]);
    } else {
      if (ifempty != NULL) {
          singleres = strdup(ifempty);
        } else {
          singleres = strdup(" ");
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

/* compute CRC32 of a file. returns 0 on success, non-zero otherwise. */
static int computecrc32(unsigned long *crc32, char *filename) {
  #define crcbuflen 8192
  unsigned char buf[crcbuflen];
  FILE *fd;
  size_t len;
  *crc32 = crc32_init();
  fd = fopen(filename, "rb");
  if (fd == NULL) return(1);
  for (;;) {
    len = fread(buf, 1, crcbuflen, fd);
    if (len == 0) break; /* eof */
    crc32_feed(crc32, buf, len);
  }
  fclose(fd);
  crc32_finish(crc32);
  return(0);
}


int main(int argc, char **argv) {
  #define SQLBUFLEN 1024 * 1024
  #define FILENAMELEN 1024
  int verbosemode = 0;
  char *title;
  char *sqltitle;
  char *desc;
  char *sqldesc;
  char *author;
  char *sqlauthor;
  char *lang;
  char *sqllang;
  char *sqlbuf;
  char **tags;
  struct epub *epubfile;
  char epubfilename[FILENAMELEN];
  char *sqlepubfilename;
  unsigned long crc32;
  char *dbaddr;
  char *dbname;
  char *dbuser;
  char *dbpass;
  int i;
  char *configfile;

  /* elibsrv must be called with exactly one parameter, and the parameter can't be empty or start with a dash */
  if ((argc < 2) || (argc > 3) || (argv[1][0] == '-') || (argv[1][0] == 0)) {
    puts("Usage: find /pathtoepubs/ -name *.epub | elibsrv /etc/elibsrv.conf [-v]");
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
  dbaddr = getconf_findvalue(NULL, "dbaddr");
  dbname = getconf_findvalue(NULL, "dbname");
  dbuser = getconf_findvalue(NULL, "dbuser");
  dbpass = getconf_findvalue(NULL, "dbpass");

  sqlbuf = malloc(SQLBUFLEN);

  /* connect to the sql db */
  if (libsql_connect(dbaddr, 5432, dbname, dbuser, dbpass) != 0) {
    fprintf(stderr, "Error: failed to connect to the sql database '%s' on host %s (defined in %s).\n", dbname, dbaddr, configfile);
    return(1);
  } else if (verbosemode != 0) {
    printf("Connected to db '%s' on host '%s'\n", dbname, dbaddr);
  }

  libsql_sendreq("SET client_min_messages TO WARNING;"); /* this required to shut up the damn thing from outputing messages about implicit index creation during a CREATE TEMP TABLE... */
  libsql_sendreq("BEGIN;");
  libsql_sendreq("CREATE TEMP TABLE tempbooks (crc32 BIGINT NOT NULL PRIMARY KEY, modtime TIMESTAMP WITHOUT TIME ZONE NOT NULL);");
  libsql_sendreq("INSERT INTO tempbooks (crc32, modtime) SELECT crc32, modtime FROM books;");
  libsql_sendreq("DELETE FROM tags;");
  libsql_sendreq("DELETE FROM books;"); /* I could use TRUNCATE here for much better performances, but the problem with TRUNCATE is that it takes an exclusive lock on the table, so the system would become unavailable during indexation time */
  for (;;) {
    int duplicatefound = 0;

    /* fetch the filename to process from stdin */
    if (fgets(epubfilename, FILENAMELEN, stdin) == NULL) break;
    trimlf(epubfilename); /* trim the possible LF trailer from the filename (fgets appends it) */

    /* compute crc32 */
    if (computecrc32(&crc32, epubfilename) != 0) {
      fprintf(stderr, "Failed to access file: %s\n", epubfilename);
      continue;
    } else if (verbosemode != 0) {
      printf("Will analyze file '%s' (CRC32 %08lX)\n", epubfilename, crc32);
    }

    /* First of all, check if the file is not already in db */
    snprintf(sqlbuf, SQLBUFLEN, "SELECT file FROM books WHERE crc32=%lu;", crc32);
    if (libsql_sendreq(sqlbuf) != 0) {
      fprintf(stderr, "SQL ERROR when trying this (please check your SQL logs): %s\n", sqlbuf);
      libsql_sendreq("ROLLBACK;");
      break;
    }
    if (libsql_nextresult() == 0) { /* file already exists in db */
      fprintf(stderr, "WARNING: file '%s' is an exact copy of '%s'. The former won't be indexed.\n", epubfilename, libsql_getresult(0));
      duplicatefound = 1;
    }
    libsql_freeresult();
    if (duplicatefound != 0) continue;

    epubfile = epub_open(epubfilename, 0 /* debug */);
    if (epubfile == NULL) {
      fprintf(stderr, "Failed to analyze file: %s\n", epubfilename);
      continue;
    }
    /* fetch metadata */
    title = get_epub_single_data(epubfile, EPUB_TITLE, "UNTITLED");
    author = get_epub_single_data(epubfile, EPUB_CREATOR, "UNKNOWN");
    lang = get_epub_single_data(epubfile, EPUB_LANG, "UND");
    desc = get_epub_single_data(epubfile, EPUB_DESCRIPTION, NULL);
    tags = get_epub_data(epubfile, EPUB_SUBJECT, 64);
    epub_close(epubfile);

    if (verbosemode != 0) {
      printf("------[ processing: %s ]------\n", epubfilename);
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

    sqlepubfilename = libsql_escape_string(epubfilename, strlen(epubfilename));
    sqltitle = libsql_escape_string(title, strlen(title));
    sqlauthor = libsql_escape_string(author, strlen(author));
    sqllang = libsql_escape_string(lang, strlen(lang));
    sqldesc = libsql_escape_string(desc, strlen(desc));

    /* free original strings */
    free(title);
    free(author);
    free(lang);
    free(desc);

    /* Insert the value into SQL */
    snprintf(sqlbuf, SQLBUFLEN, "INSERT INTO books (crc32,file,author,title,language,description,modtime) VALUES (%lu,%s,%s,%s,lower(%s),%s,(SELECT COALESCE((SELECT modtime FROM tempbooks WHERE crc32=%lu), NOW())));", crc32, sqlepubfilename, sqlauthor, sqltitle, sqllang, sqldesc, crc32);
    if (libsql_sendreq(sqlbuf) != 0) {
      fprintf(stderr, "SQL ERROR when trying this (please check your SQL logs): %s\n", sqlbuf);
      libsql_sendreq("ROLLBACK;");
      break;
    }

    /* free the sql strings */
    free(sqltitle);
    free(sqlauthor);
    free(sqllang);
    free(sqldesc);

    /* insert and free tags */
    for (i = 0; (tags != NULL) && (tags[i] != NULL); i++) {
      char *sqltag;
      int skip;
      skip = 0;
      if (strstr(tags[i], "http://") == tags[i]) skip = 1; /* skip tags that are web urls */
      if (skip == 0) {
        sqltag = libsql_escape_string(tags[i], strlen(tags[i]));
        snprintf(sqlbuf, SQLBUFLEN, "INSERT INTO tags (book,tag) VALUES (%lu,lower(%s));", crc32, sqltag);
        if (libsql_sendreq(sqlbuf) != 0) {
          fprintf(stderr, "SQL ERROR when trying this / please check your SQL logs: %s\n", sqlbuf);
          libsql_sendreq("ROLLBACK;");
          break;
        }
        free(sqltag);
      }
      free(tags[i]);
    }
    free(tags);

  }
  libsql_sendreq("DROP TABLE tempbooks;"); /* not really necessary for a TEMP table, but let's do it anyway just for completness */
  libsql_sendreq("COMMIT;");
  epub_cleanup();
  free(sqlbuf);
  libsql_disconnect(); /* disconnect from the sql db */
  return(0);
}
