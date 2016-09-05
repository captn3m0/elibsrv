/*
 * This file is part of the elibsrv project
 * Copyright (C) 2015-2016 Mateusz Viste
 *
 * This is a very ugly wrapper around the (C++) poppler library, that provides
 * a simple way to fetch basic metadata from a PDF file.
 */

#ifndef pdfmeta_h_sentinel
#define pdfmeta_h_sentinel

#ifdef __cplusplus
extern "C" {
#endif

struct meta *meta_pdf_get(char *file);

#ifdef __cplusplus
}
#endif

#endif
