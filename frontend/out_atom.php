<?php

/*
 * This file is part of the elibsrv project.
 * Provides the output functions for ATOM feeds.
 *
 * http://elibsrv.sourceforge.net
 */


function printheaders_atom(array $pageinfo) {
  header("content-type: application/xml;charset=utf-8");
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "\n";
  echo '<feed xmlns:opds="http://opds-spec.org/2010/catalog" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xml:lang="en" xmlns:opensearch="http://a9.com/-/spec/opensearch/1.1/" xmlns:app="http://www.w3.org/2007/app" xmlns:dc="http://purl.org/dc/terms/" xmlns:dcterms="http://purl.org/dc/terms/">' . "\n";
  echo "\n";
  echo "  <id>" . md5($pageinfo['id']). "</id>\n";
  echo "  <title>" . htmlentities($pageinfo['title']) . "</title>\n";
  echo "  <icon>favicon.png</icon>\n";
  echo "  <updated>" . date(DATE_ATOM) . "</updated>\n";
  echo "\n";
  // print the menu bar
  echo '  <link rel="start" title="Home"' . "\n";
  echo '        href="' . $pageinfo['self'] . '?f=atom"' . "\n";
  echo '        type="application/atom+xml;profile=opds-catalog;kind=navigation"/>' . "\n";
  echo "\n";
  echo '  <link rel="self"' . "\n";
  echo '        href="' . $pageinfo['self'] . '?f=atom"' . "\n";
  echo '        type="application/atom+xml;profile=opds-catalog;kind=navigation"/>' . "\n";
  echo "\n";
  echo "  <link rel=\"search\" title=\"Search\"\n";
  echo "        href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "&amp;action=searchform\"\n";
  echo "        type=\"application/opensearchdescription+xml\"/>\n";
  echo "\n";
}


function printnaventry_atom(array $nav) {
  echo "  <entry>\n";
  echo '    <title>' . htmlentities($nav['title'], ENT_XML1, "UTF-8") . "</title>\n";
  echo '    <id>' . md5($nav['url']) . "</id>\n";
  echo '    <content type="text"></content>' . "\n";
  echo '    <link type="application/atom+xml;profile=opds-catalog;kind=navigation" href="' . $nav['url'] . '"/>' . "\n";
  if (!empty($nav['icon'])) echo '    <link href="' . $nav['icon'] . '" type="image/png" rel="http://opds-spec.org/image/thumbnail"/>' . "\n";
  echo "    <updated>" . date(DATE_ATOM) . "</updated>\n";
  echo "  </entry>\n";
}


function printaqentry_atom(array $meta) {
  echo "  <entry>\n";
  echo "    <title>" . htmlentities($meta['title'], ENT_XML1, "UTF-8") . "</title>\n";
  echo "    <id>" . $meta['crc'] . "</id>\n";
  echo "    <updated>" . date(DATE_ATOM) . "</updated>\n";
  echo "    <author>\n";
  echo "      <name>" . $meta['author'] . "</name>\n";
  echo "    </author>\n";
  echo "    <dc:language>" . htmlentities($meta['lang'], ENT_XML1, "UTF-8") . "</dc:language>\n";
  echo "    <dcterms:issued>" . htmlentities($meta['pubdate'], ENT_XML1, "UTF-8") . "</dcterms:issued>\n";
  echo "    <dcterms:publisher>" . htmlentities($meta['publisher'], ENT_XML1, "UTF-8") . "</dcterms:publisher>\n";
  echo '    <summary type="text">' . htmlentities($meta['desc'], ENT_XML1, "UTF-8") . "</summary>\n";
  echo "    <link rel=\"http://opds-spec.org/image\"\n";
  echo '        href="' . $meta['coverlink'] . "\"\n";
  echo "        type=\"image/jpeg\"/>\n";
  echo "    <link rel=\"http://opds-spec.org/image/thumbnail\"\n";
  echo "        href=\"" . $meta['thumblink'] . "\"\n";
  echo "        type=\"image/jpeg\"/>\n";
  echo "    <link rel=\"http://opds-spec.org/acquisition\"\n";
  echo "        href=\"{$meta['aqlink']}\"\n";
  echo "        type=\"application/epub+zip\"/>\n";
  //<link href="http://www.archive.org/download/bequest_jg_librivox/bequest_jg_librivox.pdf" type="application/pdf" rel="http://opds-spec.org/acquisition"/>
  echo "  </entry>\n";
}


function printtrailer_atom(array $info) {
  echo "\n</feed>\n";
}


?>
