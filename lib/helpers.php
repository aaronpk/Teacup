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
    curl_setopt($ch, CURLOPT_URL, 'https://atlas.p3k.io/api/timezone?latitude='.$lat.'&longitude='.$lng);
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

if(!function_exists('http_build_url')) {
  function http_build_url($parsed_url) { 
    $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : ''; 
    $host     = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
    $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
    $user     = isset($parsed_url['user']) ? $parsed_url['user'] : ''; 
    $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : ''; 
    $pass     = ($user || $pass) ? "$pass@" : ''; 
    $path     = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
    $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : ''; 
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : ''; 
    return "$scheme$user$pass$host$port$path$query$fragment"; 
  } 
}

function micropub_post($endpoint, $params, $access_token) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
    'Accept: application/json'
  ));
  curl_setopt($ch, CURLOPT_POST, true);
  $post = json_encode($params);
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

function micropub_media_post($endpoint, $access_token, $file) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token
  ));
  curl_setopt($ch, CURLOPT_POST, true);

  $post = [
    'file' => new CURLFile($file)
  ];

  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  $response = curl_exec($ch);
  $error = curl_error($ch);
  $sent_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);

  return array(
    'response' => $response,
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

function micropub_get($endpoint, $params, $access_token) {
  $url = parse_url($endpoint);
  if(!k($url, 'query')) {
    $url['query'] = http_build_query($params);
  } else {
    $url['query'] .= '&' . http_build_query($params);
  }
  $endpoint = http_build_url($url);

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $endpoint);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $access_token,
    'Accept: application/json',
  ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  $data = array();
  if($response) {
    $data = @json_decode($response, true);
  }
  $error = curl_error($ch);
  return array(
    'response' => $response,
    'data' => $data,
    'error' => $error,
    'curlinfo' => curl_getinfo($ch)
  );
}

function get_micropub_config(&$user) {
  $targets = array();

  $r = micropub_get($user->micropub_endpoint, ['q'=>'config'], $user->access_token);

  if($r['data'] && is_array($r['data']) && array_key_exists('media-endpoint', $r['data'])) {
    $user->micropub_media_endpoint = $r['data']['media-endpoint'];
    $user->save();
  }

  return array(
    'response' => $r
  );
}

function get_micropub_checkin(&$user) {
  $r = micropub_get($user->micropub_endpoint, ['q' => 'source', 'limit' => 1, 'post-type' => 'checkin'], $user->access_token);
  
  $url = null;
  $name = null;
  $lat = null;
  $lng = null;
  
  if($r['data'] && is_array($r['data'])) {
    if(isset($r['data']['items'][0]['properties']['checkin'][0]) &&
       isset($r['data']['items'][0]['properties']['checkin'][0]['properties']['name'][0])) {
      $checkin = $r['data']['items'][0]['properties']['checkin'][0];
      $url = $r['data']['items'][0]['properties']['url'][0];
      $name = $checkin['properties']['name'][0];
      $lat = $checkin['properties']['latitude'][0];
      $lng = $checkin['properties']['longitude'][0];
    }
  }
  
  return [
    'url' => $url,
    'name' => $name,
    'lat' => $lat,
    'lng' => $lng,
  ];
}

