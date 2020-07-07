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
  define ("SOFTWAREVERSION", "1.20.6");
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
  
  // apple music constants

  define ("APPLEMUSICAPIBASE", "https://api.music.apple.com");
  define ("APPLEMUSICAPI", APPLEMUSICAPIBASE. "/v1");
  define ("APPLEMUSICAMPAPI", "https://amp-api.music.apple.com/v1");
  define ("APPLEMUSICFRONTEND", "https://music.apple.com/us/browse");

  // open opus 

  define ("OPENOPUS", "http://api.openopus.org");

  // helper libraries

  include_once (UTILIB. "/lib.php");
  include_once (LIB. "/lib.php");

  // omnisearch forbidden words

  $omnisearch_forbidden = Array ("symphony", "sinfonietta", "orquesta", "orchestra", "symphonic", "philharmonic", "duo", "trio", "quartet", "quintet", "sextet", "septet", "octet");

  // api init

  $starttime = microtime (true);
  $apireturn = Array ("status" => Array ("version" => SOFTWAREVERSION));

  // db init

  $mysql = mysqli_connect (DBHOST, DBUSER, DBPASS, INSTANCE. "_". DBDB);
  mysqli_set_charset ($mysql, "utf8");
