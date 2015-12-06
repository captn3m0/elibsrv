/*
   libsql - a generic interface to the SQL database.
   Copyright (C) 2014-2016 Mateusz Viste

    int libsql_connect(char *sqlserver, int sqlport, char *sqldatabase, char *sqluser, char *sqlpassword)
    void libsql_escape_string(char *fromstr, int fromlen)
    int libsql_sendreq(char *sqlcmd)
    int libsql_nextresult(void)
    char *libsql_getresult(int nField)
    int libsql_freeresult(void)
    void libsql_disconnect(void)

   elibsrv is free software: you can redistribute it and/or modify it
   under the terms of the GNU General Public License as published by the
   Free Software Foundation, either version 3 of the License, or (at your
   option) any later version. elibsrv is distributed in the hope that
   it will be useful, but WITHOUT ANY WARRANTY; without even the implied
   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
   the GNU General Public License for more details.
   You should have received a copy of the GNU General Public License
   along with elibsrv. If not, see <http://www.gnu.org/licenses/>.
*/


#include <string.h>
#include <syslog.h>   /* for syslog() */
#include <unistd.h>   /* for dup() */
#include <stdlib.h>   /* for atoll() */
#include <libpq-fe.h>
#include "libsql.h"   /* include self for control */

static PGconn *libsql_conn;
static PGresult *libsql_res;
static int libsql_inited = 0, libsql_rescnt = -1;


char *libsql_escape_string(char *fromstr, int fromlen) {
  if (libsql_inited == 0) return(NULL);
  return(PQescapeLiteral(libsql_conn, fromstr, fromlen));
}


void libsql_disconnect(void) {
  if (libsql_inited != 0) {
    PQfinish(libsql_conn); /*  clean up the lib  */
    libsql_inited = 0;
  }
}


int libsql_connect(char *sqlserver, int sqlport, char *sqldatabase, char *sqluser, char *sqlpassword) { /* connect to the DB - if the parameter is not NULL then connect the GPBox db, otherwise to gp_global */
  char conninfo[1024];
  snprintf(conninfo, 1024, "hostaddr=%s port=%d dbname=%s user=%s password=%s connect_timeout=10 sslmode=disable", sqlserver, sqlport, sqldatabase, sqluser, sqlpassword);
  libsql_conn = PQconnectdb(conninfo);
  libsql_inited = 1;
  if (PQstatus(libsql_conn) != CONNECTION_OK) {
    syslog(LOG_WARNING, "%s", PQerrorMessage(libsql_conn));
    libsql_disconnect();
    return(-1);
  }
  return(0);
}


int libsql_freeresult(void) {
  if ((libsql_inited != 0) && (libsql_rescnt >= 0)) {
    libsql_rescnt = -1;
    PQclear(libsql_res);
    return(0);
  } else {
    syslog(LOG_WARNING, "libsql_freeresult(): memory could not be freed [%d;%d]", libsql_inited, libsql_rescnt);
    return(-1);
  }
}


int libsql_sendreq(char *sqlcmd) {
  /* Save the stdout via a dup() call, then close it (libpq likes to write garbage to stdout from time to time... */
  /* int saved_stdout = dup(1);
     freopen("/dev/null", "w", stdout); */
  if (libsql_inited == 0) { /* if libsql not inited, then fail */
    syslog(LOG_WARNING, "libsql: libsql_senreq failed, because libsql is not inited yet.");
    return(-2);
  }
  if (libsql_rescnt >= 0) { /* If there is some old stuff to clean up (cause the messy dev hasn't cleaned), then fail */
    syslog(LOG_WARNING, "libsql: libsql_sendreq failed, because a previous instance is still pending.");
    return(-3);
  }
  /* Now we can proceed to the SQL stuff */
  libsql_res = PQexec(libsql_conn, sqlcmd);
  /* Now we can restore the original stdout */
  /* char buf[64];
    sprintf(buf, "/dev/fd/%d", saved_stdout);
    freopen(buf, "w", stdout); */
  /* stdout restored */
  if (PQresultStatus(libsql_res) == PGRES_COMMAND_OK) { /* this is a command that can never return any data */
    libsql_rescnt = -1;
    PQclear(libsql_res);
  } else if (PQresultStatus(libsql_res) != PGRES_TUPLES_OK) {
    /* snprintf(errmsg, errmsg_maxlen, PQerrorMessage(libsql_conn)); */
    syslog(LOG_WARNING, "%s", PQerrorMessage(libsql_conn));
    PQclear(libsql_res);
    libsql_rescnt = -1;
    return(-1);
  } else {
    libsql_rescnt = 0;
  }
  return(0);
}


int libsql_nextresult(void) {
  if ((libsql_inited != 0) && (libsql_rescnt >= 0)) {
    if (libsql_rescnt < PQntuples(libsql_res)) {
      libsql_rescnt += 1;
      return(0);
    } else {
      return(-1);
    }
  } else { /* libsql not inited or no request sent that could return anything */
    return(-2);
  }
}


char *libsql_getresult(int nField) {
  if ((libsql_inited != 0) && (libsql_rescnt > 0)) {
    if ((libsql_rescnt <= PQntuples(libsql_res)) && (nField < PQnfields(libsql_res))) {
      return(PQgetvalue(libsql_res, libsql_rescnt - 1, nField));
    }
  }
  return(NULL);
}