function build_static_map_url($latitude, $longitude, $height, $width, $zoom) {
  return '/map.png?marker[]=lat:' . $latitude . ';lng:' . $longitude . ';icon:small-green-cutout&width=' . $width . '&height=' . $height . '&zoom=' . $zoom;
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

function date_iso8601($date_string, $tz_offset) {
  $date = new DateTime($date_string);
  if($tz_offset > 0)
    $date->add(new DateInterval('PT'.$tz_offset.'S'));
  elseif($tz_offset < 0)
    $date->sub(new DateInterval('PT'.abs($tz_offset).'S'));
  $tz = tz_seconds_to_offset($tz_offset);
  return $date->format('Y-m-d\TH:i:s') . $tz;
}

function tz_seconds_to_offset($seconds) {
  return ($seconds < 0 ? '-' : '+') . sprintf('%02d:%02d', abs($seconds/60/60), ($seconds/60)%60);
}

function tz_offset_to_seconds($offset) {
  if(preg_match('/([+-])(\d{2}):?(\d{2})/', $offset, $match)) {
    $sign = ($match[1] == '-' ? -1 : 1);
    return (($match[2] * 60 * 60) + ($match[3] * 60)) * $sign;
  } else {
    return 0;
  }
}

function entry_url($entry, $user) {
  return $entry->canonical_url ?: Config::$base_url . $user->url . '/' . $entry->id;
}

function entry_date($entry, $user) {
  $date = new DateTime($entry->published);
  if($entry->tz_offset > 0)
    $date->add(new DateInterval('PT'.$entry->tz_offset.'S'));
  elseif($entry->tz_offset < 0)
    $date->sub(new DateInterval('PT'.abs($entry->tz_offset).'S'));
  $tz = tz_seconds_to_offset($entry->tz_offset);
  return new DateTime($date->format('Y-m-d\TH:i:s') . $tz);
  // Can switch back to this later if I prompt the user for a named timezone instead of just an offset
  // $tz = new DateTimeZone($entry->timezone);
  // $date->setTimeZone($tz);
  // return $date;
}

function validate_photo(&$file) {
  try {

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($_POST) < 1 ) {
      throw new RuntimeException('File upload size exceeded.');
    }
   
    // Undefined | Multiple Files | $_FILES Corruption Attack
    // If this request falls under any of them, treat it invalid.
    if (
        !isset($file['error']) ||
        is_array($file['error'])
    ) {
        throw new RuntimeException('Invalid parameters.');
    }

    // Check $file['error'] value.
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    // You should also check filesize here.
    if ($file['size'] > 4000000) {
        throw new RuntimeException('Exceeded filesize limit.');
    }

    // DO NOT TRUST $file['mime'] VALUE !!
    // Check MIME Type by yourself.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (false === $ext = array_search(
        $finfo->file($file['tmp_name']),
        array(
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ),
        true
    )) {
        throw new RuntimeException('Invalid file format.');
    }

  } catch (RuntimeException $e) {

      return $e->getMessage();
  }
}

// Reads the exif rotation data and actually rotates the photo.
// Only does anything if the exif library is loaded, otherwise is a noop.
function correct_photo_rotation($filename) {
  if(class_exists('IMagick')) {
    $image = new IMagick($filename);
    $orientation = $image->getImageOrientation();
    switch($orientation) {
      case IMagick::ORIENTATION_BOTTOMRIGHT:
        $image->rotateImage(new ImagickPixel('#00000000'), 180);
        break;
      case IMagick::ORIENTATION_RIGHTTOP:
        $image->rotateImage(new ImagickPixel('#00000000'), 90);
        break;
      case IMagick::ORIENTATION_LEFTBOTTOM:
        $image->rotateImage(new ImagickPixel('#00000000'), -90);
        break;
    }
    $image->setImageOrientation(IMagick::ORIENTATION_TOPLEFT);
    $image->writeImage($filename);
  }
}

function default_drink_options() {
  return [
    ['title'=>'Coffee','subtitle'=>'','type'=>'drink'],
    ['title'=>'Beer','subtitle'=>'','type'=>'drink'],
    ['title'=>'Cocktail','subtitle'=>'','type'=>'drink'],
    ['title'=>'Tea','subtitle'=>'','type'=>'drink'],
    ['title'=>'Mimosa','subtitle'=>'','type'=>'drink'],
    ['title'=>'Latte','subtitle'=>'','type'=>'drink'],
    ['title'=>'Champagne','subtitle'=>'','type'=>'drink']
  ];
}

