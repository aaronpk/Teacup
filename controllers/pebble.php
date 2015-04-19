<?php

$app->get('/pebble', function() use($app) {
  $html = render('pebble', array(
    'title' => 'Teacup for Pebble'
  ));
  $app->response()->body($html);
});

$app->get('/pebble/settings', function() use($app) {
  $html = render('pebble-settings-login', array(
    'title' => 'Log In',
    'footer' => false
  ));
  $app->response()->body($html);
});

$app->get('/pebble/settings/finished', function() use($app) {
  if($user=require_login($app)) {
    $token = JWT::encode(array(
      'user_id' => $_SESSION['user_id'],
      'me' => $_SESSION['me'],
      'created_at' => time()
    ), Config::$jwtSecret);
    
    $html = render('pebble-settings', array(
      'title' => 'Pebble Settings',
      'token' => $token
    ));
    $app->response()->body($html);
  }
});

$app->get('/pebble/options.json', function() use($app) {
  if($user=require_login($app)) {
    $params = $app->request()->params();

    $options = get_entry_options($user->id, k($params,'latitude'), k($params,'longitude'));

    $app->response()['Content-Type'] = 'application/json';
    $app->response()->body(json_encode($options));
  }
});

