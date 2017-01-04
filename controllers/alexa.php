<?php
use \Firebase\JWT\JWT;

$app->get('/alexa', function() use($app) {
  render('alexa', array(
    'title' => 'Teacup for Alexa'
  ));
});

$app->get('/alexa/auth', function() use($app) {
  $req = $app->request();
  $params = $req->params();

  $required = ['client_id', 'response_type', 'state', 'redirect_uri'];
  $params_present = array_keys($params);

  // Validate Alexa OAuth parameters
  if(count(array_intersect($required, $params_present)) != count($required)) {
    render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Missing parameters',
      'errorDescription' => 'One or more required parameters were missing',
      'footer' => false
    ));
    return;
  }

  // Check that redirect URI is one that is allowed
  if(!in_array($params['redirect_uri'], Config::$alexaRedirectURIs)) {
    render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Invalid redirect URI',
      'errorDescription' => 'Alexa sent an invalid redirect URI',
      'footer' => false
    ));
    return;
  }

  if($params['client_id'] != Config::$alexaClientID) {
    render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Invalid Client ID',
      'errorDescription' => 'Alexa sent an invalid client ID',
      'footer' => false
    ));
    return;
  }

  // Pass through the OAuth parameters
  render('alexa-auth', [
    'title' => 'Teacup for Alexa',
    'client_id' => $params['client_id'],
    'response_type' => $params['response_type'],
    'state' => $params['state'],
    'redirect_uri' => $params['redirect_uri'],
    'footer' => false
  ]);
});

$app->post('/alexa/login', function() use($app) {
  $req = $app->request();
  $params = $req->params();

  $required = ['code', 'client_id', 'state', 'redirect_uri'];
  $params_present = array_keys($params);

  if(count(array_intersect($required, $params_present)) != count($required)) {
    render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Missing parameters',
      'errorDescription' => 'One or more required parameters were missing',
      'footer' => false
    ));
    return;
  }

  $user = ORM::for_table('users')
    ->where('device_code', $params['code'])
    ->where_gt('device_code_expires', date('Y-m-d H:i:s'))->find_one();

  if(!$user) {
    render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Invalid code',
      'errorDescription' => 'The code you entered is invalid or has expired',
      'footer' => false
    ));
    return;
  }

  $code = JWT::encode(array(
    'user_id' => $user->id,
    'iat' => time(),
    'exp' => time()+300,
    'client_id' => $params['client_id'],
    'state' => $params['state'],
    'redirect_uri' => $params['redirect_uri'],
  ), Config::$jwtSecret);

  $redirect = $params['redirect_uri'] . '?code=' . $code . '&state=' . $params['state'];

  $app->redirect($redirect, 302);
});

$app->post('/alexa/token', function() use($app) {
  $req = $app->request();
  $params = $req->params();
  // Alexa requests a token given a code generated above

  // Verify the client ID and secret
  if($params['client_id'] != Config::$alexaClientID 
    || $params['client_secret'] != Config::$alexaClientSecret) {
    $app->response->setStatus(400);
    $app->response()['Content-type'] = 'application/json';
    $app->response()->body(json_encode([
      'error' => 'forbidden',
      'error_description' => 'The client ID and secret do not match'
    ]));
    return;
  }

  if(array_key_exists('code', $params)) {
    $jwt = $params['code'];
  } elseif(array_key_exists('refresh_token', $params)) {
    $jwt = $params['refresh_token'];
  } else {
    $app->response->setStatus(400);
    $app->response()['Content-type'] = 'application/json';
    $app->response()->body(json_encode([
      'error' => 'bad_request',
      'error_description' => 'Must provide either an authorization code or refresh token'
    ]));
    return;
  }

  // Validate the JWT
  try {
    $user = JWT::decode($jwt, Config::$jwtSecret, ['HS256']);
  } catch(Exception $e) {
    $app->response->setStatus(400);
    $app->response()['Content-type'] = 'application/json';
    $app->response()->body(json_encode([
      'error' => 'unauthorized',
      'error_description' => 'The authorization code or refresh token was invalid'
    ]));
    return;
  }

  // Generate an access token and refresh token
  $access_token = JWT::encode([
    'user_id' => $user->user_id,
    'client_id' => $user->client_id,
    'iat' => time(),
  ], Config::$jwtSecret);
  $refresh_token = JWT::encode([
    'user_id' => $user->user_id,
    'client_id' => $user->client_id,
    'iat' => time(),
  ], Config::$jwtSecret);


  $app->response()['Content-type'] = 'application/json';
  $app->response()->body(json_encode([
    'access_token' => $access_token,
    'refresh_token' => $refresh_token
  ]));
});


$app->post('/alexa/endpoint', function() use($app) {

  $input = file_get_contents('php://input');
  $json = json_decode($input, 'input');

  $alexaRequest = \Alexa\Request\Request::fromData($json);

  if($alexaRequest instanceof Alexa\Request\IntentRequest) {

    # Verify the access token
    try {
      $data = JWT::decode($alexaRequest->user->accessToken, Config::$jwtSecret, ['HS256']);
    } catch(Exception $e) {
      $app->response->setStatus(401);
      $app->response()['Content-type'] = 'application/json';
      $app->response()->body(json_encode([
        'error' => 'unauthorized',
        'error_description' => 'The access token was invalid or has expired'
      ]));
      return;
    }

    $user = ORM::for_table('users')->find_one($data->user_id);

    if(!$user) {
      $app->response->setStatus(400);
      return;
    }

    $action = $alexaRequest->slots['Action'];
    $food = ucfirst($alexaRequest->slots['Food']);


    $entry = ORM::for_table('entries')->create();
    $entry->user_id = $user->id;
    $entry->type = ($action == 'drank' ? 'drink' : 'eat');
    $entry->content = $food;
    $entry->published = date('Y-m-d H:i:s');
    $entry->save();

    $text_content = 'Just ' . $action . ': ' . $food;

    if($user->micropub_endpoint) {
      $mp_request = array(
        'h' => 'entry',
        'published' => date('Y-m-d H:i:s'),
        'summary' => $text_content
      );
      if($user->enable_array_micropub) {
        $mp_request[$action] = [
          'type' => 'h-food',
          'properties' => [
            'name' => $food
          ]
        ];
      } else {
        $mp_request['p3k-food'] = $food;
        $mp_request['p3k-type'] = $entry->type;
      }

      $r = micropub_post($user->micropub_endpoint, $mp_request, $user->access_token);
      $request = $r['request'];
      $response = $r['response'];

      $entry->micropub_response = $response;
      if($response && preg_match('/Location: (.+)/', $response, $match)) {
        $url = $match[1];
        $entry->micropub_success = 1;
        $entry->canonical_url = $url;
      } else {
        $entry->micropub_success = 0;
        $url = Config::$base_url . $user->url . '/' . $entry->id;
      }
      $entry->save();
    }


    $response = new \Alexa\Response\Response;
    $response->respond('Got it!')
      ->withCard('You '.$action.': '.$food, $url)
      ->endSession(true);

    $app->response()['Content-type'] = 'application/json';
    $app->response()->body(json_encode($response->render()));
  }
});
