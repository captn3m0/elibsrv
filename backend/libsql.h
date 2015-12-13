/*
    libsql header file
    Copyright (C) Mateusz Viste 2011
*/

#ifndef libsql_h_sentinel

  #define libsql_h_sentinel

  int libsql_connect(char *sqlserver, int sqlport, char *sqldatabase, char *sqluser, char *sqlpassword);
  char *libsql_escape_string(char *fromstr);
  int libsql_sendreq(char *sqlcmd);
  int libsql_nextresult(void);
  char *libsql_getresult(int nField);
  int libsql_freeresult(void);
  void libsql_disconnect(void);

#endif
