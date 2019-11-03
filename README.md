# [elibsrv](http://elibsrv.sourceforge.net)

>a light library server for ePub and PDF ebooks
>Copyright (C) 2014-2016 Mateusz Viste


>"Book collectors do not buy books to read - they buy books because they have read them."
>                                                                    --André Gide

_Note_: This is a fork of the sourceforge repo, with additional support for Docker.


## Intro

elibsrv is a virtual library for ebook files. In more techy terms, it's a light ePub and PDF indexing engine providing an OPDS and HTML interface. If you have plenty of ePub and/or PDF files and would like to access them remotely via an organized interface, either using your web browser or OPDS-compatible device, elibsrv could be an excellent fit. It is also compatible with Kindle devices, allowing for on-the-fly conversion to *.mobi, when configured with the kindlegen plugin.

elibsrv is based on two blocks: the indexing process (ran periodically, eg. via a crontab entry), the PHP frontend (outputting OPDS or HTML listings). elibsrv stores all the indexed metadata in its own SQLite database.

elibsrv can be installed on a Linux or BSD server, and probably on anything that's compatible with POSIX.


## Dependencies

elibsrv requires some bits to be present on your server:

- libsqlite3 (used by the indexing process to populate its internal database)
- libepub (used by the indexing process to extract metadata from EPUB files)
- libpoppler-cpp (for PDF metadata extraction)
- a web server with PHP 5.3 (or newer) and PHP GD and PHP SQLite3 extensions

### optional:
- the `kindlegen` binary from Kindle, if mobi conversion is required
- the "rewrite" http module, if pretty URLs are to be used


## Installation

The installation process of the elibsrv server requires to follow a few steps.

1. Copy the file `elibsrv.conf` to your `/etc/`` directory, and edit it to set parameters accordingly to your installation. Note, that you can store the configuration file elsewhere, and name it differently (which might come handy if you'd like to run several OPDS feeds on the same server). If you do change the location of the configuration file, you will have to adapt the crontab calls of the elibsrv backend (see below), and also modify the `config.php` file in your root web directory.

2. Compile the indexing backend process. To do this, simply go to the `elibsrv/backend` directory, and type '`make`'. This should compile you an '`elibsrv`' binary executable file.

3. Set up indexing to be performed on a periodical basis (via a crontab entry). Assuming you keep the elibsrv backend in `/`root/elibsrv/elibsrv`, and you keep your EPUB ebooks in `/srv/ebooks`, and you store your configuration file in `/etc/elibsrv.conf`, then the cron entry you might want to add to your crontab could look like one of these:

```
0 *    * * *    root    find /srv/ebooks/ -iname '*.epub' | /root/elibsrv/elibsrv /etc/elibsrv.conf
0 *    * * *    root    find /srv/ebooks/ -regextype posix-egrep -iregex '.*\.((epub)|(pdf))' | /root/elibsrv/elibsrv /etc/elibsrv.conf
```

4. Copy all files and directories in `frontend/*` to your web directory root. If you'd like to use "pretty URLs" (which is highly recommended for cross-browsers compatibility), then you will also have to rename the htaccess file to `.htaccess` (assuming you're running the Apache web server).

5. Run the elibsrv indexer a first time, so it builds its metadata base. Use the same command you scheduled in your crontab to run it.

Since now on, you should be able to access your library via one of the following urls:

```
  OPDS: http://yourwebserver/
  HTML: http://yourwebserver/?f=html
```


## Kindle compatibility

elibsrv is primarily an ePub aggregator. Sadly, the Kindle reader is not compatible with the ePub standard, and supports only its own (closed and obscure) "mobi" format instead. If you are the owner of a Kindle device, fear not - you still can use elibsrv. Since v20151220, elibsrv supports automatic ("on the fly") conversion of ePub files to the MOBI standard using a free converter tool from Amazon, called "`kindlegen`". This requires to set the "`kindlegenbin`" elibsrv configuration setting with the full path to the kindlegen binary (you will have to find the kindlegen binary on your own though, since several versions exist for different operating systems, and I'm not authorized to redistribute them anyway). Once this is done, elibsrv will propose both 'EPUB' and 'MOBI' download links for each book. When a MOBI download is requested, elibsrv looks whether the exact same ebook filename exists, but with the '*.mobi' extension - if so, it returns it. If not, it calls the kindlegen tool to convert it first, and then returns the mobi result. Note, that you could just as well replace kindlegen with some equivalent, as long as it understands the same syntax "tool full-path-filename.epub", and computes a mobi file in the same directory where the original ePub file is.

## Docker Support

This fork adds support for running the server and scanner via Docker. The published images are available at <https://cloud.docker.com/repository/docker/captn3m0/elibsrv>. The published docker image includes the kindlegen binary pre-installed. The docker image expects your books to be mounted at `/books`

### Environment variables

|Variable|Default|Notes|
|---|---|---|
|elibsrv_title|"My ebooks server"|Ebook server title|
|elibsrv_db|"/config/elibsrv.db"|Path to the sqlite database file|
|elibsrv_thumbheight|160|Height in pixels for generated thumbnails|

### Command

You can pass the following as command parameter to the docker image:

|Command|Notes|
|`scan`|Run the scanner and then quit|
|`serve`|Just run the webserver|
|`` (default)|Run the scanner, and then run the webserver once the scan finishes|

### Example

Assuming that you have your ebooks saved in `/srv/books`, you can run the following command:

`docker run --volume /srv/books:/books:ro --volume /etc/elibsrv:/config captn3m0/elibsrv`

If you'd like to break your scan and serve steps, you can run your scanner on a crontab:

`0 * * * * docker run --volume /srv/books:/books:ro --volume /etc/elibsrv:/config captn3m0/elibsrv scan`

and then mount the config directory (holding the database) read-only on the actual webserver:

`docker run --volume /srv/books:/books:ro --volume /etc/elibsrv:/config:ro captn3m0/elibsrv serve`

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
