/*
   This is the header file for libgetconf
   Copyright (C) Mateusz Viste 2011,2012
*/

#ifndef libgetconf
  #define libgetconf

  struct getconf_entry {
    char *section;
    char *token;
    char *value;
  };

  /* Load the configuration file "filename" */
  int loadconf(char *filename);

  /* Find the token "token" belonging to section "section", and return a pointer to its value
     The "section" parameter will be ignored if set to NULL. */
  char *getconf_findvalue(char *section, char *token);

  /* Get all configuration values, one by one (needs to be called multiple times). When no
     more values will be available, then the function will return a NULL pointer. */
  struct getconf_entry *getconf_dumpconfig();

  /* Find the token "token" belonging to section "section", which is a list of parameters,
     and fills the pointer *value with the parameter at position 'position' in the list,
     up to maxlen bytes. */
  int getconf_findvaluefromlist(char *section, char *token, int position, char *value, int maxlen);

#endif
