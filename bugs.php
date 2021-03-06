#! /usr/bin/env php
<?php

$max_days = 20;
$max_rss_days = 20;
$force = false;

require_once "vendor/autoload.php";

$t_loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($t_loader, [
    'cache' => false, // 'cache/twig_compilation_cache',
]);

$date_str = date("Y-m-d");


$end_time = strtotime("$date_str 12:00:00");


ob_start();

echo "<?xml version='1.0' encoding='UTF-8'?>
<rss version='2.0'>
<channel>
<title>MySQL Bug Anniversaries</title>
<link>http://mysql-bug-anniversaries.db-stuff.org/</link>
<description>MySQL Bug Anniversaries by Day</description>
<language>en-us</language>
<ttl>1440</ttl>"; 

for ($i = 0; $i < $max_days; $i++) {
  @mkdir("www/archive/".date("Y/m", $end_time - $i * 86400), 0777, true);
  $day_str = "www/archive/".date("Y/m/Y-m-d", $end_time - $i * 86400);
  $link = "./archive/".date("Y/m/Y-m-d", $end_time - $i * 86400).".html";
  
  $body = get_body($day_str, $force);

  $guid = md5($body);
  $pubdate = strftime("%a, %d %b %Y 00:00:00 +0200", strtotime($day_str));

  $title = basename($day_str) . " MySQL Bug Anniversaries";

  if ($force || !file_exists("$day_str.html")) {
    error_log("generating ".basename($day_str));
    file_put_contents("$day_str.html", $twig->render('bug-day.html', [ 'title' => $title, 'body' => $body ]));
  }

  if ($i < $max_rss_days) {
  echo "
<item>
<title>$title</title>
<link>$link</link>
<pubdate>$pubdate</pubdate>
<guid>$guid</guid>
<description>".htmlspecialchars($body)."</description>
</item>";
  }
}
echo "</channel></rss>"; 

file_put_contents("www/bugs.rss", ob_get_clean());

copy("www/archive/".date("Y/m")."/$date_str.html", "www/index.html");

exit(0);


function get_body($date_str, $force=false)
{
  $date_str = basename($date_str);
  $body_file = "cache/bodies/$date_str.body";

  if (!$force && file_exists($body_file)) {
    return file_get_contents($body_file);
  } else {
    $body = generate_body($date_str);
    file_put_contents($body_file, $body);
    return $body;
  }
}

function generate_body($date_str) 
{
  global $twig;
  
  $date_str = basename($date_str);

  $base_url = "http://bugs.mysql.com/search-csv.php?status[]=Active&os=0&bug_age=0&order_by=id&limit=100&defect_class=all&workaround_viability=all&impact=all&fix_risk=all&fix_effort=all&begin=";

  $begin = 0;

  $bugs = [];

  do {
    $bug_count = 0;
    
    $url = $base_url.$begin;
    $begin += 100;
    
    $fp = fopen_cached($url);
    if (!$fp) die("Can't open URL $url");
    
    $month_day_str = substr($date_str, 5);

    while (($data = fgetcsv($fp, 1000, ",")) !== FALSE) {
      list($id, $entered, $modified, $type, $status, $severity, $version, $os, $summary) = $data;
      
      if (is_numeric($id)) {
	$bug_count++;

	$date = substr($entered, 0, 10);
	$age = date("Y") - date("Y", strtotime($date));

	
	if (preg_match("|$month_day_str |", $entered) && !preg_match("|$date_str |", $entered)) {
	  if (!isset($bugs[$age])) $bugs[$age] = [];
	  $bugs[$age][$id] = array("date" => $date, "summary" => $summary);
	}
      } 
    }
  } while ($bug_count > 0);

  krsort($bugs);

  $years = [];
  $max_age = 0;
  foreach($bugs as $age => $list) {
    $years[$age] = [ 'age' => $age, 'year' => date("Y") - $age, 'count' => count($list) ];
    $max_age = max($max_age, $age);
  }
  for ($age = 1; $age <= $max_age; $age++) {
    if (!isset($years[$age])) $years[$age] = ['age' => $age, 'year' => date("Y") - $age, 'count' => 0 ];
  }
  krsort($years);

  return $twig->render('bug-anniversary-section.html', [ 'today' => $date_str, 'sections' => $bugs, 'years' => $years ]);
}

function fopen_cached($url) 
{
  $cache_dir = "cache/bugs/".date("Y/m/Y-m-d");
  $cache_file = "$cache_dir/".md5($url);

  if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0755, true);
  }

  if(!file_exists($cache_file)) {
    $body = file_get_contents($url);
    file_put_contents($cache_file, $body);
  }

  return fopen($cache_file, "r");
}
