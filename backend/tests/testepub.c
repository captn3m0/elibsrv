/*
 * Reads an epub file and returns a few properties
 */

#include <epub.h>   /* libepub headers */
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>

#include "../meta_common.h"


int main(int argc, char **argv) {
  char *ebookfilename;
  struct meta *meta;

  if ((argc < 2) || (argv[1][0] == '-')) {
    printf("usage: testepub file.epub\n");
    return(1);
  }

  ebookfilename = argv[1];

  meta = meta_epub_get(ebookfilename);
  if (meta == NULL) {
    fprintf(stderr, "Failed to analyze file: %s\n", ebookfilename);
    return(2);
  }

  printf("title : '%s'\n", meta->title);
  printf("author: '%s'\n", meta->author);
  meta_free(meta);

  return(0);
}
