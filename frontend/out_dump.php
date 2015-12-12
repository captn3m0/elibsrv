<?php

/*
 * This file is part of the elibsrv project.
 * Provides the output functions for dump (debug) mode.
 *
 * http://elibsrv.sourceforge.net
 *
 * Each output plugin file must contain a set of defined functions, as listed
 * below, where SUFFIX is the plugin's suffix (eg. "atom", "html"...):
 *
 * printheaders_SUFFIX(array $pageinfo)
 *   prints out all necessary headers for the given SUFFIX output plugin. this
 *   function is called once, at the start of every page. the $pageinfo array
 *   contains following fields:
 *     $pageinfo['title'] - the title of the page
 *     $pageinfo['id']    - a unique string identifying this page
 *     $pageinfo['self']  - the main script url (for self-referencing links)
 *
 * printnaventry_SUFFIX(array $nav)
 *  prints out a single 'navigation' entry. that is a menu entry that leads to
 *  another menu within the elibsrv interface. the $nav array contains
 *  following fields:
 *     $nav['title']  - title of the navigation entry
 *     $nav['url']    - target url
 *     $nav['icon']   - url to the icon, if any (can be empty)
 *
 * printaqentry_SUFFIX(array $meta)
 *   used by elibsrv to output an 'acquisition entry', that is an entry that
 *   will allow the user agent to fetch the ebook. it's fed with a $meta array
 *   that contains all ebook's metadata fetched by elibsrv:
 *     $meta['title']     - ebook's title
 *     $meta['author']    - ebook's author
 *     $meta['lang']      - ebook's language (two-letter country code like 'en', 'fr', etc)
 *     $meta['desc']      - ebook's description (also known as "summary")
 *     $meta['publisher'] - ebook's publisher
 *     $meta['pubdate']   - ebook's publication date
 *     $meta['catdate']   - date and time when the ebook has been cataloged
 *     $meta['moddate']   - date and time when the ebook has been last modified
 *     $meta['crc']       - CRC32 sum of the ebook file
 *     $meta['aqlink']    - acquisition link (can be used to fetch the actual ebook file)
 *     $meta['coverlink'] - cover link (can be used to fetch the ebook's cover image)
 *     $meta['thumblink'] - thumbnail link (can be used to fetch a thumbnail image of the cover)
 *     $meta['filename']  - the local filename of the ebook file
 *     $meta['filesize']  - the ebook's file size, in bytes
 *
 * printtrailer_SUFFIX(array $info)
 *   used to output the answer's trailer data (if any). this is called once,
 *   at the end of every page. the $info array contains following fields:
 *     $info['version']  - the program's version number
 *     $info['homepage'] - the program's
 */


function printheaders_dump(array $pageinfo) {
  header("content-type: text/plain;charset=utf-8");
  var_dump($pageinfo);
}


function printnaventry_dump(array $nav) {
  var_dump($nav);
}


function printaqentry_dump(array $meta) {
  var_dump($meta);
}


function printtrailer_dump(array $info) {
  var_dump($info);
}


?>
