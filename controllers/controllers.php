<?php

function require_login(&$app) {
  $params = $app->request()->params();
  if(array_key_exists('token', $params)) {
    try {
      $data = JWT::decode($params['token'], Config::$jwtSecret);
      $_SESSION['user_id'] = $data->user_id;
      $_SESSION['me'] = $data->me;
    } catch(DomainException $e) {
      header('X-Error: DomainException');
      $app->redirect('/', 301);
    } catch(UnexpectedValueException $e) {
      header('X-Error: UnexpectedValueException');
      $app->redirect('/', 301);
    }
  }

  if(!array_key_exists('user_id', $_SESSION)) {
    $app->redirect('/');
    return false;
  } else {
    return ORM::for_table('users')->find_one($_SESSION['user_id']);
  }
}

function get_login(&$app) {
  if(array_key_exists('user_id', $_SESSION)) {
    return ORM::for_table('users')->find_one($_SESSION['user_id']);
  } else {
    return false;
  }
}

function generate_login_token() {
  return JWT::encode(array(
    'user_id' => $_SESSION['user_id'],
    'me' => $_SESSION['me'],
    'created_at' => time()
  ), Config::$jwtSecret);
}

$app->get('/new', function() use($app) {
  if($user=require_login($app)) {

    // Get the last post and set the timezone offset to match
    $date_str = date('Y-m-d');
    $time_str = date('H:i:s');
    $tz_offset = '+0000';

    $last = ORM::for_table('entries')->where('user_id', $user->id)
      ->order_by_desc('published')->find_one();
    if(false && $last) {
      $seconds = $last->tz_offset;
      $tz_offset = tz_seconds_to_offset($seconds);

      // Create a date object in the local timezone given the offset
      $date = new DateTime();
      if($seconds > 0)
        $date->add(new DateInterval('PT'.$seconds.'S'));
      elseif($seconds < 0)
        $date->sub(new DateInterval('PT'.abs($seconds).'S'));
      $date_str = $date->format('Y-m-d');
      $time_str = $date->format('H:i:s');
    }

    // Initially populate the page with the list of options without considering location.
    // This way if browser location is disabled or not available, or JS is disabled, there
    // will still be a list of options presented on the page by the time it loads.
    // Javascript will replace the options after location is available.

    $html = render('new-post', array(
      'title' => 'New Post',
      'micropub_endpoint' => $user->micropub_endpoint,
      'token_scope' => $user->token_scope,
      'access_token' => $user->access_token,
      'response_date' => $user->last_micropub_response_date,
      'location_enabled' => $user->location_enabled,
      'default_options' => get_entry_options($user->id),
      'tz_offset' => $tz_offset,
      'date_str' => $date_str,
      'time_str' => $time_str,
      'enable_appcache' => true
    ));
    $app->response()->body($html);
  }
});

$app->post('/prefs', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();
    $user->location_enabled = $params['enabled'];
    $user->save();
  }
  $app->response()->body(json_encode(array(
    'result' => 'ok'
  )));
});

$app->get('/creating-a-token-endpoint', function() use($app) {
  $app->redirect('http://indiewebcamp.com/token-endpoint', 301);
});
$app->get('/creating-a-micropub-endpoint', function() use($app) {
  $html = render('creating-a-micropub-endpoint', array('title' => 'Creating a Micropub Endpoint'));
  $app->response()->body($html);
});

$app->get('/docs', function() use($app) {
  $html = render('docs', array('title' => 'Documentation'));
  $app->response()->body($html);
});

