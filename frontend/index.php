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


$pver = "20161225";


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
  if (preg_match("/^.* Opera\/.*$/", $ua) == 1) return("html");

  // Detect Safari (as used by the kindle browser)
  if (preg_match("/^.* Safari\/.*$/", $ua) == 1) return("html");

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
  $result = $db->query($query);
  $myrow = $result->fetchArray();
  if ($myrow) {
    $res['filename'] = $myrow['file'];
    $res['format'] = sqlformat2human($myrow['format']);
  } else {
    echo "Error: no matching file.\n";
    $res = NULL;
  }
  return($res);
  $result->finalize();
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


function printaqentry($outformat, $title, $crc32, $author, $language, $description, $publisher, $pubdate, $catdate, $moddate, $prettyurls, $filesize, $filename, $format, $kindlegenbin) {
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
    $meta['aqlinkmobi'] =  "files/{$crc32}/" . rawurlencode($author . " - " . $title) . '.mobi';
  } else {
    $meta['aqlink'] = $_SERVER['PHP_SELF'] . "?action=getfile&amp;query=" . $crc32;
    $meta['aqlinkmobi'] = $_SERVER['PHP_SELF'] . "?action=getmobi&amp;query=" . $crc32;
  }
  if ((empty($kindlegenbin)) || ($format != 'epub')) $meta['aqlinkmobi'] = NULL; // drop the mobi link if no kindlegenbin path is configured, or source file is not epub
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


function titlesindex($outformat, $db, $authorfilter, $langfilter, $tagfilter, $randcount, $latest, $search, $prettyurls, $kindlegenbin, $pagesize, $pagenum, $action) {
  $fieldslist = 'crc32, title, author, description, language, publisher, pubdate, modtime, moddate, file, filesize, format';
  printheaders($outformat, "titlesindex", "Titles");

  $psz = $pagesize + 1;
  if ($pagenum < 1) $pagenum = 1;
  $offset = ($pagenum - 1) * $pagesize;

  if (! empty($authorfilter)) {
    $sqlauthorfilter = $db->escapeString($authorfilter);
    $query = "SELECT {$fieldslist} FROM books WHERE author='{$sqlauthorfilter}' ORDER BY title, language LIMIT {$psz} OFFSET {$offset};";
  } else if (! empty($langfilter)) {
    if (strlen($langfilter) > 2) {
      $sqllangfilter = ''; // non-2 letter 'language' is 'unknown'
    } else {
      $sqllangfilter = $db->escapeString($langfilter);
    }
    $query = "SELECT {$fieldslist} FROM books WHERE language='{$sqllangfilter}' ORDER BY title, author LIMIT {$psz} OFFSET {$offset};";
  } else if (! empty($tagfilter)) {
    $sqltagfilter = $db->escapeString($tagfilter);
    $query = "SELECT {$fieldslist} FROM books LEFT OUTER JOIN tags ON books.crc32=tags.book WHERE tag='{$sqltagfilter}' ORDER BY title, author, language LIMIT {$psz} OFFSET {$offset};";
  } else if ($randcount != 0) {
    // sqlite is *extremely* slow when it comes to running ORDER BY random(), so instead I have to use a quite contrapted way: get the number of rows in the table, fetch the CRCs of 5 random offsets, and finally build a temporary query with a filter on the crc set
    $crclist = array();
    for ($i = 0; $i < $randcount; $i++) {
      $result = $db->query('SELECT crc32 FROM books LIMIT 1 OFFSET ABS(random()) % (SELECT count(*) FROM books);');
      $row = $result->fetchArray();
      $crclist[$i] = $row[0]; // do not be tempted to convert with intval, the max value of an INT is 2^31 on many systems, while I need all 32bits of the CRC32 values - safer to keep them simply as strings
      $result->finalize();
    }
    $query = "SELECT {$fieldslist} FROM books WHERE crc32 IN (" . implode(',',$crclist) . ');';
  } else if ($latest > 0) {
    $query = "SELECT {$fieldslist} FROM books WHERE modtime > strftime('%s', 'now') - {$latest}*86400 ORDER BY modtime DESC, title, author, language LIMIT {$psz} OFFSET {$offset};";
  } else if (! empty($search)) {
    $sqlsearch = $db->escapeString($search);
    $query = "SELECT {$fieldslist} FROM books WHERE lower(author) LIKE lower('%{$sqlsearch}%') OR lower(title) LIKE lower('%{$sqlsearch}%') ORDER BY title, author, language LIMIT {$psz} OFFSET {$offset};";
  } else {
    $query = "SELECT {$fieldslist} FROM books ORDER BY title, author, language LIMIT {$psz} OFFSET {$offset};";
  }
  $result = $db->query($query);

  /* fetch results into an array and count items, detecting if there is a next page at the same time */
  $resultset = array();
  $resultcnt = 0;
  $nextpage = -1;
  $prevpage = -1;
  while ($myrow = $result->fetchArray()) {
    if ($resultcnt < $pagesize) {
      $resultset[$resultcnt++] = $myrow;
    } else {
      $nextpage = $pagenum + 1;
    }
  }
  $result->finalize();
  if ($pagenum > 1) $prevpage = $pagenum - 1;

  if ($nextpage > 0) printnaventry($outformat, "Next page", "action=" . $action . "&amp;p={$nextpage}", "images/pgnext.png");
  if ($prevpage > 0) printnaventry($outformat, "Previous page", "action={$action}&amp;p={$prevpage}", "images/pgprev.png");

  foreach ($resultset AS $myrow) {
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
    printaqentry($outformat, $title, $crc32, $author, $language, $description, $publisher, $pubdate, $catdate, $moddate, $prettyurls, $filesize, $filename, $format, $kindlegenbin);
  }

  printtrailer($outformat);
}


