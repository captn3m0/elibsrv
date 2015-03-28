/*
   This is a simple config file parser. This file is under Public Domain.
   Copyright (C) Mateusz Viste 2011,2012. All rights reserved.

   Here is a quick example about how to use getconf:
     #include "getconf.h";
     int main(int argc, char *argv[]) {
       char *value;
       loadconf("/etc/gp/probd.conf");
       value = getconf_findvalue("section", "ServerAddr");
       printf("%s", value);
     }
*/

#include <stdio.h>
#include <string.h>
#include <syslog.h>
#include "libgetconf.h"

/* #define DEBUG */ /* uncomment this to turn libgetconf() into debug (verbose) mode */

char configtablesections[256][50];
char configtabletokens[256][50];
char configtablevalues[256][128];


static void rtrim(char *str) { /* declared as static so this function can be called only from this module */
  if (str[0] > 0) {
    unsigned int x = 0, y = 0;
    while (str[x] > 0) {
      if ((str[x] != 32) && (str[x] != 9)) y = (x + 1);
      x++;
    }
    str[y] = 0;
  }
}


int loadconf(char *filename) {
  FILE *fp;
  int bytebuff;
  char readstate = 0;
  unsigned int curstringpointer = 0, curentry = 0, x = 0;
  char curstring[50];
  char cursection[50] = {0};

  #ifdef DEBUG
    syslog(LOG_INFO, "loadconf(%s)", filename);
  #endif
  fp = fopen(filename, "r");
  if (fp == NULL) {
    fprintf(stderr, "loadconf(): cannot open file %s\n", filename);
    return(1);
  }

  /* Reset all the config table content */
  for (x = 0; x < 254; x++) {
    configtablesections[x][0] = 0;
    configtabletokens[x][0] = 0;
    configtablevalues[x][0] = 0;
  }

  for (;;) {
    bytebuff = fgetc(fp);
    if (bytebuff == EOF) break;
    switch (readstate) {
      case 0: /* "discovery", let's see what we get on (ignore space-like characters) */
        switch (bytebuff) {
          case 0x0:   /* NUL */
            break;
          case '#':  /* # */
          case ';':  /* ; */
            readstate = 1;
            break;
          case '\n':   /* LF */
          case '\r':   /* CR */
          case ' ':    /* SPC */
            break;
          case '[':   /* [ */
            cursection[0] = 0;
            curstringpointer = 0;
            readstate = 5;
            break;
          default:
            readstate = 2;
            curstring[0] = bytebuff;
            curstringpointer = 1;
            /* Save the current section name */
            for (x = 0; cursection[x] != 0; x++) configtablesections[curentry][x] = cursection[x];
            break;
        }
        break;
      case 1: /* "comment", ignore everything until we got into an LF */
        if (bytebuff == 0xA) readstate = 0;
        break;
      case 2: /* "token", we are retrieving a token's name here */
        configtablesections[curentry][x] = 0;
        switch (bytebuff) {
          case 0:    /* NUL */
          case 0xA:  /* LF  */
          case 0xD:  /* CR  */
            readstate = 0;
            break;
          case 0x20: /* SPC */
          case '\t': /* TAB */
            break;
          case 0x23: /* # */
            readstate = 1;
            break;
          case 0x3D: /* = */
            curstring[curstringpointer] = 0;
            for (x = 0; x <= curstringpointer; x++) {
              configtabletokens[curentry][x] = curstring[x];
            }
            curstringpointer = 0;
            readstate = 3;
            break;
          default:
            if (curstringpointer < 49) {
              curstring[curstringpointer] = bytebuff;
              curstringpointer++;
            }
            break;
        }
        break;
      case 3: /* "value", we are retrieving a token's value here */
        switch (bytebuff) {
          case 0:    /* NUL */
            break;
          case 0xA:  /* LF */
          case 0xD:  /* CR */
            curstring[curstringpointer] = 0;
            for (x = 0; x <= curstringpointer; x++) {
              configtablevalues[curentry][x] = curstring[x];
            }
            curstringpointer = 0;
            if (curentry < 255) curentry++;
            readstate = 0;
            break;
          case 0x23: /* # */
            curstring[curstringpointer] = 0;
            rtrim(curstring);
            for (x = 0; x <= curstringpointer; x++) {
              configtablevalues[curentry][x] = curstring[x];
            }
            curstringpointer = 0;
            if (curentry < 255) curentry++;
            readstate = 1;
            break;
          case 0x20: /* SPC */
            if (curstringpointer == 0) break; /* ignore spaces when in front of the value */
          default:
            if (curstringpointer < 127) {
              curstring[curstringpointer] = bytebuff;
              curstringpointer++;
            }
            break;
        }
        break;
      case 5: /* Section name */
        switch (bytebuff) {
          case 0x0A:
            readstate = 0;
            curstringpointer = 0;
            break;
          case ']':
            readstate = 1;
            curstringpointer = 0;
            break;
          default:
            if (curstringpointer < 49) {
              cursection[curstringpointer] = bytebuff;
              curstringpointer++;
              cursection[curstringpointer] = 0;
            }
            break;
        }
        break;
    }
  }
  fclose(fp); /* close the conf file */
  #ifdef DEBUG
    for (x = 0; x < 256; x++) {
      if (configtabletokens[x][0] != 0) {
        syslog(LOG_INFO, "%s.%s=%s\n", configtablesections[x], configtabletokens[x], configtablevalues[x]);
      }
    }
  #endif
  return(0);
}


