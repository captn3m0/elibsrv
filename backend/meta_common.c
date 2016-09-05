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

#include <stdlib.h>
#include <unistd.h>

#include "meta_common.h"

void meta_free(struct meta *meta) {
  int i;
  if (meta == NULL) return;
  free(meta->title);
  free(meta->author);
  free(meta->lang);
  free(meta->desc);
  free(meta->publisher);
  free(meta->pubdate);
  free(meta->moddate);

  /* free tags */
  if (meta->tags != NULL) {
    for (i = 0; meta->tags[i] != NULL; i++) {
      free(meta->tags[i]);
    }
    free(meta->tags);
  }

  free(meta);
}
