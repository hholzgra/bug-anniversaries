<?php

$date_str = date("Y-m-d");


$end_time = strtotime("$date_str 12:00:00");


ob_start();

echo "<?xml version='1.0' encoding='UTF-8'?>
<rss version='2.0'>
<channel>
<title>MySQL Bug Anniversaries</title>
<link>http://php-groupies.de/</link>
<description>MySQL Bug Anniversaries by Day</description>
<language>en-us</language>
<ttl>1440</ttl>"; 




for ($i = 0; $i < 300 ; $i++) {
  @mkdir("archive/".date("Y/m", $end_time - $i * 86400), 0777, true);
  $day_str = "archive/".date("Y/m/Y-m-d", $end_time - $i * 86400);

  $body = get_body($day_str);

  $link = "http://php-groupies.de/mysql-bugs/$day_str.html";
  $guid = md5($body);
  $pubdate = strftime("%a, %d %b %Y 00:00:00 +0200", strtotime($day_str));

  $title = "$day_str MySQL Bug Anniversaries";

  if (!file_exists("$day_str.html")) {
    ob_start();
    echo "<html><head><title>$title</title></head><body>\n";
    echo "$body\n";
    echo "</body></html>";
    file_put_contents("$day_str.html", ob_get_clean());
  }

  echo "
<item>
<title>$title</title>
<link>$link</link>
<pubdate>$pubdate</pubdate>
<guid>$guid</guid>
<description>".htmlspecialchars($body)."</description>
</item>";
}
echo "</channel></rss>"; 

file_put_contents("bugs.rss", ob_get_clean());

copy("archive/".date("Y/m")."/$date_str.html", "today.html");

exit(0);


function get_body($date_str)
{
  $date_str = basename($date_str);
  $body_file = "cache/bodies/$date_str.body";

  if (file_exists($body_file)) {
    return file_get_contents($body_file);
  } else {
    $body = generate_body($date_str);
    file_put_contents($body_file, $body);
    return $body;
  }
}

function generate_body($date_str) 
{
  $date_str = basename($date_str);
  ob_start();

  $title = "$date_str MySQL Bug Anniversaries";
  echo "<h1>$title</h1>\n";

  $base_url = "http://bugs.mysql.com/search-csv.php?status[]=Active&os=0&bug_age=0&order_by=id&limit=100&defect_class=all&workaround_viability=all&impact=all&fix_risk=all&fix_effort=all&begin=";

  $begin = 0;

  $bugs = array();

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
	
	if (preg_match("|$month_day_str |", $entered) && !preg_match("|$date_str |", $entered)) {
	  $bugs[$id] = array("date" => substr($entered, 0, 10), "summary" => $summary);
	}
      } 
    }
  } while ($bug_count > 0);
  
  $last_date = "";
  foreach ($bugs as $id => $bug) {
    if ($bug["date"] != $last_date) {
      $last_date = $bug["date"];
      $age = date("Y") - date("Y", strtotime($bug["date"]));
      echo "<hr/><b>$age years old:</b><br/>\n";
    } 
    printf("<tt>%05d</tt> ", $id);
    echo "<a href='http://bugs.mysql.com/$id'>$bug[summary]</a><br/>\n";
  }

  return ob_get_clean();
}

function fopen_cached($url) 
{
  $cache_dir = "cache/".date("Y-m-d");
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
