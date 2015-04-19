<?php

function buildRedirectURI() {
  return Config::$base_url . 'auth/callback';
}

function clientID($pebble=false) {
  if($pebble) {
    return Config::$base_url . 'pebble';
  } else {
    return trim(Config::$base_url, '/'); // remove trailing slash from client_id
  }
}

function build_url($parsed_url) { 
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

// Input: Any URL or string like "aaronparecki.com"
// Output: Normlized URL (default to http if no scheme, force "/" path)
//         or return false if not a valid URL (has query string params, etc)
function normalizeMeURL($url) {
  $me = parse_url($url);

  if(array_key_exists('path', $me) && $me['path'] == '')
    return false;

  // parse_url returns just "path" for naked domains
  if(count($me) == 1 && array_key_exists('path', $me)) {
    $me['host'] = $me['path'];
    unset($me['path']);
  }

  if(!array_key_exists('scheme', $me))
    $me['scheme'] = 'http';

  if(!array_key_exists('path', $me))
    $me['path'] = '/';

  // Invalid scheme
  if(!in_array($me['scheme'], array('http','https')))
    return false;

  // Invalid path
  // if($me['path'] != '/')
  //   return false;

  // query and fragment not allowed
  if(array_key_exists('query', $me) || array_key_exists('fragment', $me))
    return false;

  return build_url($me);
}

function hostname($url) {
  return parse_url($url, PHP_URL_HOST);
}

function add_hcard_info($user, $hCard) {
  if($user && $hCard) {
    // Update the user's h-card info if present
    if(BarnabyWalters\Mf2\hasProp($hCard, 'name'))
      $user->name = BarnabyWalters\Mf2\getPlaintext($hCard, 'name');
    if(BarnabyWalters\Mf2\hasProp($hCard, 'photo'))
      $user->photo_url = BarnabyWalters\Mf2\getPlaintext($hCard, 'photo');
  }  
}

$app->get('/', function($format='html') use($app) {
  $res = $app->response();


  ob_start();
  render('index', array(
    'title' => 'Teacup',
    'meta' => ''
  ));
  $html = ob_get_clean();
  $res->body($html);
});

$app->get('/auth/start', function() use($app) {
  $req = $app->request();

  $params = $req->params();
  
  // the "me" parameter is user input, and may be in a couple of different forms:
  // aaronparecki.com http://aaronparecki.com http://aaronparecki.com/
  // Normlize the value now (move this into a function in IndieAuth\Client later)
  if(!array_key_exists('me', $params) || !($me = normalizeMeURL($params['me']))) {
    $html = render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Invalid "me" Parameter',
      'errorDescription' => 'The URL you entered, "<strong>' . $params['me'] . '</strong>" is not valid.'
    ));
    $app->response()->body($html);
    return;
  }

  $authorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);
  $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);
  $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
  $hCard = IndieAuth\Client::representativeHCard($me);

  // Generate a "state" parameter for the request
  $state = IndieAuth\Client::generateStateParameter();
  $_SESSION['auth_state'] = $state;
  
  // Store whether to return to the Pebble settings tab or the new post page after signing in
  if(array_key_exists('redirect', $params) && $params['redirect'] == 'settings') {
    $_SESSION['redirect_after_login'] = '/pebble/settings/finished';
  } else {
    $_SESSION['redirect_after_login'] = '/new';
  }

  $pebble = k($params, 'pebble');
  $_SESSION['pebble'] = $pebble;

  if($tokenEndpoint && $micropubEndpoint && $authorizationEndpoint) {
    $scope = 'post';
    $authorizationURL = IndieAuth\Client::buildAuthorizationURL($authorizationEndpoint, $me, buildRedirectURI(), clientID($pebble), $state, $scope);
  } else {
    $authorizationURL = IndieAuth\Client::buildAuthorizationURL('https://indieauth.com/auth', $me, buildRedirectURI(), clientID($pebble), $state);
  }

  // If the user has already signed in before and has a micropub access token, skip 
  // the debugging screens and redirect immediately to the auth endpoint.
  // This will still generate a new access token when they finish logging in.
  $user = ORM::for_table('users')->where('url', hostname($me))->find_one();

  if($user && $user->access_token && !array_key_exists('restart', $params)) {

    add_hcard_info($user, $hCard);
    $user->micropub_endpoint = $micropubEndpoint;
    $user->authorization_endpoint = $authorizationEndpoint;
    $user->token_endpoint = $tokenEndpoint;
    $user->type = $micropubEndpoint ? 'micropub' : 'local';
    $user->save();

    $app->redirect($authorizationURL, 301);

  } else {

    if(!$user)
      $user = ORM::for_table('users')->create();
    add_hcard_info($user, $hCard);
    $user->url = hostname($me);
    $user->date_created = date('Y-m-d H:i:s');
    $user->micropub_endpoint = $micropubEndpoint;
    $user->authorization_endpoint = $authorizationEndpoint;
    $user->token_endpoint = $tokenEndpoint;
    $user->type = $micropubEndpoint ? 'micropub' : 'local';
    $user->save();

    $html = render('auth_start', array(
      'title' => 'Sign In',
      'me' => $me,
      'authorizing' => $me,
      'meParts' => parse_url($me),
      'micropubUser' => $authorizationEndpoint && $tokenEndpoint && $micropubEndpoint,
      'tokenEndpoint' => $tokenEndpoint,
      'micropubEndpoint' => $micropubEndpoint,
      'authorizationEndpoint' => $authorizationEndpoint,
      'authorizationURL' => $authorizationURL
    ));
    $app->response()->body($html);
  }
});

