<?php

/*
 * elibsrv - a light OPDS indexing server for EPUB ebooks.
 *
 * Copyright (C) Mateusz Viste 2014
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


$pver = "20141029";


function getconf($array, $key) {
  foreach ($array as $k=>$v) {
    if (strtolower($key) == strtolower($k)) return($v);
  }
  return(NULL);
}

// returns the file with the flag icon related to $lang. returns NULL if no matching flag found.
function getLangIcon($lang) {
  $filename = "flags/" . strtolower($lang) . ".png";
  if (file_exists(dirname(__FILE__) . "/" . $filename)) {
    return($filename);
  }
  return("flags/unknown.png");
}

// Outputs the thumbnail of a file, in given height (and proportional width)
function returnImageThumbnail($filename, $height, $cachedst) {
  // Compute new size
  list($width_orig, $height_orig) = getimagesize($filename);

  $ratio_orig = $width_orig/$height_orig;

  // Compute the new width
  $width = $height*$ratio_orig;

  // Create an empty image in memory
  $image_p = imagecreatetruecolor($width, $height);

  // Load the original image
  switch (exif_imagetype($filename)) {
    case IMAGETYPE_JPEG:
      $image = imagecreatefromjpeg($filename);
      break;
    case IMAGETYPE_PNG:
      $image = imagecreatefrompng($filename);
      break;
  }

  // Resample the original image to the new memory buffer
  imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

  // Return the new (thumbnail) image, and possibly save it to cache
  if (! empty($cachedst)) {
    header('Content-Type: image/jpeg');
    imagejpeg($image_p, $cachedst);
    readfile($cachedst);
  } else { // no cache file, output directly
    header('Content-Type: image/jpeg');
    imagejpeg($image_p);
  }
}


// Returns either "html" or "atom", depending on the client's user agent
function getDefaultOutformat() {
  $ua = $_SERVER['HTTP_USER_AGENT'];
  // detect Firefox
  if (preg_match("/^.* Firefox\/.*$/", $ua) == 1) return("html");

  // detect IE
  if (preg_match("/^.* MSIE .*$/", $ua) == 1) return("html");
  if (preg_match("/^.* Trident\/.*$/", $ua) == 1) return("html");

  // detect Chrome
  if (preg_match("/^.* Chrome\/.*$/", $ua) == 1) return("html");

  // detect Opera
  if (preg_match("/^.* Opera\/$/", $ua) == 1) return("html");

  // for anything else, assume it's an opds reader
  return("atom");
}


// Returns a string with the filename of the cover file inside an epub file (so I can access it later using zip://$temp#$file)
function getEpubCoverFile($epubfile) {
  $result = NULL;
  $firstimg = NULL;
  $za = zip_open($epubfile);
  if (! is_resource($za)) return(NULL);
  // here I should look for $za being valid
  for (;;) {
    $ze = zip_read($za);
    if ($ze == FALSE) break; // no more entries in the zip structure
    $filename = zip_entry_name($ze);
    $filesize = zip_entry_filesize($ze);
    if (substr($filename, -1) != '/') {
      // remember the first image of at least 4 KiB found in the archive
      if ((preg_match("/^.*\.(jpeg|jpg|png)$/", $filename) == 1) && ($firstimg == NULL) && ($filesize >= 4096)) $firstimg = $filename;
      // look for images that contains the word "cover"
      if (preg_match("/^.*[cC][oO][vV][eE][rR].*\.(jpeg|jpg|png)$/", $filename) == 1) {
        $result = $filename;
        break;
      }
      // img1.jpg is an often used filename for the cover - let's remember it, in case we find nothing better
      if ((preg_match("/^.*\/img1.jpg$/", $filename) == 1) && ($result == NULL)) $result = $filename;
    }
  }
  zip_close($za);
  // if no "sure" result found, then fallback to the first image that was found
  if ($result == NULL) $result = $firstimg;
  return($result);
}


function getLocalFilename($db, $query) {
  $filename = NULL;
  if (! is_numeric($query)) {
    echo "Error: invalid query.\n";
    return;
  }
  $query = "SELECT file FROM books WHERE crc32={$query};";
  $result = pg_query($query);
  $myrow = pg_fetch_assoc($result);
  if ($myrow) {
      $filename = $myrow['file'];
    } else {
      echo "Error: no matching file.\n";
  }
  pg_free_result($result);
  return($filename);
}


function printheaders($outformat, $pageid, $pagetitle) {
  if ($outformat == "atom") {
    header("content-type: application/xml;charset=utf-8");
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "\n";
    echo "<feed xmlns:opds=\"http://opds-spec.org/2010/catalog\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns=\"http://www.w3.org/2005/Atom\" xmlns:thr=\"http://purl.org/syndication/thread/1.0\" xml:lang=\"en\" xmlns:opensearch=\"http://a9.com/-/spec/opensearch/1.1/\" xmlns:app=\"http://www.w3.org/2007/app\" xmlns:dc=\"http://purl.org/dc/terms/\" xmlns:dcterms=\"http://purl.org/dc/terms/\">\n";
    echo "\n";
    echo "  <id>" . md5($pageid). "</id>\n";
    echo "  <title>" . $pagetitle . "</title>\n";
    echo "  <icon>favicon.png</icon>\n";
    echo "  <updated>" . date(DATE_ATOM) . "</updated>\n";
    echo "\n";
  } else if ($outformat == "html") {
    header("content-type: text/html;charset=utf-8");
    echo "<!DOCTYPE html>\n";
    echo "<html>\n";
    echo "<head>\n";
    echo "  <title>" . htmlentities($pagetitle) . "</title>\n";
    echo "  <link rel=\"shortcut icon\" href=\"favicon.png\">\n";
    echo "  <style>\n";
    echo "    p { padding: 0 1em 0 1em; margin: 0.1em 0 0.1em 0; }\n";
    echo "    p.menutitle { background-color: #FFE0C0; font-weight: bold; margin-bottom: 0.9em; }\n";
    echo "    p.acqimg { margin: 0.5em 0 0 0; padding: 0; }\n";
    echo "    p.acqlink { margin: -2.75em auto 1.2em 2em; }\n";
    echo "    p.trailer { margin: 1.5em 0 0 0; text-align: right; padding: 1px 0.5em 1px 0.5em; font-size: 0.8em; }\n";
    echo "    a.trailer { color: #606060; text-decoration: none; }\n";
    echo "    img.acqlink { vertical-align: middle; height: 3em; max-width: 2.9em; border: 1px #909090 solid; }\n";
    echo "    a { text-decoration: none; color: #000090; }\n";
    echo "    hr { margin: 0.9em 0 0.9em 0; color: #E0E0E0; }\n";
    echo "    span.author { color: #707070; }\n";
    echo "  </style>\n";
    echo "</head>\n";
    echo "<body>\n";
    echo "  <p class=\"menutitle\">" . $pagetitle . "</p>\n";
  }
}


function printlinks($outformat) {
  if ($outformat == "atom") {
    echo "  <link rel=\"start\" title=\"Home\"\n";
    echo "        href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "\"\n";
    echo "        type=\"application/atom+xml;profile=opds-catalog;kind=navigation\"/>\n";
    echo "\n";
    echo "  <link rel=\"self\"\n";
    echo "        href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "\"\n";
    echo "        type=\"application/atom+xml;profile=opds-catalog;kind=navigation\"/>\n";
    echo "\n";
    echo "  <link rel=\"search\" title=\"Search\"\n";
    echo "        href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "&amp;action=searchform\"\n";
    echo "        type=\"application/opensearchdescription+xml\"/>\n";
    echo "\n";
  } else if ($outformat == "html") {
    echo "  <p><form name=\"input\" action=\"" . $_SERVER['PHP_SELF'] . "\" method=\"get\"><a href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "\">Home</a> <input type=\"text\" name=\"query\"><input type=\"hidden\" name=\"action\" value=\"titles\"><input type=\"hidden\" name=\"f\" value=\"{$outformat}\"><input type=\"submit\" value=\"search\"></form></p>\n";
    //echo "  <p><a href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "\">Home</a></p>\n";
    echo "  <hr>\n";
  }
}


function printnaventry($outformat, $title, $urlparams, $iconfile) {
  if ($outformat == "atom") {
    echo "  <entry>\n";
    echo "    <title>" . htmlentities($title, ENT_XML1, "UTF-8") . "</title>\n";
    echo "    <id>" . md5($title . $urlparams) . "</id>\n";
    echo "    <content type=\"text\"></content>\n";
    echo "    <link type=\"application/atom+xml;profile=opds-catalog;kind=navigation\" href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "&amp;" . $urlparams . "\"/>\n";
    if ($iconfile != NULL) echo "    <link href=\"" . $iconfile . "\" type=\"image/png\" rel=\"http://opds-spec.org/image/thumbnail\"/>\n";
    echo "    <updated>" . date(DATE_ATOM) . "</updated>\n";
    echo "  </entry>\n";
  } else if ($outformat == "html") {
    $iconhtml = "";
    if ($iconfile != NULL) $iconhtml = "<img src=\"" . $iconfile . "\" style=\"height: 2em; padding: 0 1em 0 0; vertical-align: middle;\">";
    echo "  <p>" . $iconhtml . "<a href=\"" . $_SERVER['PHP_SELF'] . "?f=" . $outformat . "&amp;" . $urlparams . "\">" . htmlentities($title) . "</a></p>\n";
  }
}


function printaqentry($outformat, $title, $crc32, $author, $language, $description, $publisher, $pubdate, $prettyurls) {
  /* Prepare the epub link in advance, since it can have different forms, depending on "pretty URLs" */
  if ($prettyurls == 1) {
      $aqlink = "files/{$crc32}/" . rawurlencode($author . " - " . $title) . ".epub";
    } else {
      $aqlink = $_SERVER['PHP_SELF'] . "?action=getfile&amp;query=" . $crc32;
  }
  if ($outformat == "atom") {
    echo "  <entry>\n";
    echo "    <title>" . htmlentities($title, ENT_XML1, "UTF-8") . "</title>\n";
    echo "    <id>" . $crc32 . "</id>\n";
    echo "    <updated>" . date(DATE_ATOM) . "</updated>\n";
    echo "    <author>\n";
    echo "      <name>" . $author . "</name>\n";
    echo "    </author>\n";
    echo "    <dc:language>" . htmlentities($language, ENT_XML1, "UTF-8") . "</dc:language>\n";
    echo "    <dcterms:issued>" . htmlentities($pubdate, ENT_XML1, "UTF-8") . "</dcterms:issued>\n";
    echo "    <dcterms:publisher>" . htmlentities($publisher, ENT_XML1, "UTF-8") . "</dcterms:publisher>\n";
    echo "    <summary type=\"text\">" . htmlentities($description, ENT_XML1, "UTF-8") . "</summary>\n";
    echo "    <link rel=\"http://opds-spec.org/image\"\n";
    echo "        href=\"" . $_SERVER['PHP_SELF'] . "?action=getcover&amp;query={$crc32}\"\n";
    echo "        type=\"image/jpeg\"/>\n";
    echo "    <link rel=\"http://opds-spec.org/image/thumbnail\"\n";
    echo "        href=\"" . $_SERVER['PHP_SELF'] . "?action=getthumb&amp;query={$crc32}\"\n";
    echo "        type=\"image/jpeg\"/>\n";
    echo "    <link rel=\"http://opds-spec.org/acquisition\"\n";
    echo "        href=\"{$aqlink}\"\n";
    echo "        type=\"application/epub+zip\"/>\n";
    //<link href="http://www.archive.org/download/bequest_jg_librivox/bequest_jg_librivox.pdf" type="application/pdf" rel="http://opds-spec.org/acquisition"/>
    echo "  </entry>\n";
  } else if ($outformat == "html") {
    $coverurl = $_SERVER['PHP_SELF'] . "?action=getcover&amp;query=" . $crc32;
    $thumburl = $_SERVER['PHP_SELF'] . "?action=getthumb&amp;query=" . $crc32;
    echo "  <p class=\"acqimg\"><a href=\"{$coverurl}\"><img src=\"{$thumburl}\" class=\"acqlink\"></a></p>\n";
    echo "  <p class=\"acqlink\"><a href=\"{$aqlink}\">" . htmlentities($title) . "</a><span class=\"author\">" . htmlentities(" (" . $language . ")") . "<br>\n" . htmlentities($author) . "</span></p>\n";
  }
}


