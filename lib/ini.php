<?
  // removing notices

  error_reporting (E_ALL ^ (E_WARNING | E_NOTICE));

  // header json

  if (!$nojson)
  {
    header ("Content-Type: application/json");
  }

  // global constants

  define ("SOFTWARENAME", "Concertino");
  define ("SOFTWAREVERSION", "1.19.07.13");
  define ("USERAGENT", SOFTWARENAME. "/" . SOFTWAREVERSION. " ( ". SOFTWAREMAIL. " )");
  define ("RECRETS", 60);
  define ("MIN_SIMILAR", 40);
  define ("MIN_COMPIL_RATIO", 0.8);
  define ("MIN_COMPIL_UNIVERSE", 10);
  define ("MIN_RELEVANCE_PERFORMER", 5);
  define ("APPLAPI_ITEMS", 25);
  define ("APPLEAPI_PAGES", 5);
  define ("API_RETURN", "json");
  define ("HASH_SALT", "vUJmLwFgniCBmqcreBbsX9Jb");
  define ("CATALOGUE_REGEX", "/\,( )*(bwv|hwv|op|opus|d|k|kv|hess|woo|fs|k\.anh|wq|w|sz|kk|m|s|h|trv|rv|jb ([0-9]+\:)|jw ([a-z]+\/)|(hob\.([a-z])+\:))( |\.)*(([0-9]+)([a-z])?)/i");

  // apple music constants

  define ("APPLEMUSICAPIBASE", "https://api.music.apple.com");
  define ("APPLEMUSICAPI", APPLEMUSICAPIBASE. "/v1");

  // open opus 

  define ("OPENOPUS", "http://api.openopus.org");

  // helper libraries

  include_once (UTILIB. "/lib.php");
  include_once (LIB. "/lib.php");

  // api init

  $starttime = microtime (true);
  $apireturn = Array ("status" => Array ("version" => SOFTWAREVERSION));

  // db init

  $mysql = mysqli_connect (DBHOST, DBUSER, DBPASS, INSTANCE. "_". DBDB);
  mysqli_set_charset ($mysql, "utf8");