function authorsindex($outformat, $db) {
  printheaders($outformat, "authorsindex", "Authors");

  //$query = "SELECT author FROM books WHERE author != '' GROUP BY author ORDER BY author;";
  $query = "SELECT UPPER(SUBSTR(author,1,1)) AS fletter, COUNT(DISTINCT author) AS count FROM books GROUP BY fletter ORDER BY fletter;";
  $result = $db->query($query);

  while ($myrow = $result->fetchArray()) {
    $fletter = strip_tags($myrow['fletter']);
    printnaventry($outformat, "{$fletter} ({$myrow['count']} entries)", "action=authorsl&amp;a=" . urlencode($fletter), NULL);
  }
  $result->finalize();

  printtrailer($outformat);
}


function authorsindex_letter($outformat, $db, $query) {
  printheaders($outformat, "authorsindex", "Authors");

  $query = "SELECT author FROM books WHERE UPPER(author) LIKE '{$query}%' GROUP BY author ORDER BY author;";
  $result = $db->query($query);

  while ($myrow = $result->fetchArray()) {
    $author = strip_tags($myrow['author']);
    printnaventry($outformat, $author, "action=titles&amp;a=" . urlencode($author), NULL);
  }
  $result->finalize();

  printtrailer($outformat);
}


function langsindex($outformat, $db) {
  printheaders($outformat, "langsindex", "Languages");

  $query = "SELECT (CASE WHEN language = '' THEN 'unknown' ELSE language END) AS lang FROM books GROUP BY lang ORDER BY lang;";
  $result = $db->query($query);

  while ($myrow = $result->fetchArray()) {
    $language = strip_tags($myrow['lang']);
    printnaventry($outformat, $language, "action=titles&amp;l=" . urlencode($language), getLangIcon($language));
  }
  $result->finalize();

  printtrailer($outformat);
}


