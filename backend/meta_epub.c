/*
 * Fetches epub metadata using libepub.
 * This file is part of the elibsrv project.
 *
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
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#include "meta_common.h"


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


/* strips the 'type' and 'comment' parts of an epub entry, if any. example:
 * creator: Jules Vernes (author)  ->  Jules Vernes */
static void striptype(char *dst, char *src) {
  char *s;
  s = strstr((char *) src, ": ");
  if (s != NULL) sprintf(dst, "%s", s + 2);
  s = strstr(dst, "(");
  if (s != NULL) *s = 0;
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


struct meta *meta_epub_get(char *ebookfilename) {
  char *pubfilter[] = { "publication:", "original-publication:", "18", "19", "20", NULL };
  char *modfilter[] = { "modification:", NULL };
  struct epub *epubfile;
  struct meta *res;

  epubfile = epub_open(ebookfilename, 0 /* debug */);
  if (epubfile == NULL) return(NULL);

  res = calloc(sizeof(struct meta), 1);

  /* fetch metadata */
  res->title = get_epub_single_data(epubfile, EPUB_TITLE, NULL, NULL);
  res->author = get_epub_single_data(epubfile, EPUB_CREATOR, NULL, NULL);
  res->lang = get_epub_single_data(epubfile, EPUB_LANG, NULL, NULL);
  res->desc = get_epub_single_data(epubfile, EPUB_DESCRIPTION, NULL, NULL);
  res->tags = get_epub_data(epubfile, EPUB_SUBJECT, 64, NULL);
  res->publisher = get_epub_single_data(epubfile, EPUB_PUBLISHER, NULL, NULL);
  res->pubdate = get_epub_single_data(epubfile, EPUB_DATE, NULL, pubfilter);
  res->moddate = get_epub_single_data(epubfile, EPUB_DATE, NULL, modfilter);

  epub_close(epubfile);
  epub_cleanup();
  return(res);
}
