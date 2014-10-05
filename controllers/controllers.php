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

function generate_login_token() {
  return JWT::encode(array(
    'user_id' => $_SESSION['user_id'],
    'me' => $_SESSION['me'],
    'created_at' => time()
  ), Config::$jwtSecret);
}

$app->get('/new', function() use($app) {
  if($user=require_login($app)) {

    $entry = false;
    $photo_url = false;

    $html = render('new-post', array(
      'title' => 'New Post',
      'micropub_endpoint' => $user->micropub_endpoint,
      'token_scope' => $user->token_scope,
      'access_token' => $user->access_token,
      'response_date' => $user->last_micropub_response_date,
      'location_enabled' => $user->location_enabled
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

  if(array_key_exists('token', $params) && !session('add-to-home-started')) {

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

    print_r($params);

    // Store the post in the database
    $entry = ORM::for_table('entries')->create();
    $entry->user_id = $user->id;
    $entry->published = date('Y-m-d H:i:s');

    if(k($params, 'location') && $location=parse_geo_uri($params['location'])) {
      $entry->latitude = $location['latitude'];
      $entry->longitude = $location['longitude'];
      if($timezone=get_timezone($location['latitude'], $location['longitude'])) {
        $entry->timezone = $timezone->getName();
        $entry->tz_offset = $timezone->getOffset(new DateTime());
      }
    } else {
      $entry->timezone = 'UTC';
      $entry->tz_offset = 0;
    }

    if(k($params, 'drank')) {
      $entry->content = $params['drank'];
    } elseif(k($params, 'custom_caffeine')) {
      $entry->content = $params['custom_caffeine'];
    } elseif(k($params, 'custom_alcohol')) {
      $entry->content = $params['custom_alcohol'];
    }

    $entry->save();

    // Send to the micropub endpoint if one is defined, and store the result

    if($user->micropub_endpoint) {
      $mp_request = array(
        'h' => 'entry',
        'content' => $entry->content,
        'location' => k($params, 'location')
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
      }

      $entry->save();
    } else {
      $url = Config::$base_url . $user->url . '/' . $entry->id;
    }

    $app->redirect($url);
  }
});

