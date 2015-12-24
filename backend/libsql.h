/*
 * libsql header file
 * Copyright (C) Mateusz Viste 2015-2016
 */

#ifndef libsql_h_sentinel

  #define libsql_h_sentinel

  int libsql_connect(char *sqlfile);
  char *libsql_escape_string(char *fromstr);
  int libsql_sendreq(char *sqlcmd, int nores);
  int libsql_nextresult(void);
  char *libsql_getresult(int nField);
  int libsql_freeresult(void);
  void libsql_disconnect(void);

#endif