function printtrailer($outformat) {
  global $pver;
  if ($outformat == "atom") {
      echo "\n";
      echo "</feed>\n";
    } else if ($outformat == "html") {
      echo "  <p class=\"trailer\"><a href=\"http://sourceforge.net/p/elibsrv\" class=\"trailer\">elibsrv frontend version {$pver}</a></p>\n";
      echo "</body>\n";
      echo "</html>\n";
  }
}


function mainindex($outformat, $title) {
  printheaders($outformat, "mainmenu", $title);
  printlinks($outformat);
  printnaventry($outformat, "Authors", "action=authors", "images/authors.png");
  printnaventry($outformat, "Languages", "action=langs", "images/langs.png");
  printnaventry($outformat, "Tags", "action=tags", "images/tags.png");
  printnaventry($outformat, "Latest books", "action=latest", "images/new.png");
  printnaventry($outformat, "All books", "action=titles", "favicon.png");
  printnaventry($outformat, "Get a few random books", "action=rand", "images/dice.png");
  printtrailer($outformat);
}


function titlesindex($outformat, $db, $authorfilter, $langfilter, $tagfilter, $randflag, $latest, $search, $prettyurls) {
  printheaders($outformat, "titlesindex", "Titles");
  printlinks($outformat);

  if (! empty($authorfilter)) {
      $sqlauthorfilter = pg_escape_string($db, $authorfilter);
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books WHERE author='{$sqlauthorfilter}' ORDER BY title, language;";
    } else if (! empty($langfilter)) {
      $sqllangfilter = pg_escape_string($db, $langfilter);
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books WHERE language='{$sqllangfilter}' ORDER BY title, author;";
    } else if (! empty($tagfilter)) {
      $sqltagfilter = pg_escape_string($db, $tagfilter);
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books LEFT OUTER JOIN tags ON books.crc32=tags.book WHERE tag='{$sqltagfilter}' ORDER BY title, author, language;";
    } else if ($randflag != 0) {
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books ORDER BY random() LIMIT 5;";
    } else if ($latest > 0) {
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books WHERE modtime > NOW() - INTERVAL '{$latest} DAYS' ORDER BY modtime DESC, title, author, language;";
    } else if (! empty($search)) {
      $sqlsearch = pg_escape_string($db, $search);
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books WHERE lower(author) LIKE lower('%{$sqlsearch}%') OR lower(title) LIKE lower('%{$sqlsearch}%') ORDER BY title, author, language;";
    } else {
      $query = "SELECT crc32, title, author, description, language, publisher, pubdate FROM books ORDER BY title, author, language;";
  }
  $result = pg_query($query);

  while ($myrow = pg_fetch_assoc($result)) {
    $crc32 = $myrow['crc32'];
    $title = strip_tags($myrow['title']);
    $author = strip_tags($myrow['author']);
    $description = strip_tags($myrow['description']);
    $language = strip_tags($myrow['language']);
    $publisher = strip_tags($myrow['publisher']);
    $pubdate = strip_tags($myrow['pubdate']);
    printaqentry($outformat, $title, $crc32, $author, $language, $description, $publisher, $pubdate, $prettyurls);
  }
  pg_free_result($result);

  printtrailer($outformat);
}


