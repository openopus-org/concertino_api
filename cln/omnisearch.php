<?
  chdir ($_SERVER["CTINHTMLDIR"]);
  include_once ("../lib/inc.php");

  $forbidden = implode ("(.*)|", $omnisearch_forbidden);
  $forbidden_regexp = '\\\b('. $forbidden. '(.*))\\\b';

  mysqli_query ($mysql, "truncate table omnisearch");
  mysqli_query ($mysql, "insert into omnisearch select concat(complete_name, ' ', title, ' ', COALESCE(subtitle,''), ' ', COALESCE(searchterms, ''), ' ', GROUP_CONCAT(REGEXP_REPLACE(performer, '{$forbidden_regexp}', '') separator ' ')) summary, concat(complete_name, ' ', title) worksummary, complete_name, work.composer_id, r.work_id, r.apple_albumid, r.subset, work.recommended, work.popular from recording r, recording_performer rp, openopus.composer, openopus.work where r.work_id=rp.work_id and r.apple_albumid=rp.apple_albumid and r.subset=rp.subset and r.work_id=work.id and composer_id = composer.id group by apple_albumid, work_id, subset");