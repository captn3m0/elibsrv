#!/bin/bash

sigil -f /etc/elibsrv.conf.tmpl -p > /etc/elibsrv.conf

mkdir --parents /books /config /cache
chmod 777 /cache

echo "HELKLO=$1"

case "$1" in
    "scan" )
        SCAN=1 ;;
    "serve" )
        SERVE=1 ;;
    *)
        SCAN=1
        SERVE=1
esac

echo $SCAN $SERVE

if [[ "$SCAN" = "1" ]]; then
    echo "[+] Scanning EPUB files"
    find /books/ -regextype posix-egrep -iregex '.*\.((epub)|(pdf))' | elibsrv /etc/elibsrv.conf
fi

if [[ "$SERVE" = "1" ]]; then
    echo "[+] Starting web server"
    apachectl -D FOREGROUND
fi