function authorsindex($outformat, $db) {
  printheaders($outformat, "authorsindex", "Authors");
  printlinks($outformat);

  $query = "SELECT author FROM books WHERE author != '' GROUP BY author ORDER BY author;";
  $result = pg_query($query);

  while ($myrow = pg_fetch_assoc($result)) {
    $author = strip_tags($myrow['author']);
    printnaventry($outformat, $author, "action=titles&amp;a=" . urlencode($author), NULL);
  }
  pg_free_result($result);

  printtrailer($outformat);
}


function langsindex($outformat, $db) {
  printheaders($outformat, "langsindex", "Languages");
  printlinks($outformat);

  $query = "SELECT language FROM books WHERE language != '' GROUP BY language ORDER BY language;";
  $result = pg_query($query);

  while ($myrow = pg_fetch_assoc($result)) {
    $language = strip_tags($myrow['language']);
    printnaventry($outformat, $language, "action=titles&amp;l=" . urlencode($language), getLangIcon($language));
  }
  pg_free_result($result);

  printtrailer($outformat);
}


function tagsindex($outformat, $db) {
  printheaders($outformat, "tagsindex", "Tags");
  printlinks($outformat);

  $query = "SELECT tag FROM tags GROUP BY tag ORDER BY tag;";
  $result = pg_query($query);

  while ($myrow = pg_fetch_assoc($result)) {
    $tag = $myrow['tag'];
    printnaventry($outformat, $tag, "action=titles&amp;t=" . urlencode($tag), NULL);
  }
  pg_free_result($result);

  printtrailer($outformat);
}


