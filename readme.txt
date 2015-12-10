
elibsrv - a light library server for EPUB ebooks
Copyright (C) 2014-2016 Mateusz Viste

homepage: http://elibsrv.sourceforge.net


"Book collectors do not buy books to read - they buy books because they have read them."
                                                                    --André Gide


[ Intro ]

elibsrv is a virtual library for EPUB files. In more techy terms, it's a light ePub indexing engine providing an OPDS and HTML interface. If you have plenty ePub files and would like to access them remotely via an organized interface, either using your web browser or OPDS-compatible device, elibsrv can be an excellent fit.

elibsrv is based on three blocks: the indexing process (ran periodically, eg. via a crontab entry), the PHP frontend (outputting OPDS or HTML listings), and a PostgreSQL database used to store the indexed metadata.

elibsrv can be installed on a Linux or BSD server.


[ Dependencies ]

elibsrv requires some bits to be present on your server:
 - libpq5 (used by the indexing process to populate the PostgreSQL database)
 - libepub (used by the indexing process to extract metadata from EPUB files)
 - an access to a PostgreSQL database
 - a web server with PHP5 and:
   - the PHP GD extension
   - the PHP postgresql extension
   - the "rewrite" module, if pretty URLs are to be used


[ Installation ]

The installation process of the elibsrv server requires to follow a few steps.

1. Prepare the SQL database: create an empty database on your PostgreSQL cluster, and populate it using the elibsrv.sql script. Example:
# psql postgres postgres -c "CREATE USER elibsrv WITH password 'mypass';"
# psql postgres postgres -c "CREATE DATABASE elibsrvdb WITH OWNER elibsrv;"
# psql elibsrvdb elibsrv -f elibsrv.sql

2. Copy the file elibsrv.conf to your /etc/ directory, and edit it to set parameters accordingly to your installation. Note, that you can store the configuration file elsewhere, and name it differently (which might come handy if you'd like to run several OPDS feeds on the same server). If you do change the location of the configuration file, you will have to adapt the crontab calls of the elibsrv backend (see below), and also modify the config.php file in your root web directory.

3. Compile the indexing backend process. To do this, simply go to the elibsrv/backend directory, and type 'make'. This should compile you an 'elibsrv' binary executable file.

4. Set up indexing to be performed on a periodical basis (via a crontab entry). Assuming you keep the elibsrv backend in /root/elibsrv/elibsrv, and you keep your EPUB ebooks in /srv/ebooks, and you store your configuration file in /etc/elibsrv.conf, then the cron entry you might want to add to your crontab could look like this:
0 *    * * *    root    find /srv/ebooks/ -name *.epub | /root/elibsrv/elibsrv /etc/elibsrv.conf

5. Copy all files and directories in frontend/* to your web directory root. If you will want to use "pretty URLs" (which is highly recommended for cross-browsers compatibility), then you will also have to rename the htaccess file to .htaccess (assuming you're running the Apache web server).

Since now on, you should be able to access your library via one of the following urls:
  OPDS: http://yourwebserver/
  HTML: http://yourwebserver/?f=html


[ License ]

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