$app->get('/auth/callback', function() use($app) {
  $req = $app->request();
  $params = $req->params();

  // Double check there is a "me" parameter
  // Should only fail for really hacked up requests
  if(!array_key_exists('me', $params) || !($me = normalizeMeURL($params['me']))) {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Invalid "me" Parameter',
      'errorDescription' => 'The ID you entered, <strong>' . $params['me'] . '</strong> is not valid.'
    ));
    $app->response()->body($html);
    return;
  }

  // If there is no state in the session, start the login again
  if(!array_key_exists('auth_state', $_SESSION)) {
    $app->redirect('/auth/start?me='.urlencode($params['me']));
    return;
  }

  if(!array_key_exists('code', $params) || trim($params['code']) == '') {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing authorization code',
      'errorDescription' => 'No authorization code was provided in the request.'
    ));
    $app->response()->body($html);
    return;
  }

  // Verify the state came back and matches what we set in the session
  // Should only fail for malicious attempts, ok to show a not as nice error message
  if(!array_key_exists('state', $params)) {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing state parameter',
      'errorDescription' => 'No state parameter was provided in the request. This shouldn\'t happen. It is possible this is a malicious authorization attempt.'
    ));
    $app->response()->body($html);
    return;
  }

  if($params['state'] != $_SESSION['auth_state']) {
    $html = render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Invalid state',
      'errorDescription' => 'The state parameter provided did not match the state provided at the start of authorization. This is most likely caused by a malicious authorization attempt.'
    ));
    $app->response()->body($html);
    return;
  }

  $pebble = k($_SESSION, 'pebble');

  // Now the basic sanity checks have passed. Time to start providing more helpful messages when there is an error.
  // An authorization code is in the query string, and we want to exchange that for an access token at the token endpoint.

  // Discover the endpoints
  $authorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);
  $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
  $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);

  $skipDebugScreen = false;

  if($tokenEndpoint) {
    // Exchange auth code for an access token
    $token = IndieAuth\Client::getAccessToken($tokenEndpoint, $params['code'], $params['me'], buildRedirectURI(), clientID($pebble), $params['state'], true);

    // If a valid access token was returned, store the token info in the session and they are signed in
    if(k($token['auth'], array('me','access_token','scope'))) {
      $_SESSION['auth'] = $token['auth'];
      $_SESSION['me'] = $params['me'];

      // TODO?
      // This client requires the "post" scope.


      // Make a request to the micropub endpoint to discover the syndication targets if any.
      // Errors are silently ignored here. The user will be able to retry from the new post interface and get feedback.
      // get_syndication_targets($user);
    }

  } else {
    // No token endpoint was discovered, instead, verify the auth code at the auth server or with indieauth.com

    // Never show the intermediate login confirmation page if we just authenticated them instead of got authorization
    $skipDebugScreen = true;

    if(!$authorizationEndpoint) {
      $authorizationEndpoint = 'https://indieauth.com/auth';
    }

    $token['auth'] = IndieAuth\Client::verifyIndieAuthCode($authorizationEndpoint, $params['code'], $params['me'], buildRedirectURI(), clientID($pebble), $params['state']);

    if(k($token['auth'], 'me')) {
      $token['response'] = ''; // hack becuase the verify call doesn't actually return the real response
      $token['auth']['scope'] = '';
      $token['auth']['access_token'] = '';
      $_SESSION['auth'] = $token['auth'];
      $_SESSION['me'] = $params['me'];
    }
  }


  // Verify the login actually succeeded
  if(!array_key_exists('me', $_SESSION)) {
    $html = render('auth_error', array(
      'title' => 'Sign-In Failed',
      'error' => 'Unable to verify the sign-in attempt',
      'errorDescription' => ''
    ));
    $app->response()->body($html);
    return;
  }


  $user = ORM::for_table('users')->where('url', hostname($me))->find_one();
  if($user) {
    // Already logged in, update the last login date
    $user->last_login = date('Y-m-d H:i:s');
    // If they have logged in before and we already have an access token, then redirect to the dashboard now
    if($user->access_token)
      $skipDebugScreen = true;
  } else {
    // New user! Store the user in the database
    $user = ORM::for_table('users')->create();
    $user->url = hostname($me);
    $user->date_created = date('Y-m-d H:i:s');
    $user->last_login = date('Y-m-d H:i:s');
  }
  $user->micropub_endpoint = $micropubEndpoint;
  $user->access_token = $token['auth']['access_token'];
  $user->token_scope = $token['auth']['scope'];
  $user->token_response = $token['response'];
  $user->save();
  $_SESSION['user_id'] = $user->id();



  unset($_SESSION['auth_state']);

  if($skipDebugScreen) {
    $app->redirect($_SESSION['redirect_after_login'], 301);
  } else {
    $html = render('auth_callback', array(
      'title' => 'Sign In',
      'me' => $me,
      'authorizing' => $me,
      'meParts' => parse_url($me),
      'tokenEndpoint' => $tokenEndpoint,
      'auth' => $token['auth'],
      'response' => $token['response'],
      'curl_error' => (array_key_exists('error', $token) ? $token['error'] : false),
      'redirect' => $_SESSION['redirect_after_login']
    ));
    $app->response()->body($html);
  }
});

$app->get('/signout', function() use($app) {
  unset($_SESSION['auth']);
  unset($_SESSION['me']);
  unset($_SESSION['auth_state']);
  unset($_SESSION['user_id']);
  $app->redirect('/', 301);
});