function searchform($outformat) {
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\">\n";
  echo "  <ShortName>My catalog</ShortName>\n";
  echo "  <Description>Search for ebooks</Description>\n";
  echo "  <InputEncoding>UTF-8</InputEncoding>\n";
  echo "  <OutputEncoding>UTF-8</OutputEncoding>\n";
  echo "  <Image type=\"image/png\">favicon.png</Image>\n";
  echo "  <Url type=\"application/atom+xml\" template=\"" . $_SERVER['PHP_SELF'] . "?action=titles&amp;f=" . $outformat . "&amp;query={searchTerms}\"/>\n";
  echo "  <Query role=\"example\" searchTerms=\"robot\"/>\n";
  echo "</OpenSearchDescription>\n";
}

function computeThumbCacheFilename($thumbdir, $crc32) {
  if (! empty($thumbdir)) return($thumbdir . '/elibsrv-thumbcache-' . $crc32);
  return('');
}


// Load all params
include 'config.php';
$params = array();

if (! file_exists($configfile)) {
  echo "Error: failed to load parameters from {$configfile}\n";
  exit(1);
}
$params = parse_ini_file($configfile);

$dbpass = getconf($params, 'dbpass');
$dbuser = getconf($params, 'dbuser');
$dbaddr = getconf($params, 'dbaddr');
$dbname = getconf($params, 'dbname');
$prettyurls = getconf($params, 'prettyurls');
$latestdays = getconf($params, 'latestdays');
if (empty($latestdays) || $latestdays < 1) $latestdays = 60;
$thumbheight = getconf($params, 'thumbheight');
if (empty($thumbheight) || $thumbheight < 1) $thumbheight = 160;
$title = getconf($params, 'title');
if (empty($title)) $title = "Main menu";
$thumbdir = getconf($params, 'thumbdir');

