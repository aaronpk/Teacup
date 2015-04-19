<?php

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
  // TODO: if a token is provided, return the user's custom list

  $app->response()['Content-Type'] = 'application/json';
  $app->response()->body(json_encode(array(
    'sections' => array(
      array(
        'title' => 'Caffeine',
        'items' => array_map(function($e){ return array('title'=>$e, 'type'=>'drink'); }, caffeine_options())
      ),
      array(
        'title' => 'Alcohol',
        'items' => array_map(function($e){ return array('title'=>$e, 'type'=>'drink'); }, alcohol_options())
      )
    )
  )));
});

