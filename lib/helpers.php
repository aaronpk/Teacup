<?php

ORM::configure('mysql:host=' . Config::$dbHost . ';dbname=' . Config::$dbName);
ORM::configure('username', Config::$dbUsername);
ORM::configure('password', Config::$dbPassword);

function render($page, $data) {
  global $app;
  return $app->render('layout.php', array_merge($data, array('page' => $page)));
};

function partial($template, $data=array(), $debug=false) {
  global $app;

  if($debug) {
    $tpl = new Savant3(\Slim\Extras\Views\Savant::$savantOptions);
    echo '<pre>' . $tpl->fetch($template . '.php') . '</pre>';
    return '';
  }

  ob_start();
  $tpl = new Savant3(\Slim\Extras\Views\Savant::$savantOptions);
  foreach($data as $k=>$v) {
    $tpl->{$k} = $v;
  }
  $tpl->display($template . '.php');
  return ob_get_clean();
}

function js_bookmarklet($partial, $context) {
  return str_replace('+','%20',urlencode(str_replace(array("\n"),array(''),partial($partial, $context))));
}

function session($key) {
  if(array_key_exists($key, $_SESSION))
    return $_SESSION[$key];
  else
    return null;
}

function k($a, $k, $default=null) {
  if(is_array($k)) {
    $result = true;
    foreach($k as $key) {
      $result = $result && array_key_exists($key, $a);
    }
    return $result;
  } else {
    if(is_array($a) && array_key_exists($k, $a) && $a[$k])
      return $a[$k];
    elseif(is_object($a) && property_exists($a, $k) && $a->$k)
      return $a->$k;
    else
      return $default;
  }
}

function parse_geo_uri($uri) {
  if(preg_match('/geo:([\-\+]?[0-9\.]+),([\-\+]?[0-9\.]+)/', $uri, $match)) {
    return array(
      'latitude' => (double)$match[1],
      'longitude' => (double)$match[2],
    );
  } else {
    return false;
  }
}

function get_timezone($lat, $lng) {
  try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://timezone-api.geoloqi.com/timezone/'.$lat.'/'.$lng);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $tz = @json_decode($response);
    if($tz)
      return new DateTimeZone($tz->timezone);
  } catch(Exception $e) {
    return null;
  }
  return null;
}