$app->get('/add-to-home', function() use($app) {
  $params = $app->request()->params();
  header("Cache-Control: no-cache, must-revalidate");

  if(array_key_exists('token', $params) && !session('add-to-home-started')) {
    unset($_SESSION['add-to-home-started']);

    // Verify the token and sign the user in
    try {
      $data = JWT::decode($params['token'], Config::$jwtSecret);
      $_SESSION['user_id'] = $data->user_id;
      $_SESSION['me'] = $data->me;
      $app->redirect('/new', 301);
    } catch(DomainException $e) {
      header('X-Error: DomainException');
      $app->redirect('/', 301);
    } catch(UnexpectedValueException $e) {
      header('X-Error: UnexpectedValueException');
      $app->redirect('/', 301);
    }

  } else {

    if($user=require_login($app)) {
      if(array_key_exists('start', $params)) {
        $_SESSION['add-to-home-started'] = true;
        
        $token = JWT::encode(array(
          'user_id' => $_SESSION['user_id'],
          'me' => $_SESSION['me'],
          'created_at' => time()
        ), Config::$jwtSecret);

        $app->redirect('/add-to-home?token='.$token, 301);
      } else {
        unset($_SESSION['add-to-home-started']);
        $html = render('add-to-home', array('title' => 'Teacup'));
        $app->response()->body($html);
      }
    }
  }
});


$app->post('/post', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    // Remove any blank params
    $params = array_filter($params, function($v){
      return $v !== '';
    });

    // Store the post in the database
    $entry = ORM::for_table('entries')->create();
    $entry->user_id = $user->id;

    $location = false;
    if(k($params, 'location') && $location=parse_geo_uri($params['location'])) {
      $entry->latitude = $location['latitude'];
      $entry->longitude = $location['longitude'];
    }

    // The post request is always going to have a date now
    $date_string = $params['note_date'] . 'T' . $params['note_time'] . $params['note_tzoffset'];
    $entry->published = date('Y-m-d H:i:s', strtotime($date_string));
    $entry->tz_offset = tz_offset_to_seconds($params['note_tzoffset']);

    $published = $date_string;

    if(k($params, 'drank')) {
      $entry->content = trim($params['drank']);
      $type = 'drink';
      $verb = 'drank';
    } elseif(k($params, 'drink')) {
      $entry->content = trim($params['drink']);
      $type = 'drink';
      $verb = 'drank';
    } elseif(k($params, 'eat')) {
      $entry->content = trim($params['eat']);
      $type = 'eat';
      $verb = 'ate';
    } elseif(k($params, 'custom_drink')) {
      $entry->content = trim($params['custom_drink']);
      $type = 'drink';
      $verb = 'drank';
    } elseif(k($params, 'custom_eat')) {
      $entry->content = trim($params['custom_eat']);
      $type = 'eat';
      $verb = 'ate';
    }

    $entry->type = $type;

    $entry->save();

    // Send to the micropub endpoint if one is defined, and store the result
    $url = false;

    if($user->micropub_endpoint) {
      $text_content = 'Just ' . $verb . ': ' . $entry->content;

      $mp_request = array(
        'h' => 'entry',
        'published' => $published,
        'p3k-food' => $entry->content,
        'p3k-type' => $type,
        'location' => k($params, 'location'),
        'summary' => $text_content
      );

      $r = micropub_post($user->micropub_endpoint, $mp_request, $user->access_token);
      $request = $r['request'];
      $response = $r['response'];

      $entry->micropub_response = $response;

      // Check the response and look for a "Location" header containing the URL
      if($response && preg_match('/Location: (.+)/', $response, $match)) {
        $url = $match[1];
        $user->micropub_success = 1;
        $entry->micropub_success = 1;
        $entry->canonical_url = $url;
      } else {
        $entry->micropub_success = 0;
      }

      $entry->save();
    }

    if($url) {
      $app->redirect($url);
    } else {
      // TODO: Redirect to an error page or show an error on the teacup post page
      $url = Config::$base_url . $user->url . '/' . $entry->id;
      $app->redirect($url);
    }
  }
});

