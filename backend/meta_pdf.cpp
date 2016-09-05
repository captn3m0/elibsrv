/*
 * This file is part of the elibsrv project
 * Copyright (C) 2015-2016 Mateusz Viste
 *
 * This is a very ugly wrapper around the (C++) poppler library, that provides
 * a simple way to fetch basic metadata from a PDF file.
 *
 * poppler-cpp doc:
 * http://marpirk.github.io/poppler-cpp-doc/annotated.html
 */

#include <iostream>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "poppler-document.h"
#include "poppler-page.h"

#include "meta_common.h"

using namespace std;



static char *extracttxt(char *haystack, const char *needle, const char *needleend) {
  char *res, *result, *end, endcharbackup;
  long len;
  res = strcasestr(haystack, needle);
  end = strcasestr(haystack, needleend);
  if ((res == NULL) || (end == NULL) || (end < res)) return(NULL);

  res += strlen(needle);
  endcharbackup = *end;
  *end = 0;

  /* find the first character that is not in some <tag> */
  for (;;) {
    /* skip all leading white spaces */
    while (*res == ' ' || *res == '\t' || *res == '\r' || *res == '\n') res++;
    if (*res != '<') break;
    /* skip everything inside the <tag> */
    while ((*res != '>') && (*res != 0)) res++;
    /* if not end of string, then get out of the tag */
    if (*res == '>') res++;
  }

  /* now end the field where the first '</' occurs */
  for (len = 0;; len++) {
    if ((res[len] == '<') && (res[len+1] == '/')) break;
    if (res[len] == 0) break;
  }

  /* restore the end char */
  *end = endcharbackup;

  /* copy the string to a new memory buffer and return a pointer to it */
  result = (char *)malloc(len + 1);
  if (result == NULL) return(NULL);
  memcpy(result, res, len);
  result[len] = 0;

  return(result);
}


static char *filename2title(char *filename) {
  char *bname;
  char *res;
  bname = strrchr(filename, '/');
  if (bname == NULL) {
    bname = filename;
  } else {
    bname += 1;
  }
  res = strdup(bname);
  /* trim the file extension */
  bname = strrchr(res, '.');
  if (bname != NULL) *bname = 0;
  return(res);
}


struct meta *meta_pdf_get(char *filename) {
  std::string str;
  poppler::document *data;
  poppler::ustring r;
  const char *meta;
  char *rawdata;
  struct meta *res;

  data = poppler::document::load_from_file(filename);
  if (data == NULL) return(NULL);

  r = data->metadata();
  str = r.to_latin1();
  meta = str.c_str();

  res = (struct meta *)calloc(sizeof(struct meta), 1);
  if (res == NULL) return(NULL);
  if (meta != NULL) {
    rawdata = strdup(meta);
    if (rawdata == NULL) {
      free(res);
      return(NULL);
    }

    res->title = extracttxt(rawdata, "<dc:title>", "</dc:title>");
    res->author = extracttxt(rawdata, "<dc:creator>", "</dc:creator>");
    res->desc = extracttxt(rawdata, "<dc:subject>", "</dc:subject>");

    free(rawdata);
  }

  /* if no title, or title empty, then fill the filename instead */
  if ((res->title == NULL) || (res->title[0] == 0)) {
    free(res->title);
    res->title = filename2title(filename);
  }

  return(res);
}