function micropub_post($endpoint, $params, $access_token) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token
  ));
  curl_setopt($ch, CURLOPT_POST, true);
  $post = http_build_query(array_merge(array(
    'h' => 'entry'
  ), $params));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  $response = curl_exec($ch);
  $error = curl_error($ch);
  $sent_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
  $request = $sent_headers . $post;
  return array(
    'request' => $request,
    'response' => $response,
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

function micropub_get($endpoint, $params, $access_token) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint . '?' . http_build_query($params));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token
  ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $data = array();
  if($response) {
    parse_str($response, $data);
  }
  $error = curl_error($ch);
  return array(
    'response' => $response,
    'data' => $data,
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

function get_syndication_targets(&$user) {
  $targets = array();

  $r = micropub_get($user->micropub_endpoint, array('q'=>'syndicate-to'), $user->micropub_access_token);
  if($r['data'] && array_key_exists('syndicate-to', $r['data'])) {
    $targetURLs = preg_split('/, ?/', $r['data']['syndicate-to']);
    foreach($targetURLs as $t) {

      // If the syndication target doesn't have a scheme, add http
      if(!preg_match('/^http/', $t))
        $tmp = 'http://' . $t;

      // Parse the target expecting it to be a URL
      $url = parse_url($tmp);

      // If there's a host, and the host contains a . then we can assume there's a favicon
      // parse_url will parse strings like http://twitter into an array with a host of twitter, which is not resolvable
      if(array_key_exists('host', $url) && strpos($url['host'], '.') !== false) {
        $targets[] = array(
          'target' => $t,
          'favicon' => 'http://' . $url['host'] . '/favicon.ico'
        );
      } else {
        $targets[] = array(
          'target' => $t,
          'favicon' => false
        );
      }
    }
  }
  if(count($targets)) {
    $user->syndication_targets = json_encode($targets);
    $user->save();
  }

  return array(
    'targets' => $targets,
    'response' => $r
  );
}

function static_map($latitude, $longitude, $height=174, $width=300, $zoom=14) {
  return 'http://static-maps.pdx.esri.com/img.php?marker[]=lat:' . $latitude . ';lng:' . $longitude . ';icon:small-green-cutout&basemap=topo&width=' . $width . '&height=' . $height . '&zoom=' . $zoom;
}

function relative_time($date) {
  static $rel;
  if(!isset($rel)) {
    $config = array(
        'language' => '\RelativeTime\Languages\English',
        'separator' => ', ',
        'suffix' => true,
        'truncate' => 1,
    );
    $rel = new \RelativeTime\RelativeTime($config);
  }
  return $rel->timeAgo($date);
}

function entry_url($entry, $user) {
  return $entry->canonical_url ?: Config::$base_url . $user->url . '/' . $entry->id;
}

function entry_date($entry, $user) {
  $date = new DateTime($entry->published);
  $tz = new DateTimeZone($entry->timezone);
  $date->setTimeZone($tz);
  return $date;
}

function default_drink_options() {
  return [
    'Coffee',
    'Beer',
    'Cocktail',
    'Tea',
    'Mimosa',
    'Latte',
    'Champagne'
  ];
}

function default_food_options() {
  return [
    'Burrito',
    'Banana',
    'Pizza',
    'Soup',
    'Tacos',
    'Mac and Cheese'
  ];
}

function query_user_nearby_options($type, $user_id, $latitude, $longitude) {
  $published = date('Y-m-d H:i:s', strtotime('-4 months'));
  $options = [];
  $optionsQ = ORM::for_table('entries')->raw_query('
    SELECT *, SUM(num) AS num FROM
    (
    SELECT id, published, content, type,
      round(gc_distance(latitude, longitude, :latitude, :longitude) / 1000) AS dist, 
      COUNT(1) AS num
    FROM entries
    WHERE user_id = :user_id
      AND type = :type
      AND gc_distance(latitude, longitude, :latitude, :longitude) IS NOT NULL
      AND published > :published /* only look at the last 4 months of posts */
    GROUP BY content, round(gc_distance(latitude, longitude, :latitude, :longitude) / 1000) /* group by 1km buckets */
    ORDER BY round(gc_distance(latitude, longitude, :latitude, :longitude) / 1000), COUNT(1) DESC /* order by distance and frequency */
    ) AS tmp
    WHERE num > 2 /* only include things that have been used more than 2 times in this 1km bucket */
    GROUP BY content /* group by name again */
    ORDER BY SUM(num) DESC /* order by overall frequency */ 
    LIMIT 4   
  ', ['user_id'=>$user_id, 'type'=>$type, 'latitude'=>$latitude, 'longitude'=>$longitude, 'published'=>$published])->find_many();
  foreach($optionsQ as $o) {
    $options[] = [
      'title' => $o->content,
      'subtitle' => query_last_eaten($user_id, $o->type, $o->content),
      'type' => $o->type
    ];
  }
  return $options;
}

function query_user_frequent_options($type, $user_id) {
  $published = date('Y-m-d H:i:s', strtotime('-4 months'));
  $options = [];
  $optionsQ = ORM::for_table('entries')->raw_query('
    SELECT type, content, MAX(published) AS published
    FROM entries
    WHERE user_id = :user_id
      AND type = :type
      AND published > :published
    GROUP BY content
    ORDER BY COUNT(1) DESC
    LIMIT 4
  ', ['user_id'=>$user_id, 'type'=>$type, 'published'=>$published])->find_many();
  foreach($optionsQ as $o) {
    $options[] = [
      'title' => $o->content,
      'subtitle' => query_last_eaten($user_id, $o->type, $o->content),
      'type' => $o->type
    ];
  }
  return $options;
}

function query_last_eaten($user_id, $type, $content) {
  $lastQ = ORM::for_table('entries')->raw_query('
    SELECT published, timezone
    FROM entries
    WHERE user_id=:user_id
      AND type=:type
      AND content=:content
    ORDER BY published DESC 
    LIMIT 1; 
  ', ['user_id'=>$user_id, 'type'=>$type, 'content'=>$content])->find_one();
  if(!$lastQ)
    return '';

  $timestamp = strtotime($lastQ->published);
  // If less than 8 hours ago, use relative time, otherwise show the actual time
  if(time() - $timestamp > 60*60*8) {
    $date = new DateTime($lastQ->published);
    $date->setTimeZone(new DateTimeZone($lastQ->timezone));
    return $date->format('D, M j, g:ia');
  } else {
    $config = array(
      'language' => '\RelativeTime\Languages\English',
      'separator' => ', ',
      'suffix' => true,
      'truncate' => 1,
    );
    $relativeTime = new \RelativeTime\RelativeTime($config);
    return $relativeTime->timeAgo($lastQ->published);
  }
}

function get_entry_options($user_id, $latitude=null, $longitude=null) {
  /* 
    Sections:
    * Recent 2 posts (food + drink combined)
    * Drinks (based on location)
      * custom box below
    * Food (based on location)
      * custom box below

    If no recent entries, remove that section.
    If no nearby food/drinks, use a default list
  */

  $recent = [];
  $drinks = [];
  $food = [];

  $recentQ = ORM::for_table('entries')->raw_query('
    SELECT type, content FROM
      (SELECT * 
      FROM entries
      WHERE user_id = :user_id
      ORDER BY published DESC) AS tmp
    GROUP BY content
    ORDER BY MAX(published) DESC
    LIMIT 4', ['user_id'=>$user_id])->find_many();
  foreach($recentQ as $r) {
    $recent[] = [
      'title' => $r->content,
      'subtitle' => query_last_eaten($user_id, $r->type, $r->content),
      'type' => $r->type
    ];
  }

  if($latitude) {
    $drinks = query_user_nearby_options('drink', $user_id, $latitude, $longitude);
  }
  // If there's no nearby data (like if the user isn't including location) then return the most frequently used ones instead
  if(count($drinks) == 0) {
    $drinks = query_user_frequent_options('drink', $user_id);
  }
  // If there's less than 4 options available, fill the list with the default options
  if(count($drinks) < 4) {
    $default = default_drink_options();
    while(count($drinks) < 4) {
      $next = array_shift($default);
      if(!in_array(['title'=>$next,'type'=>'drink'], $drinks)) {
        $drinks[] = [
          'title' => $next,
          'subtitle' => query_last_eaten($user_id, 'drink', $next),
          'type' => 'drink'
        ];
      }
    }
  }

  if($latitude) {
    $food = query_user_nearby_options('eat', $user_id, $latitude, $longitude);
  }
  if(count($food) == 0) {
    $food = query_user_frequent_options('eat', $user_id);
  }
  if(count($food) < 4) {
    $default = default_food_options();
    while(count($food) < 4) {
      $next = array_shift($default);
      if(!in_array(['title'=>$next,'type'=>'eat'], $food)) {
        $food[] = [
          'title' => $next,
          'subtitle' => query_last_eaten($user_id, 'eat', $next),
          'type' => 'eat'
        ];
      }
    }
  }


  $options = [
    'sections' => [
      [
        'title' => 'Recent',
        'items' => $recent
      ],
      [
        'title' => 'Drinks',
        'items' => $drinks
      ],
      [
        'title' => 'Food',
        'items' => $food
      ]
    ]
  ];

  if(count($options['sections'][0]['items']) == 0)
    array_shift($options['sections']);

  return $options;
}