$action = "";
$query = "";
$authorfilter = "";
$langfilter = "";
$tagfilter = "";
$outformat = "";

if (isset($_GET['action'])) $action = $_GET['action'];
if (isset($_GET['query'])) $query = $_GET['query'];
if (isset($_GET['a'])) $authorfilter = $_GET['a'];
if (isset($_GET['l'])) $langfilter = $_GET['l'];
if (isset($_GET['t'])) $tagfilter = $_GET['t'];
if (isset($_GET['f'])) $outformat = $_GET['f'];

if (($outformat != "html") && ($outformat != "atom")) $outformat = getDefaultOutformat();

// If this is about thumbnail, see if we can serve from cache and quit without having to connect to sql
if (($action == "getthumb") && (file_exists(computeThumbCacheFilename($thumbdir, $query)))) {
  header('Content-Type: image/jpeg');
  readfile(computeThumbCacheFilename($thumbdir, $query));
  exit(0);
}

$db = pg_connect("host={$dbaddr} dbname={$dbname} user={$dbuser} password={$dbpass} connect_timeout=5");
if ($db == FALSE) {
  echo "Error: failed to connect to the SQL database";
  exit(1);
}

if ($action == "getfile") {
    header("content-type: application/epub+zip");
    $localfilename = getLocalFilename($db, $query);
    $shortfile = basename($localfilename);
    if ($localfilename != NULL) readfile($localfilename);
  } else if ($action == "getthumb") {
    $cachethumb = computeThumbCacheFilename($thumbdir, $query);
    if (file_exists($cachethumb)) {
      header('Content-Type: image/jpeg');
      readfile($cachethumb);
    } else {
      $coverinzip = dirname(__FILE__) . "/images/nocover.png";
      $localfilename = getLocalFilename($db, $query);
      if ($localfilename != NULL) {
        $coverfile = getEpubCoverFile($localfilename);
        if ($coverfile != NULL) {
          $coverinzip = "zip://" . $localfilename . "#" . $coverfile;
        }
      }
      /* return the cover file */
      returnImageThumbnail($coverinzip, 120, $cachethumb);
    }
  } else if ($action == "getcover") {
    $coverinzip = dirname(__FILE__) . "/images/nocover.png";
    $localfilename = getLocalFilename($db, $query);
    if ($localfilename != NULL) {
      $coverfile = getEpubCoverFile($localfilename);
      if ($coverfile != NULL) {
        $coverinzip = "zip://" . $localfilename . "#" . $coverfile;
      }
    }
    /* return the cover file */
    header("content-type: " . image_type_to_mime_type(exif_imagetype($coverinzip)));
    readfile($coverinzip);
  } else if ($action == "titles") {
    titlesindex($outformat, $db, $authorfilter, $langfilter, $tagfilter, 0, 0, $query, $prettyurls);
  } else if ($action == "latest") {
    titlesindex($outformat, $db, NULL, NULL, NULL, 0, $latestdays, NULL, $prettyurls);
  } else if ($action == "authors") {
    authorsindex($outformat, $db);
  } else if ($action == "langs") {
    langsindex($outformat, $db);
  } else if ($action == "tags") {
    tagsindex($outformat, $db);
  } else if ($action == "rand") {
    titlesindex($outformat, $db, NULL, NULL, NULL, 1, 0, NULL, $prettyurls);
  } else if ($action == "searchform") {
    searchform($outformat);
  } else {
    mainindex($outformat, $title);
}

// close the SQL connection
pg_close($db);

?>
