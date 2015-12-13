<?php

/*
 * elibsrv - a light OPDS indexing server for EPUB ebooks.
 *
 * Copyright (C) 2014-2016 Mateusz Viste
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


$pver = "20151213";


// include output plugins
require 'out_html.php';
require 'out_atom.php';
require 'out_dump.php';


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


function sqlformat2human($sqlformat) {
  switch (intval($sqlformat)) {
    case 0: return('epub');
    case 1: return('pdf');
    default: return('unknown');
  }
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
  $ua = "";
  if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
  }
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
  $largest_img = NULL;
  $largest_img_size = 0;
  $za = zip_open($epubfile);
  if (! is_resource($za)) return(NULL);
  // here I should look for $za being valid
  for (;;) {
    $ze = zip_read($za);
    if ($ze == FALSE) break; // no more entries in the zip structure
    $filename = zip_entry_name($ze);
    $filesize = zip_entry_filesize($ze);
    if (substr($filename, -1) != '/') {
      // remember the largest image in the archive
      if ((preg_match("/^.*\.(jpeg|jpg|png)$/", $filename) == 1) && ($filesize > $largest_img_size)) {
        $largest_img = $filename;
        $largest_img_size = $filesize;
      }
      // look for images that contain the word "cover"
      if (preg_match("/^.*[cC][oO][vV][eE][rR].*\.(jpeg|jpg|png)$/", $filename) == 1) {
        $result = $filename;
        break;
      }
      // img1.jpg is an often used filename for the cover - let's remember it, in case we find nothing better
      if ((preg_match("/^.*\/img1.jpg$/", $filename) == 1) && ($result == NULL)) $result = $filename;
    }
  }
  zip_close($za);
  // if no "sure" result found, then fallback to the largest image that was found
  if ($result == NULL) $result = $largest_img;
  return($result);
}


// returns an array with two elements:
//   filename  - the local filename
//   format    - the file format (epub, pdf..)
function getLocalFilename($db, $query) {
  $res = array();
  if (! is_numeric($query)) {
    echo "Error: invalid query.\n";
    return(NULL);
  }
  $query = "SELECT file,format FROM books WHERE crc32={$query};";
  $result = pg_query($query);
  $myrow = pg_fetch_assoc($result);
  if ($myrow) {
    $res['filename'] = $myrow['file'];
    $res['format'] = sqlformat2human($myrow['format']);
    pg_free_result($result);
    return($res);
  } else {
    echo "Error: no matching file.\n";
    pg_free_result($result);
    return(NULL);
  }
}


function printheaders($outformat, $pageid, $pagetitle) {
  $pageinfo = array();
  $pageinfo['id'] = $pageid;
  $pageinfo['title'] = $pagetitle;
  $pageinfo['self'] = $_SERVER['PHP_SELF'];
  if ($outformat == "atom") {
    printheaders_atom($pageinfo);
  } else if ($outformat == "html") {
    printheaders_html($pageinfo);
  } else if ($outformat == "dump") {
    printheaders_dump($pageinfo);
  }
}


function printnaventry($outformat, $title, $urlparams, $iconfile) {
  $nav = array();
  $nav['title'] = $title;
  $nav['url'] = $_SERVER['PHP_SELF'] . '?f=' . $outformat . '&amp;' . $urlparams;
  $nav['icon'] = $iconfile;
  if ($outformat == "atom") {
    printnaventry_atom($nav);
  } else if ($outformat == "html") {
    printnaventry_html($nav);
  } else if ($outformat == "dump") {
    printnaventry_dump($nav);
  }
}


function printaqentry($outformat, $title, $crc32, $author, $language, $description, $publisher, $pubdate, $catdate, $moddate, $prettyurls, $filesize, $filename, $format) {
  // prepare the array with metadata
  $meta = array();
  $meta['title'] = $title;
  $meta['crc'] = $crc32;
  $meta['author'] = $author;
  $meta['lang'] = $language;
  $meta['desc'] = $description;
  $meta['publisher'] = $publisher;
  $meta['pubdate'] = $pubdate;
  $meta['catdate'] = $catdate;
  $meta['moddate'] = $moddate;
  $meta['filesize'] = intval($filesize);
  $meta['filename'] = $filename;
  $meta['format'] = $format;
  if ($prettyurls == 1) { // the epub link can have different forms, depending on the "pretty URLs" setting
    $meta['aqlink'] = "files/{$crc32}/" . rawurlencode($author . " - " . $title) . '.' . $format;
  } else {
    $meta['aqlink'] = $_SERVER['PHP_SELF'] . "?action=getfile&amp;query=" . $crc32;
  }
  $meta['coverlink'] = $_SERVER['PHP_SELF'] . "?action=getcover&amp;query={$crc32}";
  $meta['thumblink'] = $_SERVER['PHP_SELF'] . "?action=getthumb&amp;query={$crc32}";

  // call the appropriate output plugin
  if ($outformat == "atom") {
    printaqentry_atom($meta);
  } else if ($outformat == "html") {
    printaqentry_html($meta);
  } else if ($outformat == "dump") {
    printaqentry_dump($meta);
  }
}


function printtrailer($outformat) {
  global $pver;

  $info = array('version'=>$pver, 'homepage'=>"http://elibsrv.sourceforge.net");

  if ($outformat == "atom") {
    printtrailer_atom($info);
  } else if ($outformat == "html") {
    printtrailer_html($info);
  } else if ($outformat == "dump") {
    printtrailer_dump($info);
  }
}


function mainindex($outformat, $title) {
  printheaders($outformat, "mainmenu", $title);
  printnaventry($outformat, "Authors", "action=authors", "images/authors.png");
  printnaventry($outformat, "Languages", "action=langs", "images/langs.png");
  printnaventry($outformat, "Tags", "action=tags", "images/tags.png");
  printnaventry($outformat, "Latest books", "action=latest", "images/new.png");
  printnaventry($outformat, "All books", "action=titles", "favicon.png");
  printnaventry($outformat, "Get a few random books", "action=rand", "images/dice.png");
  printtrailer($outformat);
}


function titlesindex($outformat, $db, $authorfilter, $langfilter, $tagfilter, $randflag, $latest, $search, $prettyurls) {
  $fieldslist = 'crc32, title, author, description, language, publisher, pubdate, modtime, moddate, file, filesize, format';
  printheaders($outformat, "titlesindex", "Titles");

  if (! empty($authorfilter)) {
    $sqlauthorfilter = pg_escape_string($db, $authorfilter);
    $query = "SELECT {$fieldslist} FROM books WHERE author='{$sqlauthorfilter}' ORDER BY title, language;";
  } else if (! empty($langfilter)) {
    $sqllangfilter = pg_escape_string($db, $langfilter);
    $query = "SELECT {$fieldslist} FROM books WHERE language='{$sqllangfilter}' ORDER BY title, author;";
  } else if (! empty($tagfilter)) {
    $sqltagfilter = pg_escape_string($db, $tagfilter);
    $query = "SELECT {$fieldslist} FROM books LEFT OUTER JOIN tags ON books.crc32=tags.book WHERE tag='{$sqltagfilter}' ORDER BY title, author, language;";
  } else if ($randflag != 0) {
    $query = "SELECT {$fieldslist} FROM books ORDER BY random() LIMIT 5;";
  } else if ($latest > 0) {
    $query = "SELECT {$fieldslist} FROM books WHERE modtime > NOW() - INTERVAL '{$latest} DAYS' ORDER BY modtime DESC, title, author, language;";
  } else if (! empty($search)) {
    $sqlsearch = pg_escape_string($db, $search);
    $query = "SELECT {$fieldslist} FROM books WHERE lower(author) LIKE lower('%{$sqlsearch}%') OR lower(title) LIKE lower('%{$sqlsearch}%') ORDER BY title, author, language;";
  } else {
    $query = "SELECT {$fieldslist} FROM books ORDER BY title, author, language;";
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
    $catdate = strtotime($myrow['modtime']);
    $moddate = strtotime($myrow['moddate']);
    $filesize = $myrow['filesize'];
    $filename = $myrow['file'];
    $format = sqlformat2human($myrow['format']);
    printaqentry($outformat, $title, $crc32, $author, $language, $description, $publisher, $pubdate, $catdate, $moddate, $prettyurls, $filesize, $filename, $format);
  }
  pg_free_result($result);

  printtrailer($outformat);
}


function authorsindex($outformat, $db) {
  printheaders($outformat, "authorsindex", "Authors");

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

// validate that outformat is a known value
if (! in_array($outformat, array('atom', 'dump', 'html'))) {
  $outformat = getDefaultOutformat();
}

// If this is about thumbnail, see if we can serve from cache and quit without having to connect to sql
if (($action == "getthumb") && (file_exists(computeThumbCacheFilename($thumbdir, $query)))) {
  header('Content-Type: image/jpeg'); // cached thumbnails are ALWAYS jpeg files
  readfile(computeThumbCacheFilename($thumbdir, $query));
  exit(0);
}

$db = pg_connect("host={$dbaddr} dbname={$dbname} user={$dbuser} password={$dbpass} connect_timeout=5");
if ($db == FALSE) {
  echo "Error: failed to connect to the SQL database";
  exit(1);
}

if ($action == "getfile") {
  $localfile = getLocalFilename($db, $query);
  if ($localfile != NULL) {
    if ($localfile['format'] == 'epub') header('content-type: application/epub+zip');
    if ($localfile['format'] == 'pdf') header('content-type: application/pdf');
    readfile($localfile['filename']);
  }
} else if ($action == "getthumb") {
  $coverinzip = dirname(__FILE__) . "/images/nocover.png";
  $localfile = getLocalFilename($db, $query);
  if ($localfile != NULL) {
    if ($localfile['format'] == 'epub') {
      $coverfile = getEpubCoverFile($localfile['filename']);
      if ($coverfile != NULL) {
        $coverinzip = "zip://" . $localfile['filename'] . "#" . $coverfile;
      }
    }
  }
  /* return the cover file */
  returnImageThumbnail($coverinzip, 120, $cachethumb);
} else if ($action == "getcover") {
  $coverinzip = dirname(__FILE__) . "/images/nocover.png";
  $localfile = getLocalFilename($db, $query);
  if ($localfile != NULL) {
    if ($localfile['format'] == "epub") {
      $coverfile = getEpubCoverFile($localfile['filename']);
      if ($coverfile != NULL) {
        $coverinzip = "zip://" . $localfile['filename'] . "#" . $coverfile;
      }
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
