/*
 * libsql - a generic interface to the SQL database.
 * Copyright (C) 2014-2016 Mateusz Viste
 *
 *  int libsql_connect(char *sqlserver, int sqlport, char *sqldatabase, char *sqluser, char *sqlpassword)
 *  void libsql_escape_string(char *fromstr, int fromlen)
 *  int libsql_sendreq(char *sqlcmd)
 *  int libsql_nextresult(void)
 *  char *libsql_getresult(int nField)
 *  int libsql_freeresult(void)
 *  void libsql_disconnect(void)
 *
 * elibsrv is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version. elibsrv is distributed in the hope that
 * it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
 * the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with elibsrv. If not, see <http://www.gnu.org/licenses/>.
 */


#include <string.h>
#include <stdlib.h>   /* for atoll() */
#include <stdio.h>    /* fprintf()... */
#include <sqlite3.h>
#include "libsql.h"   /* include self for control */

static sqlite3 *libsql_conn;
static sqlite3_stmt *libsql_res;


char *libsql_escape_string(char *fromstr) {
  char *zSQL, *res;
  if (fromstr == NULL) return(strdup("''")); /* if fromstr is NULL, return a copy of "''" */
  zSQL = sqlite3_mprintf("%Q", fromstr);
  res = strdup(zSQL);
  sqlite3_free(zSQL);
  return(res);
}


void libsql_disconnect(void) {
  sqlite3_close(libsql_conn);
  sqlite3_shutdown();
}


int libsql_connect(char *sqlfile) {
  int res;
  res = sqlite3_initialize();
  if (res != SQLITE_OK) {
    fprintf(stderr, "SQLITE ERROR: %s\n", sqlite3_errstr(res));
    return(-1);
  }
  res = sqlite3_open(sqlfile, &libsql_conn);
  if (res != SQLITE_OK) {
    fprintf(stderr, "SQLITE ERROR: %s\n", sqlite3_errstr(res));
    return(-1);
  }
  sqlite3_busy_timeout(libsql_conn, 20000); /* avoid returning "busy" errors during concurrent accesses */
  return(0);
}


int libsql_freeresult(void) {
  if (sqlite3_finalize(libsql_res) == SQLITE_OK) {
    libsql_res = NULL;
    return(0);
  }
  return(-1);
}


int libsql_sendreq(char *sqlcmd, int nores) {
  int res;
  res = sqlite3_prepare(libsql_conn, sqlcmd, -1, &libsql_res, NULL);
  if (res != SQLITE_OK) {
    fprintf(stderr, "failed to process sql request: %s\nerror msg: %s\n", sqlcmd, sqlite3_errstr(res));
    return(-1);
  }
  if (nores != 0) {
    if (sqlite3_step(libsql_res) != SQLITE_DONE) {
      fprintf(stderr, "failed to process sql request: %s\nerror msg: %s\n", sqlcmd, sqlite3_errstr(res));
      return(-1);
    }
  }
  return(0);
}


int libsql_nextresult(void) {
  int res;
  res = sqlite3_step(libsql_res);
  if (res == SQLITE_ROW) {
    return(0);
  }
  if (res == SQLITE_DONE) {
    return(-1);
  }
  return(-2);
}


char *libsql_getresult(int nField) {
  char *res;
  res = (char *)sqlite3_column_text(libsql_res, nField);
  return(res);
}