char *getconf_findvalue(char *section, char *token) {
  unsigned int x;
  #ifdef DEBUG
    syslog(LOG_INFO, "getconf_findvalue(%s, %s)", section, token);
  #endif
  for (x = 0; x < 256; x++) { /* note: strcasecmp is like strcmp, but case-insensitive */
    if (strcasecmp(token, configtabletokens[x]) == 0) {
      if (section != NULL) {
          if (strcasecmp(section, configtablesections[x]) == 0) return(configtablevalues[x]);
        } else {
          return(configtablevalues[x]);
      }
    }
  }
  return("");
}


struct getconf_entry *getconf_dumpconfig() {
  static int nextval = 0;
  static struct getconf_entry confentry;
  #ifdef DEBUG
    syslog(LOG_INFO, "getconf_dumpconfig()");
  #endif
  if (nextval < 256) {
    if (configtabletokens[nextval][0] != 0) {
      confentry.section = configtablesections[nextval];
      confentry.token = configtabletokens[nextval];
      confentry.value = configtablevalues[nextval];
      nextval += 1;
      return(&confentry);
    }
  }
  return(NULL);
}


/* This function takes a list of values, separated with commas, and finds *
 * the Nth value, feeding the variable 'value' with it.                   *
 * The function returns:                                                  *
 *    0 if everything went fine                                           *
 *    1 if the end of the list has been reached                           *
 *   -1 if the value has been trimed                                      */
int getconf_findvaluefromlist(char *section, char *token, int position, char *value, int maxlen) {
  int zx = 0, zz = 1, lastchar = -1;
  char *list;
  #ifdef DEBUG
    syslog(LOG_INFO, "getconf_findvaluefromlist(%s, %s, %d, value, %d)", section, token, position, maxlen);
  #endif
  list = getconf_findvalue(section, token);
  if (list == NULL) {
    value = NULL;
    return(1);
  }
  if (position < 1) return(-2);
  if (position > 1) {
      do {
        if (list[zx] == 0) {
          return(1);
        }
        if (list[zx] == ',') zz += 1;
        zx += 1;
      } while (zz < position);
    } else if (position == 1) { /* if asking for 1st parameter, check if parameter exists at all */
      if (list[0] == 0) return(1);
  }
  zz = 0;
  while ((list[zx] != 0) && (list[zx] != ',')) {
    switch (list[zx]) {
      case ',':
        break;
      case ' ':
      case '\t':
        if (zz == 0) break; /* ignore any leading white space chars */
      default:
        value[zz] = list[zx];
        if ((value[zz] != ' ') && (value[zz] != '\t')) lastchar = zz; /* remember the position of last real char */
        zz += 1;
        if (zz == maxlen) {
          if (zz > 0) value[zz - 1] = 0;
          return(-1);
        }
    }
    zx += 1;
  }
  value[lastchar + 1] = 0; /* terminate the value string */
  return(0);
}


/* This one is provided only for legacy applications */
char *getconf(char *token) {
  #ifdef DEBUG
    syslog(LOG_INFO, "getconf(%s)", token);
  #endif
  return(getconf_findvalue(NULL, token));
}
