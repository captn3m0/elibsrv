/*
 * simple test program for the libsql module.
 *
 * This file is part of the elibsrv project.
 * Copyright (C) 2014-2016 Mateusz Viste
 *
 * http://elibsrv.sourceforge.net
 */

#include <stdio.h>
#include <stdlib.h>

#include "libsql.h"


int main(void) {
  char rawdata[256];
  char sqlcmd[256];
  char *sqldata;
  puts("open db...");
  if (libsql_connect("test.db") != 0) {
    puts("ERROR");
    return(1);
  }
  puts("create schema...");
  if (libsql_sendreq("CREATE TABLE xxx (id INTEGER PRIMARY KEY, val TEXT);", 1) != 0) {
    libsql_disconnect();
    return(1);
  }
  if (libsql_sendreq("CREATE TABLE xyz (id INTEGER PRIMARY KEY, val TEXT);", 1) != 0) {
    libsql_disconnect();
    return(1);
  }
  puts("insert value...");
  sprintf(rawdata, "Mateusz Viste, Mateusz");
  sqldata = libsql_escape_string(rawdata);
  sprintf(sqlcmd, "INSERT INTO xxx (val) VALUES (%s);", sqldata);
  free(sqldata);
  if (libsql_sendreq(sqlcmd, 1) != 0) {
    return(1);
  }
  puts("read value...");
  libsql_sendreq("SELECT id,val FROM xxx;", 0);
  while (libsql_nextresult() == 0) {
    printf("| %s\t| %s\n", libsql_getresult(0), libsql_getresult(1));
  }
  libsql_freeresult();
  puts("close db...");
  libsql_disconnect();
  return(0);
}
