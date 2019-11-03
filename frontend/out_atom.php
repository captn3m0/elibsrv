<?php

/*
 * This file is part of the elibsrv project.
 * Provides the output functions for ATOM (OPDS) feeds.
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
 *     $meta['aqlinkmobi']- acquisition link to the mobi version of the file (if any)
 *     $meta['coverlink'] - cover link (can be used to fetch the ebook's cover image)
 *     $meta['thumblink'] - thumbnail link (can be used to fetch a thumbnail image of the cover)
 *     $meta['filename']  - the local filename of the ebook file
 *     $meta['filesize']  - the ebook's file size, in bytes
 *     $meta['format']    - ebook's file format ('epub', 'pdf')
 *
 * printtrailer_SUFFIX(array $info)
 *   used to output the answer's trailer data (if any). this is called once,
 *   at the end of every page. the $info array contains following fields:
 *     $info['version']  - the program's version number
 *     $info['homepage'] - the program's
 */

function printheaders_atom(array $pageinfo)
{
    header("content-type: application/xml;charset=utf-8");
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo "\n";
    echo '<feed xmlns:opds="http://opds-spec.org/2010/catalog" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xml:lang="en" xmlns:opensearch="http://a9.com/-/spec/opensearch/1.1/" xmlns:app="http://www.w3.org/2007/app" xmlns:dc="http://purl.org/dc/terms/" xmlns:dcterms="http://purl.org/dc/terms/">' .
        "\n";
    echo "\n";
    echo "  <id>" . md5($pageinfo['id']) . "</id>\n";
    echo "  <title>" .
        htmlentities($pageinfo['title'], ENT_XML1, "UTF-8") .
        "</title>\n";
    echo "  <icon>favicon.png</icon>\n";
    echo "  <updated>" . date(DATE_ATOM) . "</updated>\n";
    echo "\n";
    // print the menu bar
    echo '  <link rel="start" title="Home"' . "\n";
    echo '        href="' . $pageinfo['self'] . '?f=atom"' . "\n";
    echo '        type="application/atom+xml;profile=opds-catalog;kind=navigation"/>' .
        "\n";
    echo "\n";
    echo '  <link rel="self"' . "\n";
    echo '        href="' . $pageinfo['self'] . '?f=atom"' . "\n";
    echo '        type="application/atom+xml;profile=opds-catalog;kind=navigation"/>' .
        "\n";
    echo "\n";
    echo '  <link rel="search" title="Search"' . "\n";
    echo '        href="' .
        $pageinfo['self'] .
        '?f=atom&amp;action=searchform"' .
        "\n";
    echo '        type="application/opensearchdescription+xml"/>' . "\n";
    echo "\n";
}

function printnaventry_atom(array $nav)
{
    echo "  <entry>\n";
    echo '    <title>' .
        htmlentities($nav['title'], ENT_XML1, "UTF-8") .
        "</title>\n";
    echo '    <id>' . md5($nav['url']) . "</id>\n";
    echo '    <content type="text"></content>' . "\n";
    echo '    <link type="application/atom+xml;profile=opds-catalog;kind=navigation" href="' .
        $nav['url'] .
        '"/>' .
        "\n";
    if (!empty($nav['icon'])) {
        echo '    <link href="' .
            $nav['icon'] .
            '" type="image/png" rel="http://opds-spec.org/image/thumbnail"/>' .
            "\n";
    }
    echo "    <updated>" . date(DATE_ATOM) . "</updated>\n";
    echo "  </entry>\n";
}

function printaqentry_atom(array $meta)
{
    $mimetypes = array();
    $mimetypes['epub'] = 'application/epub+zip';
    $mimetypes['pdf'] = 'application/pdf';
    echo "  <entry>\n";
    echo "    <title>" .
        htmlentities($meta['title'], ENT_XML1, "UTF-8") .
        "</title>\n";
    echo "    <id>" . $meta['crc'] . "</id>\n";
    echo "    <updated>" . date(DATE_ATOM) . "</updated>\n";
    echo "    <author>\n";
    echo "      <name>" . $meta['author'] . "</name>\n";
    echo "    </author>\n";
    echo "    <dc:language>" .
        htmlentities($meta['lang'], ENT_XML1, "UTF-8") .
        "</dc:language>\n";
    echo "    <dcterms:issued>" .
        htmlentities($meta['pubdate'], ENT_XML1, "UTF-8") .
        "</dcterms:issued>\n";
    echo "    <dcterms:publisher>" .
        htmlentities($meta['publisher'], ENT_XML1, "UTF-8") .
        "</dcterms:publisher>\n";
    echo '    <summary type="text">' .
        htmlentities($meta['desc'], ENT_XML1, "UTF-8") .
        "</summary>\n";
    echo '    <link rel="http://opds-spec.org/image"' . "\n";
    echo '        href="' . $meta['coverlink'] . '"' . "\n";
    echo '        type="image/jpeg"/>' . "\n";
    echo '    <link rel="http://opds-spec.org/image/thumbnail"' . "\n";
    echo '        href="' . $meta['thumblink'] . '"' . "\n";
    echo '        type="image/jpeg"/>' . "\n";
    echo '    <link rel="http://opds-spec.org/acquisition"' . "\n";
    echo '        href="' . $meta['aqlink'] . '"' . "\n";
    echo '        type="' . $mimetypes[$meta['format']] . '"/>' . "\n";
    if (!empty($meta['aqlinkmobi'])) {
        echo '    <link rel="http://opds-spec.org/acquisition"' . "\n";
        echo '        href="' . $meta['aqlinkmobi'] . '"' . "\n";
        echo '        type="application/x-mobipocket-ebook"/>' . "\n";
    }
    echo "  </entry>\n";
}

function printtrailer_atom(array $info)
{
    echo "\n</feed>\n";
}

?>
