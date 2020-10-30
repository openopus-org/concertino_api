<?
  // api wrapers

  function appledownparse ($url, $token, $usertoken = "")
  {
    return apidownparse ($url, "json", $token, $usertoken);
  }

  // searching freely on apple music 

  function searchapple ($search, $offset = 0, $country = "us")
  {
    // enforce default country

    if (!$country)
    {
      $country = "us";
    }

    // fetching apple music api

    $amres = appledownparse (APPLEMUSICAPI. "/catalog/{$country}/search?types=songs&offset={$offset}&l=en-US&limit=". APPLAPI_ITEMS. "&term=". trim(urlencode ($search)), APPMUSTOKEN);
    $loop = 1;

    while ($amres["results"]["songs"]["next"] && $loop <= APPLEAPI_PAGES)
    {
      $amresmore = appledownparse (APPLEMUSICAPIBASE. $amres["results"]["songs"]["next"]. "&l=en-US&limit=". APPLAPI_ITEMS, APPMUSTOKEN);

      $amres["results"]["songs"]["data"] = array_merge ($amres["results"]["songs"]["data"], $amresmore["results"]["songs"]["data"]);
      $amres["results"]["songs"]["next"] = $amresmore["results"]["songs"]["next"];
      $loop++;
    }

    // removing repeated ids

    foreach ($amres["results"]["songs"]["data"] as $amr)
    {
      $amrs[$amr["id"]] = $amr;
    }

    // grouping by album id, composer name and work title

    foreach ($amrs as $alb)
    {
      unset ($performers);

      if (trim ($alb["attributes"]["composerName"]) != "" && (in_array ("Classical", $alb["attributes"]["genreNames"]) || in_array ("Opera", $alb["attributes"]["genreNames"])))
      {
        $alb["artists"] = preg_split("/(\,|\&)/", $alb["attributes"]["artistName"]);

        foreach ($alb["artists"] as $kart => $art)
        {
          if (!strpos ($art["name"], "/"))
          {
            $performers[] = trim ($art);
          }
        }

        if (sizeof ($performers) && $alb["attributes"]["discNumber"])
        {
          $apple_albumid = explode ("?", end (explode ("/", $alb["attributes"]["url"])))[0];

          if (!isset ($return[$apple_albumid]))
          {
            $return[$apple_albumid] = Array 
            (
              "id" => $apple_albumid,
              "year" => $alb["attributes"]["releaseDate"],
              "apple_imgurl" => str_replace ("{w}x{h}", "320x320", $alb["attributes"]["artwork"]["url"]),
              "album_name" => $alb["attributes"]["albumName"]
            );
          }

          unset ($subtitle);

          if (isset ($alb["attributes"]["workName"]))
          {
            $work_title = $alb["attributes"]["workName"];
          }
          else
          {
            $work_title = explode(":", $alb["attributes"]["name"])[0];
          }

          preg_match ('/(\(.*?\))/i', $alb["attributes"]["name"], $matches);
  
          if (sizeof ($matches) > 0)
          {
            $subtitle = $matches[sizeof ($matches)-1];
            $work_title = str_replace ($subtitle, "", $work_title);
            $subtitle = preg_replace ('/\(|\)/', '', $subtitle);
          }

          $work_title = trim ($work_title);

          $compworks[str_replace ("-", "", slug ($alb["attributes"]["composerName"])). str_replace ("-", "", slug (worksimplifier ($work_title)))] = ["composer" => $alb["attributes"]["composerName"], "title" => $work_title];

          $singletrack = (isset ($return[$apple_albumid]["tracks"][str_replace ("-", "", slug ($alb["attributes"]["composerName"]))][str_replace ("-", "", slug (worksimplifier ($work_title)))]) ? "false" : "true");

          // treating composers names

          $alb["attributes"]["composerName"] = trim (preg_replace ('(\(.*\))', '', $alb["attributes"]["composerName"]));

          if (stristr ($alb["attributes"]["composerName"], "&") || stristr ($alb["attributes"]["composerName"], ","))
          {
            foreach (preg_split("/(\,|\&)/", $alb["attributes"]["composerName"]) as $compexpname)
            {
              $compexpnames[slug($compexpname)] = trim ($compexpname);
            }

            $alb["attributes"]["composerName"] = implode (" & ", $compexpnames);
          }

          // returning array

          $return[$apple_albumid]["tracks"][str_replace ("-", "", slug ($alb["attributes"]["composerName"]))][str_replace ("-", "", slug (worksimplifier ($work_title)))] = Array 
            (
              "id" => $alb["attributes"]["playParams"]["id"],
              "full_title" => $alb["attributes"]["name"],
              "title" => $work_title,
              "subtitle" => trim ($subtitle),
              "composer" => $alb["attributes"]["composerName"],
              "performers" => $performers,
              "singletrack" => $singletrack
            );
        }
      }
    }

    // guessing composer and works

    $guessedworks = openopusdownparse ("dyn/work/guess/", ["works"=>json_encode (array_values ($compworks))]);

    foreach ($guessedworks["works"] as $gwork)
    {
      $worksdb[str_replace ("-", "", slug ($gwork["requested"]["composer"])). "-". str_replace ("-", "", slug (worksimplifier ($gwork["requested"]["title"])))] = $gwork["guessed"];
    }

    foreach ($guessedworks["composers"] as $gcmp)
    {
      $compsdb[str_replace ("-", "", slug ($gcmp["requested"]))] = $gcmp["guessed"];
    }

    foreach ($return as $apple_albumid => $albums)
    {
      foreach ($albums["tracks"] as $comp => $wks)
      {
        foreach ($wks as $wk => $track)
        {
          if (isset ($worksdb[$comp. "-". $wk]))
          {
            $rwork = $worksdb[$comp. "-". $wk];
          }
          else 
          {
            $rwork = [
              "id" => "at*{$track["id"]}", 
              "title" => $track["title"], 
              "genre"=>"None"];

            if (isset ($compsdb[str_replace ("-", "", slug ($track["composer"]))]))
            {
              $rwork["composer"] = $compsdb[str_replace ("-", "", slug ($track["composer"]))];
            }
            else
            {
              $rwork["composer"] = [
                "complete_name" => $track["composer"],
                "id" => "0",
                "name" => $track["composer"],
                "epoch" => "None"]; 
            }
          }

          $rreturn[] = Array
            (
              "apple_albumid" => (string) $apple_albumid,
              "set" => 1,
              "verified" => "false",
              "cover" => $albums["apple_imgurl"],
              "performers" => $track["performers"],
              "work" => $rwork,
              "album_name" => $albums["album_name"],
              "compilation" => "false",
              "singletrack" => $track["singletrack"]
            );
        }
      }
    }

    return ["recordings" => $rreturn, "next" => (isset ($amres["results"]["songs"]["next"]) ? "true" : "false")];
  }

  // fetch a recording using only apple music data

  function detailapple ($apple_albumid, $trackid, $country = "us")
  {
    if (!$country)
    {
      $country = "us";
    }

    $spalbums = appledownparse (APPLEMUSICAPI. "/catalog/{$country}/albums/{$apple_albumid}?l=en-US", APPMUSTOKEN);
    
    $extras = Array 
      (
        "label" => $spalbums["data"][0]["attributes"]["recordLabel"],
        "cover" => str_replace ("{w}x{h}", "320x320", $spalbums["data"][0]["attributes"]["artwork"]["url"]),
        "year" => $spalbums["data"][0]["attributes"]["releaseDate"]
      );

    $data = $spalbums["data"][0]["relationships"]["tracks"]["data"];

    foreach ($data as $alb)
    {
      if ($alb["id"] == $trackid)
      {
        // treating composers names

        $alb["attributes"]["composerName"] = trim (preg_replace ('(\(.*\))', '', $alb["attributes"]["composerName"]));

        if (stristr ($alb["attributes"]["composerName"], "&") || stristr ($alb["attributes"]["composerName"], ","))
        {
          foreach (preg_split("/(\,|\&)/", $alb["attributes"]["composerName"]) as $compexpname)
          {
            $compexpnames[slug($compexpname)] = trim ($compexpname);
          }

          $alb["attributes"]["composerName"] = implode (" & ", $compexpnames);
        }

        $extras["composer"]["complete_name"] = $alb["attributes"]["composerName"];
        
        // treating work title

        if (isset ($alb["attributes"]["workName"]))
        {
          $work_title = $alb["attributes"]["workName"];
        }
        else
        {
          $work_title = explode(":", $alb["attributes"]["name"])[0];
        }

        preg_match ('/(\(.*?\))/i', $alb["attributes"]["name"], $matches);

        if (sizeof ($matches) > 0)
        {
          $subtitle = $matches[sizeof ($matches)-1];
          $work_title = str_replace ($subtitle, "", $work_title);
          $subtitle = preg_replace ('/\(|\)/', '', $subtitle);
        }

        $extras["work"]["title"] = trim ($work_title);
        $extras["work"]["subtitle"] = trim ($subtitle);
      }
    }

    // guessing composer and works

    $guessedworks = openopusdownparse ("dyn/work/guess/", ["works"=>"[". json_encode (["composer" => $extras["composer"]["complete_name"], "title" => $extras["work"]["title"]]). "]"]);

    if (isset ($guessedworks["composers"]))
    {
      $extras["composer"] = $guessedworks["composers"][0]["guessed"];
    }
    else
    {
      $extras["composer"] =
        [
          "id" => "0",
          "name" => $extras["composer"]["complete_name"],
          "complete_name" => $extras["composer"]["complete_name"],
          "epoch" => "None"
        ];
    }

    foreach ($data as $kalb => $alb)
    {
      unset ($performers);

      if (isset ($alb["attributes"]["workName"]))
      {
        $work_title = $alb["attributes"]["workName"];
      }
      else
      {
        $work_title = explode(":", $alb["attributes"]["name"])[0];
      }

      preg_match ('/(\(.*?\))/i', $alb["attributes"]["name"], $matches);

      if (sizeof ($matches) > 0)
      {
        $subtitle = $matches[sizeof ($matches)-1];
        $work_title = trim (str_replace ($subtitle, "", $work_title));
      }

      if ($work_title == $extras["work"]["title"])
      {
        $alb["artists"] = preg_split("/(\,|\&)/", $alb["attributes"]["artistName"]);
        foreach ($alb["artists"] as $kart => $art)
        {
          if (!strpos ($art["name"], "/"))
          {
            $performers[] = trim ($art);
          }
        }

        if (sizeof ($performers) && $alb["attributes"]["discNumber"])
        {
          $year = $alb["attributes"]["releaseDate"];
          
          $apple_albumid = explode ("?", end (explode ("/", $alb["attributes"]["url"])))[0];
          
          $albums[$apple_albumid][] = Array 
          (
            "similarity_between" => Array ($work["work"]["searchtitle"], worksimplifier (explode (":", $alb["attributes"]["name"])[0])),
            "mostsimilar" => $similarity,
            "full_title" => $alb["attributes"]["name"],
            "title" => trim (end (explode (":", $alb["attributes"]["name"]))),
            "album_name" => $alb["attributes"]["albumName"],
            "similarity" => $similarity,
            "work_id" => $wid,
            "year" => $year,
            "apple_imgurl" => str_replace ("{w}x{h}", "320x320", $alb["attributes"]["artwork"]["url"]),
            "apple_albumid" => (string) $apple_albumid,
            "performers" => $performers,
            "tracks" => (in_array ($alb["attributes"]["playParams"]["id"], $usedtracks) ? sizeof ($albums[$apple_albumid]) : sizeof ($albums[$apple_albumid])+1)
          );
          
          $usedtracks[] = $alb["attributes"]["playParams"]["id"];

          $tracks[] = Array 
          (
            "full_title" => $alb["attributes"]["name"],
            "title" => trim (str_replace ("(Live)", "", end (explode (":", end (explode ("/", $alb["attributes"]["name"])))))),
            "cd" => $alb["attributes"]["discNumber"],
            "position" => $alb["attributes"]["trackNumber"],
            "length" => round ($alb["attributes"]["durationInMillis"] / 1000, 0, PHP_ROUND_HALF_UP),
            "apple_trackid" => $alb["id"],
            "preview" => $alb["attributes"]["previews"][0]["url"],
            "performers" => $performers
          );
        }
      } 
    }

    $stats = Array 
      (
        "apple_responses" => count ($data),
        "useful_responses" => count ($tracks),
        "usefulness_rate" => round(100*(count ($tracks)/count ($data)), 2). "%"
      );

    return Array ("type"=> "tracks", "items"=>$tracks, "stats"=>$stats, "extras"=>$extras);
  }

  // fetch album details from apple

  function albumapple ($apple_albumid, $country = "us")
  {
    if (!$country)
    {
      $country = "us";
    }

    $spalbums = appledownparse (APPLEMUSICAPI. "/catalog/{$country}/albums/{$apple_albumid}?l=en-US", APPMUSTOKEN);
    
    $extras = Array 
      (
        "title" => $spalbums["data"][0]["attributes"]["name"],
        "label" => $spalbums["data"][0]["attributes"]["recordLabel"],
        "cover" => str_replace ("{w}x{h}", "320x320", $spalbums["data"][0]["attributes"]["artwork"]["url"]),
        "year" => $spalbums["data"][0]["attributes"]["releaseDate"]
      );

    $data = $spalbums["data"][0]["relationships"]["tracks"]["data"];

    foreach ($data as $alb)
    {
      if (isset ($alb["attributes"]["workName"]))
      {
        $work_title = $alb["attributes"]["workName"];
      }
      else
      {
        $work_title = explode(":", $alb["attributes"]["name"])[0];
      }

      preg_match ('/(\(.*?\))/i', $alb["attributes"]["name"], $matches);

      if (sizeof ($matches) > 0)
      {
        $subtitle = $matches[sizeof ($matches)-1];
        $work_title = str_replace ($subtitle, "", $work_title);
      }

      unset ($performers);
      $alb["artists"] = preg_split("/(\,|\&)/", $alb["attributes"]["artistName"]);
      foreach ($alb["artists"] as $kart => $art)
      {
        if (!strpos ($art["name"], "/"))
        {
          $performers[] = trim ($art);
        }
      }

      $tracks[$alb["attributes"]["composerName"]. " | ". worksimplifier ($work_title) /*. " | ". $alb["attributes"]["artistName"]*/][] = Array (
        "composer" => $alb["attributes"]["composerName"],
        "work" => trim ($work_title),
        "full_title" => $alb["attributes"]["name"],
        "title" => trim (str_replace ("(Live)", "", end (explode (":", end (explode ("/", $alb["attributes"]["name"])))))),
        "cd" => $alb["attributes"]["discNumber"],
        "position" => $alb["attributes"]["trackNumber"],
        "length" => round ($alb["attributes"]["durationInMillis"] / 1000, 0, PHP_ROUND_HALF_UP),
        "apple_trackid" => $alb["id"],
        "preview" => $alb["attributes"]["previews"][0]["url"],
        "performers" => $performers
      );

      $works[] = ["composer" => $alb["attributes"]["composerName"], "title" => trim ($work_title)];
    }

    // guessing composer and works

    $guessedworks = openopusdownparse ("dyn/work/guess/", ["works"=>json_encode ($works)]);

    foreach ($guessedworks["works"] as $gwork)
    {
      $worksdb[str_replace ("-", "", slug ($gwork["requested"]["composer"])). "-". str_replace ("-", "", slug (worksimplifier ($gwork["requested"]["title"])))] = $gwork["guessed"];  
    }

    foreach ($guessedworks["composers"] as $gcmp)
    {
      $compsdb[str_replace ("-", "", slug ($gcmp["requested"]))] = $gcmp["guessed"];
    }

    // compiling album array

    $allperformers = Array ();

    foreach ($tracks as $ktr => $tr)
    {
      $comp = str_replace ("-", "", slug ($tr[0]["composer"]));
      $wk = str_replace ("-", "", slug (worksimplifier ($tr[0]["work"])));

      if (isset ($worksdb[$comp. "-". $wk]))
      {
        $rwdb = $worksdb[$comp. "-". $wk];
        $rwork = Array 
          (
            "id" => $rwdb["id"],
            "genre" => $rwdb["genre"],
            "title" => $rwdb["title"],
            "subtitle" => $rwdb["subtitle"],
            "composer" => Array 
              (
                "id" => $rwdb["composer"]["id"],
                "name" => $rwdb["composer"]["name"],
                "complete_name" => $rwdb["composer"]["complete_name"],
                "epoch" => $rwdb["composer"]["epoch"]
              )
          );
      }
      else 
      {
        $rwork = [
          "id" => "at*{$tr[0]["apple_trackid"]}", 
          "title" => $tr[0]["work"], 
          "genre"=>"None"];

        if (isset ($compsdb[$comp]))
        {
          $rwdbc = $compsdb[$comp];
          $rwork["composer"] = Array 
            (
              "id" => $rwdbc["id"],
              "name" => $rwdbc["name"],
              "complete_name" => $rwdbc["complete_name"],
              "epoch" => $rwdbc["epoch"]
            );
        }
        else
        {
          $rwork["composer"] = [
            "complete_name" => $tr[0]["composer"],
            "id" => "0",
            "name" => $tr[0]["composer"],
            "epoch" => "None"]; 
        }
      }

      $return[$ktr] = Array (
        "work" => $rwork,
        "performers" => $tr[0]["performers"]
      );

      foreach ($tr as $track)
      {
        $return[$ktr]["tracks"][] = Array (
          "title" => $track["title"],
          "cd" => $track["cd"],
          "position" => $track["position"],
          "length" => $track["length"],
          "apple_trackid" => $track["apple_trackid"],
          "preview" => $track["preview"],
          "performers" => $track["performers"]
        );

        $allperformers = array_merge ($allperformers, $track["performers"]);
      }
    }

    // detecting multiple recordings of a same work

    $perfsdb = openopusdownparse ("dyn/performer/list/", ["names"=>json_encode ($allperformers)]);

    foreach ($return as $workkeys => $work)
    {
      foreach ($work["tracks"] as $track)
      {
        $fullperformers = allperformers ($track["performers"], $perfsdb["performers"]["digest"], $work["work"]["composer"]["complete_name"]);
        $performers = array_slice ($fullperformers, -2, 2, true);
        $newkey = "wkid-". $work["work"]["id"]. "-". slug(implode ("-", arraykeepvalues ($performers, ["name"])));
        
        if (array_key_exists ($newkey, $newreturn))
        {
          $newreturn[$newkey]["tracks"][] = $track;
        }
        else
        {
          $newreturn[$newkey] = ["work" => $work["work"], "performers" => $fullperformers, "tracks" => [$track]];          
        }
      }
    }

    $return = $newreturn;

    $stats = Array 
      (
        "apple_responses" => count ($data),
        "useful_responses" => count ($data),
        "usefulness_rate" => "100%"
      );

    return Array ("type"=> "tracks", "items"=>array_values ($return), "stats"=>$stats, "extras"=>$extras);
  }

  // fetch and analyze apple music metadata

  function fetchapple ($work, $return, $offset = 0, $pagelimit = 0, $country = "us", $extra = "")
  {
    global $mysql;
    
    // catalogue or title mode?

    $mode = $work["work"]["searchmode"];

    // enforce default country

    if (!$country)
    {
      $country = "us";
    }

    // creating works titles reference

    if ($mode == "title")
    {
      $wkdb = ($work["similarlytitled"] ? $work["similarlytitled"] : []);

      // adding search terms of the work to the reference

      foreach ($work["work"]["searchterms"] as $st)
      {
        array_unshift ($wkdb, Array 
          (
            "id" => $work["work"]["id"],
            "title" => $work["work"]["title"],
            "searchterm" => $st,
            "similarity" => 100
          ));
      }
    }

    // hoboken catalogue exception

    if (stristr ($work["work"]["catalogue"], "Hob."))
    {
      $work["work"]["catalogue"] = str_replace ("Hob.", "Hob( |\.)*", $work["work"]["catalogue"]);
      $work["work"]["searchterms"] = ["hob ". $work["work"]["catalogue_number"]];
    }

    // BWV catalogue exception

    if ($work["work"]["catalogue"] == "BWV")
    {
      $work["work"]["searchterms"] = ["BWV.". $work["work"]["catalogue_number"], "BWV ". $work["work"]["catalogue_number"]];
    }

    // searching apple music

    $token = APPMUSTOKEN;

    if ($return == "albums")
    {  
      foreach ($work["work"]["searchterms"] as $search)
      {
        if ($extra) $search .= " ". $extra;
        $tspalbums = appledownparse (APPLEMUSICAPI. "/catalog/{$country}/search?types=songs&offset={$offset}&l=en-US&limit=". APPLAPI_ITEMS. "&term=". trim(urlencode ($search. " {$work["composer"]["complete_name"]}")), $token);
        $loop = 1;
        
        //print_r ($tspalbums);
        while ($tspalbums["results"]["songs"]["next"] && $loop <= ($pagelimit ? $pagelimit : APPLEAPI_PAGES))
        {
          $morealbums = appledownparse (APPLEMUSICAPIBASE. $tspalbums["results"]["songs"]["next"]. "&l=en-US&limit=". APPLAPI_ITEMS, $token);

          $tspalbums["results"]["songs"]["data"] = array_merge ($tspalbums["results"]["songs"]["data"], $morealbums["results"]["songs"]["data"]);
          $tspalbums["results"]["songs"]["next"] = $morealbums["results"]["songs"]["next"];
          $loop++;
        }

        if ($spalbums)
        {
          $spalbums["results"]["songs"]["data"] = array_merge ($spalbums["results"]["songs"]["data"], $tspalbums["results"]["songs"]["data"]);
        }
        else
        {
          $spalbums = $tspalbums;
        }
      }

      // removing repeated ids

      foreach ($spalbums["results"]["songs"]["data"] as $amr)
      {
        $amrs[$amr["id"]] = $amr;
      }

      $data = $amrs;
    }
    else if ($return == "tracks")
    {
      $spalbums = appledownparse (APPLEMUSICAPI. "/catalog/{$country}/albums/". $work["recording"]["apple_albumid"]. "?l=en-US", $token);

      $extras = Array 
        (
          "label" => $spalbums["data"][0]["attributes"]["recordLabel"],
          "cover" => str_replace ("{w}x{h}", "320x320", $spalbums["data"][0]["attributes"]["artwork"]["url"]),
          "year" => $spalbums["data"][0]["attributes"]["releaseDate"]
        );

      $data = $spalbums["data"][0]["relationships"]["tracks"]["data"];
    }

    foreach ($data as $kalb => $alb)
    {
      $simwkdb = 0;
      $mostwkdb = 0;
      $mostwkdbtitle = "";
      
      $alb["attributes"]["name"] = preg_replace ('/^(( )*( |\,|\(|\'|\"|\-|\;|\:)( )*)/i', '', $alb["attributes"]["name"], 1);

      if (substr_count (str_replace ('-', '', slug ($alb["attributes"]["composerName"])), str_replace ('-', '', slug(explode (",", str_ireplace ("jr", "", $work["composer"]["name"]))[0])))) 
      {
        if ($mode == "catalogue")
        {
          //echo '/('. str_replace (' ', '( )*', str_replace ("/", "\/", $work["work"]["catalogue"])). ')(( |\.))*('. str_replace (' ', '( )*', $work["work"]["catalogue_number"]). '($| |\W))/i';
          preg_match_all ('/('. str_replace (' ', '( )*', str_replace ("/", "\/", $work["work"]["catalogue"])). ')(( |\.))*('. str_replace (' ', '( )*', $work["work"]["catalogue_number"]). '($| |\W))/i', $alb["attributes"]["name"], $trmatches);

          if (sizeof ($trmatches[0])) 
          {
            if ($work["work"]["additional_number"])
            {
              preg_match_all ('/(no\.)( )*'. $work["work"]["additional_number"]. '($| |\W)/i', $alb["attributes"]["name"], $tropmatches);

              if (sizeof ($tropmatches[0])) $mostwkdb = $work["work"]["id"];
            }
            else
            {
              $mostwkdb = $work["work"]["id"];
            }
          }
        }
        else
        {
          $alb["attributes"]["name"] = str_replace ("&", "and", $alb["attributes"]["name"]);

          //echo "\nTRYING: ". str_replace ('-', '', slug ($alb["attributes"]["name"]));

          foreach ($wkdb as $wk)
          {
            similar_text (str_replace (" ", "", $wk["searchterm"]), str_replace (" ", "", worksimplifier (explode (":", $alb["attributes"]["name"])[0], true)), $sim);
            //echo "\n [{$wk["searchterm"]}] [$sim] - ". $alb["attributes"]["name"];
            if (stripos (str_replace ('-', '', slug ($alb["attributes"]["name"])), str_replace ('-', '', slug ($wk["searchterm"]))) !== false && $sim > $simwkdb) 
            {
              //echo "\n [{$wk["searchterm"]}] [$sim] - ". $alb["attributes"]["name"];
              //echo "\nFOUND: ". $wk["searchterm"];
              $simwkdb = $sim;
              $mostwkdb = $wk["id"];
              $mostwkdbtitle = $wk["searchterm"];
            }
          }
  
          //echo "\nGUESSED: ". $mostwkdb. " - ". $mostwkdbtitle. " [". $alb["attributes"]["name"]. "]";
        }
        
        if ($mostwkdb == $work["work"]["id"])
        {
          //echo $alb["name"]. "\n\n";
          unset ($performers);

          $alb["artists"] = preg_split("/(\,|\&)/", $alb["attributes"]["artistName"]);
          foreach ($alb["artists"] as $kart => $art)
          {
            if (!strpos ($art["name"], "/"))
            {
              $performers[] = trim ($art);
            }
          }

          if (sizeof ($performers) && $alb["attributes"]["discNumber"])
          {
            $year = $alb["attributes"]["releaseDate"];
            
            $apple_albumid = explode ("?", end (explode ("/", $alb["attributes"]["url"])))[0];
            
            $albums[$apple_albumid][] = Array 
            (
              "similarity_between" => Array ($work["work"]["searchtitle"], worksimplifier (explode (":", $alb["attributes"]["name"])[0])),
              "mostsimilar" => $similarity,
              "full_title" => $alb["attributes"]["name"],
              "title" => trim (end (explode (":", $alb["attributes"]["name"]))),
              "album_name" => $alb["attributes"]["albumName"],
              "similarity" => $similarity,
              "work_id" => $wid,
              "year" => $year,
              "apple_imgurl" => str_replace ("{w}x{h}", "320x320", $alb["attributes"]["artwork"]["url"]),
              "apple_albumid" => $apple_albumid,
              "performers" => $performers,
              "tracks" => (in_array ($alb["attributes"]["playParams"]["id"], $usedtracks) ? sizeof ($albums[$apple_albumid]) : sizeof ($albums[$apple_albumid])+1)
            );
            
            $usedtracks[] = $alb["attributes"]["playParams"]["id"];

            $tracks[] = Array 
            (
              "full_title" => $alb["attributes"]["name"],
              "title" => trim (str_replace ("(Live)", "", end (explode (":", end (explode ("/", $alb["attributes"]["name"])))))),
              "cd" => $alb["attributes"]["discNumber"],
              "position" => $alb["attributes"]["trackNumber"],
              "length" => round ($alb["attributes"]["durationInMillis"] / 1000, 0, PHP_ROUND_HALF_UP),
              "apple_trackid" => $alb["id"],
              "preview" => $alb["attributes"]["previews"][0]["url"],
              "performers" => $performers
            );
          }

          $mostsimilar = $similarity;
        }
        //else echo "\nREJECTED:". str_replace ('-', '', slug ($alb["attributes"]["name"]));
      }
      //else echo "\nComposer Error -- ". slug($alb["attributes"]["composerName"]). " == ". slug($work["composer"]["name"]);
    }

    $stats = Array 
      (
        "apple_responses" => count ($data),
        "useful_responses" => count (${$return}),
        "usefulness_rate" => round(100*(count (${$return})/count ($data)), 2). "%"
      );

    if ($return == "albums" && $spalbums["results"]["songs"]["next"])
    {
      $extras = Array ("next"=>$spalbums["results"]["songs"]["next"]);  
    }

    return Array ("type"=> $return, "items"=>${$return}, "stats"=>$stats, "extras"=>$extras);
  }

  // add concertino own extradata to apple metadata

  function extradata ($spot, $params)
  {
    global $mysql;

    if ($params["aid"])
    {
      $where = "work_id={$params["wid"]} and apple_albumid='{$params["aid"]}' and subset={$params["set"]}";
    }
    else
    {
      $where = "work_id={$params["wid"]}";
    }

    $extrarecordings = mysqlfetch ($mysql, "select ifnull(observation,'') observation, apple_imgurl, apple_albumid, subset, year, recommended, compilation, oldaudio, verified, wrongdata, spam, badquality from recording where ". $where);
    $extraperformers = mysqlfetch ($mysql, "select apple_albumid, subset, performer, role from recording_performer where " . $where . " order by apple_albumid asc, subset asc");

    if ($params["aid"]) $extratracks = mysqlfetch ($mysql, "select cd, position, length, title, apple_trackid from track where " . $where . " order by apple_albumid asc, subset asc, cd asc, position asc");

    if ($extratracks)
    {
      $extratracks[sizeof ($extratracks)-1]["performers"] = end($spot["items"])["performers"];
      $spot["items"] = $extratracks;
    }

    foreach ($extrarecordings as $ed)
    {
      if ($params["aid"])
      {
        if ($ed["year"]) $spot["extras"]["year"] = $ed["year"];
        if ($ed["observation"]) $spot["extras"]["observation"] = $ed["observation"];
        if ($ed["verified"]) $spot["extras"]["verified"] = "true";
      }
      else
      {
        if ($ed["subset"] > 1)
        // || ($ed["verified"] && !sizeof ($spot["items"][$ed["apple_albumid"]])))
        {
          $spot["items"]["{$ed["apple_albumid"]}-{$ed["subset"]}"][0] = $ed;
          $spot["items"]["{$ed["apple_albumid"]}-{$ed["subset"]}"][0]["tracks"] = 2;
        }
        else 
        {
          $pos = sizeof ($spot["items"][$ed["apple_albumid"]]) - 1;

          if ($pos >= 0)
          {
            if ($ed["observation"]) $spot["items"][$ed["apple_albumid"]][$pos]["observation"] = $ed["observation"];
            if ($ed["year"]) $spot["items"][$ed["apple_albumid"]][$pos]["year"] = $ed["year"];
            if ($ed["compilation"]) $spot["items"][$ed["apple_albumid"]][$pos]["compilation"] = true;
            if ($ed["oldaudio"]) $spot["items"][$ed["apple_albumid"]][$pos]["historic"] = true;
            if ($ed["verified"]) $spot["items"][$ed["apple_albumid"]][$pos]["verified"] = true;
            if ($ed["recommended"]) $spot["items"][$ed["apple_albumid"]][$pos]["recommended"] = true;

            if ($ed["badquality"] || $ed["wrongdata"] || $ed["spam"]) 
            {
              unset ($spot["items"][$ed["apple_albumid"]]);
            }
          }
        }
      }
    }

    foreach ($extraperformers as $ep)
    {
      $array = Array ("name"=>$ep["performer"],"role"=>$ep["role"]);

      if ($params["aid"])
      {
        $spot["items"][sizeof($spot["items"])-1]["extraperformers"][] = $array;
      }
      else
      {
        if ($ep["subset"] > 1 || $spot["items"]["{$ep["apple_albumid"]}-{$ep["subset"]}"][0]["verified"])
        {
          $spot["items"]["{$ep["apple_albumid"]}-{$ep["subset"]}"][0]["extraperformers"][] = $array;
        }
        else
        {
          $pos = sizeof ($spot["items"][$ep["apple_albumid"]]) - 1;
          
          if ($pos >= 0) $spot["items"][$ep["apple_albumid"]][$pos]["extraperformers"][] = $array;
        }
      }
    }

    return $spot;
  }

  // inserting a recording into the recording abstract database

  function insertrecording ($request)
  {
    global $mysql;

    $query = "insert into recording (work_id, composer_name, work_title, apple_albumid, subset, apple_imgurl) values ('{$request["wid"]}', '{$request["composer"]}', '{$request["work"]}', '{$request["aid"]}', '{$request["set"]}', '{$request["cover"]}')";
    mysqli_query ($mysql, $query);

    // inserting performers into the recording abstract database

    if (mysqli_affected_rows ($mysql) > 0)
    {
      $performers = json_decode ($request["performers"], true);

      foreach ($performers as $pk => $pf)
      {
        $nperfs[] = Array ("performer"=>$pf["name"], "role"=>$pf["role"], "work_id"=>$request["wid"], "apple_albumid"=>$request["aid"], "subset"=>$request["set"]);
      }

      mysqlmultinsert ($mysql, "recording_performer", $nperfs);
    }

    return true;
  }

  // retrieving the favorite recording ids from a specified user

  function favrecordings ($uid)
  {
    global $mysql;

    $return = [];
    $recordings = mysqlfetch ($mysql, "select concat(recording.work_id,'-',recording.apple_albumid,'-',recording.subset) as id from recording, user_recording where user_recording.user_id = '{$uid}' and user_recording.work_id = recording.work_id and user_recording.apple_albumid = recording.apple_albumid and user_recording.subset = recording.subset and user_recording.favorite = 1");
    
    foreach ($recordings as $rec)
    {
      $return[] = $rec["id"];
    }

    return $return;
  }