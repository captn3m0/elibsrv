/*
 * Reads an epub file and returns a few properties
 */

#include <epub.h>   /* libepub headers */
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>

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
      printf("found: %s\n", meta[i]); /* remove me */
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


int main(int argc, char **argv) {
  char *title = NULL, *ebookfilename;
  struct epub *epubfile;

  if ((argc < 2) || (argv[1][0] == '-')) {
    printf("usage: testepub file.epub\n");
    return(1);
  }

  ebookfilename = argv[1];

  epubfile = epub_open(ebookfilename, 0 /* debug */);
  if (epubfile == NULL) {
    fprintf(stderr, "Failed to analyze file: %s\n", ebookfilename);
    return(2);
  }

  title = get_epub_single_data(epubfile, EPUB_TITLE, "UNTITLED", NULL);

  printf("title: '%s'\n", title);

  free(title);
  epub_cleanup();

  return(0);
}
