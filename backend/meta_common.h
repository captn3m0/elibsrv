#ifndef META_H_SENTINEL

struct meta {
  char *title;
  char *author;
  char *lang;
  char *desc;
  char **tags;
  char *publisher;
  char *pubdate;
  char *moddate;
};

#include "meta_pdf.h"
#include "meta_epub.h"

void meta_free(struct meta *meta);

#endif