$app->get('/options.json', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    $options = get_entry_options($user->id, k($params,'latitude'), k($params,'longitude'));
    $html = partial('partials/entry-buttons', ['options'=>$options]);

    $tz_offset = '+0000';
    $date_str = date('Y-m-d');
    $time_str = date('H:i:s');
    if(k($params,'latitude')) {
      if($timezone=get_timezone($params['latitude'], $params['longitude'])) {
        $seconds = $timezone->getOffset(new DateTime());
        $tz_offset = tz_seconds_to_offset($seconds);

        // Create a date object in the local timezone given the offset
        $date = new DateTime();
        if($seconds > 0)
          $date->add(new DateInterval('PT'.$seconds.'S'));
        elseif($seconds < 0)
          $date->sub(new DateInterval('PT'.abs($seconds).'S'));
        $date_str = $date->format('Y-m-d');
        $time_str = $date->format('H:i:s');
      }
    }

    $app->response()['Content-type'] = 'application/json';
    $app->response()->body(json_encode([
      'buttons'=>$html, 
      'tz_offset'=>$tz_offset,
      'date_str'=>$date_str,
      'time_str'=>$time_str
    ]));
  }
});

$app->get('/map.png', function() use($app) {
  $url = static_map_service($_SERVER['QUERY_STRING']);
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $img = curl_exec($ch);
  header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+30 days')) . ' GMT');
  header('Pragma: cache');
  header('Cache-Control: private');
  $app->response()['Content-type'] = 'image/png';
  $app->response()->body($img);
});

$app->get('/-teacup.appcache', function() use($app) {
  $content = partial('appcache');

  $app->response()['Content-type'] = 'text/cache-manifest';
  $app->response()->body($content);
});

$app->get('/:domain', function($domain) use($app) {
  $params = $app->request()->params();

  $user = ORM::for_table('users')->where('url', $domain)->find_one();
  if(!$user) {
    $app->notFound();
    return;
  }

  $per_page = 10;

  $entries = ORM::for_table('entries')->where('user_id', $user->id);
  if(array_key_exists('before', $params)) {
    $entries->where_lte('id', $params['before']);
  }
  $entries = $entries->limit($per_page)->order_by_desc('published')->find_many();

  if(count($entries) > 1) {
    $older = ORM::for_table('entries')->where('user_id', $user->id)
      ->where_lt('id', $entries[count($entries)-1]->id)->order_by_desc('published')->find_one();
  } else {
    $older = null;
  }

  if(count($entries) > 1) {
    $newer = ORM::for_table('entries')->where('user_id', $user->id)
      ->where_gte('id', $entries[0]->id)->order_by_asc('published')->offset($per_page)->find_one();
  } else {
    $newer = null;
  }

  if(!$newer) {
    // no new entry was found at the specific offset, so find the newest post to link to instead
    $newer = ORM::for_table('entries')->where('user_id', $user->id)
      ->order_by_desc('published')->limit(1)->find_one();

    if($newer && $newer->id == $entries[0]->id)
      $newer = false;
  }

  $html = render('entries', array(
    'title' => 'Teacup',
    'entries' => $entries,
    'user' => $user,
    'older' => ($older ? $older->id : false),
    'newer' => ($newer ? $newer->id : false)
  ));
  $app->response()->body($html);
})->conditions(array(
  'domain' => '[a-zA-Z0-9\.-]+\.[a-z]+'
));


$app->get('/:domain/:entry', function($domain, $entry_id) use($app) {
  $user = ORM::for_table('users')->where('url', $domain)->find_one();
  if(!$user) {
    $app->notFound();
    return;
  }

  $entry = ORM::for_table('entries')->where('user_id', $user->id)->where('id', $entry_id)->find_one();
  if(!$entry) {
    $app->notFound();
    return;
  }

  $html = render('entry', array(
    'title' => 'Teacup',
    'entry' => $entry,
    'user' => $user
  ));
  $app->response()->body($html);
})->conditions(array(
  'domain' => '[a-zA-Z0-9\.-]+\.[a-z]+',
  'entry' => '\d+'
));


