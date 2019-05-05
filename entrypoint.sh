#!/bin/bash

sigil -f /etc/elibsrv.conf.tmpl -p > /etc/elibsrv.conf

mkdir --parents /books /config /cache
chmod 777 /cache

# Run a one-time scan on /books
echo "[+] Scanning EPUB files on first launch"
find /books/ -regextype posix-egrep -iregex '.*\.((epub)|(pdf))' | elibsrv -v /etc/elibsrv.conf

echo "[+] Starting web server"
apachectl -D FOREGROUND