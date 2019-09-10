<?
  // api wrapers

  function appledownparse ($url, $token)
  {
    return apidownparse ($url, "json", $token);
  }

  // fetch and analyze apple music metadata

  function fetchapple ($work, $return, $offset = 0)
  {
    global $mysql;
    
    // catalogue or title mode?

    $mode = $work["work"]["searchmode"];

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

    // searching apple music

    $token = APPMUSTOKEN;

    if ($return == "albums")
    {  
      foreach ($work["work"]["searchterms"] as $search)
      {
        $tspalbums = appledownparse (APPLEMUSICAPI. "/catalog/us/search?types=songs&offset={$offset}&limit=". APPLAPI_ITEMS. "&term=". trim(urlencode ($search. " {$work["composer"]["complete_name"]}")), $token);
        $loop = 1;
        
        while ($tspalbums["results"]["songs"]["next"] && $loop <= APPLEAPI_PAGES)
        {
          $morealbums = appledownparse (APPLEMUSICAPIBASE. $tspalbums["results"]["songs"]["next"]. "&limit=". APPLAPI_ITEMS, $token);

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

      $data = $spalbums["results"]["songs"]["data"];
    }
    else if ($return == "tracks")
    {
      $spalbums = appledownparse (APPLEMUSICAPI. "/catalog/us/albums/". $work["recording"]["apple_albumid"], $token);
      
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

      if (substr_count (str_replace ('-', '', slug($alb["attributes"]["composerName"])), str_replace ('-', '', slug($work["composer"]["name"])))) 
      {
        if ($mode == "catalogue")
        {
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

          if (sizeof ($performers))
          {
            $year = $alb["attributes"]["releaseDate"];
            
            $apple_albumid = explode ("?", end (explode ("/", $alb["attributes"]["url"])))[0];
            
            $albums[$apple_albumid][] = Array 
            (
              "similarity_between" => Array ($work["work"]["searchtitle"], worksimplifier (explode (":", $alb["attributes"]["name"])[0])),
              "mostsimilar" => $similarity,
              "full_title" => $alb["attributes"]["name"],
              "title" => trim (end (explode (":", $alb["attributes"]["name"]))),
              "similarity" => $similarity,
              "work_id" => $wid,
              "year" => $year,
              "apple_imgurl" => str_replace ("{w}x{h}", "320x320", $alb["attributes"]["artwork"]["url"]),
              "apple_albumid" => $apple_albumid,
              "performers" => $performers,
              "tracks" => sizeof ($albums[$apple_albumid])+1
            );
            $tracks[] = Array 
            (
              "full_title" => $alb["attributes"]["name"],
              "title" => trim (str_replace ("(Live)", "", end (explode (":", end (explode ("/", $alb["attributes"]["name"])))))),
              "cd" => $alb["attributes"]["discNumber"],
              "position" => $alb["attributes"]["trackNumber"],
              "length" => round ($alb["attributes"]["durationInMillis"] / 1000, 0, PHP_ROUND_HALF_UP),
              "apple_trackid" => $alb["id"],
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
        if ($ed["subset"] > 1 || ($ed["verified"] && !sizeof ($spot["items"][$ed["apple_albumid"]])))
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

    $query = "insert into recording (work_id, apple_albumid, subset, apple_imgurl) values ('{$request["wid"]}', '{$request["aid"]}', '{$request["set"]}', '{$request["cover"]}')";
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