function tagsindex($outformat, $db) {
  printheaders($outformat, "tagsindex", "Tags");

  $query = "SELECT tag FROM tags GROUP BY tag ORDER BY tag;";
  $result = $db->query($query);

  while ($myrow = $result->fetchArray()) {
    $tag = $myrow['tag'];
    printnaventry($outformat, $tag, "action=titles&amp;t=" . urlencode($tag), NULL);
  }
  $result->finalize();

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


// first of all, check that all dependencies are met
if (!function_exists('gd_info')) {
  echo "Error: PHP GD library not installed";
  exit(1);
}
if (!extension_loaded('sqlite3')) {
  echo "Error: PHP sqlite3 extension not enabled";
  exit(1);
}

// Load all params
include 'config.php';
$params = array();

if (! file_exists($configfile)) {
  echo "Error: failed to load parameters from {$configfile}\n";
  exit(1);
}
$params = parse_ini_file($configfile);

$dbfile = getconf($params, 'dbfile');
$prettyurls = getconf($params, 'prettyurls');
$latestdays = getconf($params, 'latestdays');
if (empty($latestdays) || $latestdays < 1) $latestdays = 60;
$thumbheight = getconf($params, 'thumbheight');
if (empty($thumbheight) || $thumbheight < 1) $thumbheight = 160;
$title = getconf($params, 'title');
if (empty($title)) $title = "Main menu";
$thumbdir = getconf($params, 'thumbdir');
$kindlegenbin = getconf($params, 'kindlegenbin');
$pagesize = getconf($params, 'pagesize');
if (empty($pagesize) || ($pagesize < 1)) $pagesize = 10;
$randcount = getconf($params, 'randcount');
if (empty($randcount) || ($randcount < 1)) $randcount = 5;

$action = "";
$query = "";
$authorfilter = "";
$langfilter = "";
$tagfilter = "";
$outformat = "";
$pagenum = 0;

if (isset($_GET['action'])) $action = $_GET['action'];
if (isset($_GET['query'])) $query = $_GET['query'];
if (isset($_GET['a'])) $authorfilter = $_GET['a'];
if (isset($_GET['l'])) $langfilter = $_GET['l'];
if (isset($_GET['t'])) $tagfilter = $_GET['t'];
if (isset($_GET['f'])) $outformat = $_GET['f'];
if (isset($_GET['p'])) $pagenum = intval($_GET['p']);

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

// The main menu doesn't require any db access, so let's handle it right away
if ($action == "") {
  mainindex($outformat, $title);
  exit(0);
}

// "connect" to the sql db
try {
  $db = new SQLite3($dbfile, SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
  echo "Error: failed to open database - have you launched elibsrv's indexer yet?";
  exit(1);
}
$db->busyTimeout(20000); // avoid 'lock' errors in case the elibsrv backend is updating stuff at the same time


if ($action == "getfile") {
  $localfile = getLocalFilename($db, $query);
  if ($localfile != NULL) {
    if ($localfile['format'] == 'epub') header('content-type: application/epub+zip');
    if ($localfile['format'] == 'pdf') header('content-type: application/pdf');
    readfile($localfile['filename']);
  }
} else if ($action == "getmobi") {
  if (empty($kindlegenbin)) {
    header('content-type: text/html');
    echo "<html><head></head><body>mobi conversion not available, because kindlegenbin not set.</body></html>\n";
  } else {
    $localfile = getLocalFilename($db, $query);
    if ($localfile != NULL) {
      // build the filename of the mobi file
      $localfilemobi = pathinfo($localfile['filename'], PATHINFO_DIRNAME) . '/' . pathinfo($localfile['filename'], PATHINFO_FILENAME) . '.mobi';
      // if doesn't exist yet, convert it using the kindlegen binary
      $kindleconvertcmd = $kindlegenbin . ' ' . escapeshellarg($localfile['filename']);
      if (! file_exists($localfilemobi)) {
        $execres = exec($kindleconvertcmd);
      }
      // if file still doesn't exist, then something went wrong
      if (! file_exists($localfilemobi)) {
        header('content-type: text/html');
        echo "<html><head></head><body>ERROR: mobi conversion failed. please check your kindlegenbin setting.<br>{$kindleconvertcmd}<br><br><pre>{$execres}</pre></body></html>\n";
      } else {
        // finally, return the mobi file
        header('content-type: application/x-mobipocket-ebook');
        readfile($localfilemobi);
      }
    }
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
  returnImageThumbnail($coverinzip, $thumbheight, computeThumbCacheFilename($thumbdir, $query));
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
  titlesindex($outformat, $db, $authorfilter, $langfilter, $tagfilter, 0, 0, $query, $prettyurls, $kindlegenbin, $pagesize, $pagenum, $action);
} else if ($action == "latest") {
  titlesindex($outformat, $db, NULL, NULL, NULL, 0, $latestdays, NULL, $prettyurls, $kindlegenbin, $pagesize, $pagenum, $action);
} else if ($action == "authors") {
  authorsindex($outformat, $db);
} else if ($action == "authorsl") {
  authorsindex_letter($outformat, $db, $authorfilter);
} else if ($action == "langs") {
  langsindex($outformat, $db);
} else if ($action == "tags") {
  tagsindex($outformat, $db);
} else if ($action == "rand") {
  titlesindex($outformat, $db, NULL, NULL, NULL, $randcount, 0, NULL, $prettyurls, $kindlegenbin, $pagesize, $pagenum, $action);
} else if ($action == "searchform") {
  searchform($outformat);
} else {
  mainindex($outformat, $title);
}

// close the SQL connection
$db->close();

?>