function default_food_options() {
  return [
    ['title'=>'Burrito','subtitle'=>'','type'=>'eat'],
    ['title'=>'Banana','subtitle'=>'','type'=>'eat'],
    ['title'=>'Pizza','subtitle'=>'','type'=>'eat'],
    ['title'=>'Soup','subtitle'=>'','type'=>'eat'],
    ['title'=>'Tacos','subtitle'=>'','type'=>'eat'],
    ['title'=>'Mac and Cheese','subtitle'=>'','type'=>'eat']
  ];
}

function query_user_nearby_options($type, $user_id, $latitude, $longitude) {
  $published = date('Y-m-d H:i:s', strtotime('-4 months'));
  $options = [];
  $bin_size = 1000;
  $optionsQ = ORM::for_table('entries')->raw_query('
    SELECT *, SUM(num) AS num FROM
    (
    SELECT id, published, content, type,
      round(gc_distance(latitude, longitude, :latitude, :longitude) / '.$bin_size.') AS dist, 
      COUNT(1) AS num
    FROM entries
    WHERE user_id = :user_id
      AND type = :type
      AND gc_distance(latitude, longitude, :latitude, :longitude) IS NOT NULL
      AND published > :published /* only look at the last 4 months of posts */
    GROUP BY content, round(gc_distance(latitude, longitude, :latitude, :longitude) / '.$bin_size.') /* group by 1km buckets */
    ORDER BY round(gc_distance(latitude, longitude, :latitude, :longitude) / '.$bin_size.'), COUNT(1) DESC /* order by distance and frequency */
    ) AS tmp
    WHERE num >= 1 /* only include things that have been used more than 2 times in this bucket */
      AND dist < 1000
    GROUP BY content /* group by name again */
    ORDER BY SUM(num) DESC /* order by overall frequency */ 
    LIMIT 6
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
    LIMIT 6
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
    SELECT published, timezone, tz_offset
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
    if($lastQ->timezone) {
      $date = new DateTime($lastQ->published);
      $date->setTimeZone(new DateTimeZone($lastQ->timezone));
    } else {
      $iso = date_iso8601($lastQ->published, $lastQ->tz_offset);
      $date = new DateTime($iso);
    }
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
    * Recent posts (food + drink combined)
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
    GROUP BY content, type
    ORDER BY MAX(published) DESC
    LIMIT 6', ['user_id'=>$user_id])->find_many();
  $last_latitude = false;
  $last_longitude = false;
  foreach($recentQ as $r) {
    if($last_latitude == false && $r->latitude) {
      $last_latitude = $r->latitude;
      $last_longitude = $r->longitude;
    }
    $recent[] = [
      'title' => $r->content,
      'subtitle' => query_last_eaten($user_id, $r->type, $r->content),
      'type' => $r->type
    ];
  }

  // If no location was provided, but there is a location in the most recent entry, use that
  if($latitude == null && $last_latitude) {
    $latitude = $last_latitude;
    $longitude = $last_longitude;
  }

  $num_options = 6;

  if($latitude) {
    $drinks = query_user_nearby_options('drink', $user_id, $latitude, $longitude);
  }
  // If there's no nearby data (like if the user isn't including location) then return the most frequently used ones instead
  if(count($drinks) < $num_options) {
    $frequent_drinks = query_user_frequent_options('drink', $user_id);
    $drinks = array_merge($drinks, $frequent_drinks);
  }
  // If there's less than 4 options available, fill the list with the default options
  if(count($drinks) < $num_options) {
    $default_drinks = default_drink_options();
    $drinks = array_merge($drinks, $default_drinks);
  }
  $drinks = array_slice($drinks, 0, $num_options);

  if($latitude) {
    $food = query_user_nearby_options('eat', $user_id, $latitude, $longitude);
  }
  if(count($food) == 0) {
    $frequent_food = query_user_frequent_options('eat', $user_id);
    $food = array_merge($food, $frequent_food);
  }
  if(count($food) < $num_options) {
    $default_food = default_food_options();
    $food = array_merge($food, $default_food);
  }
  $food = array_slice($food, 0, $num_options);


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

