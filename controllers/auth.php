<?php

function buildRedirectURI() {
  return Config::$base_url . 'auth/callback';
}

function clientID() {
  return Config::$base_url;
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


  render('index', array(
    'title' => 'Teacup',
    'meta' => ''
  ));
});

$app->get('/auth/start', function() use($app) {
  $req = $app->request();

  $params = $req->params();

  // the "me" parameter is user input, and may be in a couple of different forms:
  // aaronparecki.com http://aaronparecki.com http://aaronparecki.com/
  // Normlize the value now (move this into a function in IndieAuth\Client later)
  if(!array_key_exists('me', $params) || !($me = IndieAuth\Client::normalizeMeURL($params['me']))) {
    render('auth_error', array(
      'title' => 'Sign In',
      'error' => 'Invalid "me" Parameter',
      'errorDescription' => 'The URL you entered, "<strong>' . $params['me'] . '</strong>" is not valid.'
    ));
    return;
  }

  $_SESSION['attempted_me'] = $me;

  $authorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);
  $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);
  $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
  $hCard = IndieAuth\Client::representativeHCard($me);

  // Generate a "state" parameter for the request
  $state = IndieAuth\Client::generateStateParameter();
  $_SESSION['auth_state'] = $state;

  $_SESSION['redirect_after_login'] = '/new';

  if($tokenEndpoint && $micropubEndpoint && $authorizationEndpoint) {
    $scope = 'create media';
    $authorizationURL = IndieAuth\Client::buildAuthorizationURL($authorizationEndpoint, [
      'me' => $me,
      'redirect_uri' => buildRedirectURI(),
      'client_id' => clientID(),
      'state' => $state,
      'scope' => $scope
    ]);
    $_SESSION['authorization_endpoint'] = $authorizationEndpoint;
    $_SESSION['micropub_endpoint'] = $micropubEndpoint;
    $_SESSION['token_endpoint'] = $tokenEndpoint;
  } else {
    $authorizationURL = IndieAuth\Client::buildAuthorizationURL('https://indielogin.com/auth', [
      'me' => $me,
      'redirect_uri' => buildRedirectURI(),
      'client_id' => clientID(),
      'state' => $state
    ]);
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

    render('auth_start', array(
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
  }
});

$app->get('/auth/callback', function() use($app) {
  $req = $app->request();
  $params = $req->params();

  // If there is no state in the session, start the login again
  if(!array_key_exists('auth_state', $_SESSION)) {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing session state',
      'errorDescription' => 'Something went wrong, please try signing in again, and make sure cookies are enabled for this domain.'
    ));
    return;
  }

  if(!array_key_exists('code', $params) || trim($params['code']) == '') {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing authorization code',
      'errorDescription' => 'No authorization code was provided in the request.'
    ));
    return;
  }

  // Verify the state came back and matches what we set in the session
  // Should only fail for malicious attempts, ok to show a not as nice error message
  if(!array_key_exists('state', $params)) {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Missing state parameter',
      'errorDescription' => 'No state parameter was provided in the request. This shouldn\'t happen. It is possible this is a malicious authorization attempt, or your authorization server failed to pass back the "state" parameter.'
    ));
    return;
  }

  if($params['state'] != $_SESSION['auth_state']) {
    render('auth_error', array(
      'title' => 'Auth Callback',
      'error' => 'Invalid state',
      'errorDescription' => 'The state parameter provided did not match the state provided at the start of authorization. This is most likely caused by a malicious authorization attempt.'
    ));
    return;
  }

  if(!isset($_SESSION['attempted_me'])) {
    render('auth_error', [
      'title' => 'Auth Callback',
      'error' => 'Missing data',
      'errorDescription' => 'We forgot who was logging in. It\'s possible you took too long to finish signing in, or something got mixed up by signing in in another tab.'
    ]);
    return;
  }
  $me = $_SESSION['attempted_me'];

  // Now the basic checks have passed. Time to start providing more helpful messages when there is an error.
  // An authorization code is in the query string, and we want to exchange that for an access token at the token endpoint.

  $authorizationEndpoint = isset($_SESSION['authorization_endpoint']) ? $_SESSION['authorization_endpoint'] : false;
  $tokenEndpoint = isset($_SESSION['token_endpoint']) ? $_SESSION['token_endpoint'] : false;
  $micropubEndpoint = isset($_SESSION['micropub_endpoint']) ? $_SESSION['micropub_endpoint'] : false;

  unset($_SESSION['authorization_endpoint']);
  unset($_SESSION['token_endpoint']);
  unset($_SESSION['micropub_endpoint']);

  $skipDebugScreen = false;

  if($tokenEndpoint) {
    // Exchange auth code for an access token
    $token = IndieAuth\Client::exchangeAuthorizationCode($tokenEndpoint, [
      'code' => $params['code'],
      'redirect_uri' => buildRedirectURI(),
      'client_id' => clientID()
    ]);

    // If a valid access token was returned, store the token info in the session and they are signed in
    if(k($token['response'], array('me','access_token','scope'))) {

      // Verify the authorization endpoint matches
      if($token['response']['me'] != $me) {
        $newAuthorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($token['response']['me']);
        if($newAuthorizationEndpoint != $authorizationEndpoint) {
          render('auth_error', [
            'title' => 'Error Signing In',
            'error' => 'Invalid authorization endpoint',
            'errorDescription' => 'The authorization endpoint for the returned profile URL did not match the authorization endpoint used to begin the login.'
          ]);
          return;
        }
      }

      $_SESSION['auth'] = $token['response'];
      $_SESSION['me'] = $me = $token['response']['me'];
    }

  } else {
    // No token endpoint was discovered, instead, verify the auth code at the auth server or with indieauth.com

    // Never show the intermediate login confirmation page if we just authenticated them instead of got authorization
    $skipDebugScreen = true;

    if(!$authorizationEndpoint) {
      $authorizationEndpoint = 'https://indielogin.com/auth';
    }

    $token = IndieAuth\Client::exchangeAuthorizationCode($authorizationEndpoint, [
      'code' => $params['code'],
      'redirect_uri' => buildRedirectURI(),
      'client_id' => clientID()
    ]);

    if(k($token['response'], 'me')) {
      $_SESSION['auth'] = $token['response'];
      $_SESSION['me'] = $me = $token['response']['me'];
    }
  }

  // Verify the login actually succeeded
  if(!k($token['response'], 'me')) {
    render('auth_error', array(
      'title' => 'Sign-In Failed',
      'error' => 'Unable to verify the sign-in attempt',
      'errorDescription' => ''
    ));
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
  $user->access_token = $token['response']['access_token'];
  $user->token_scope = $token['response']['scope'];
  $user->token_response = $token['raw_response'];
  $user->save();
  $_SESSION['user_id'] = $user->id();


  if($tokenEndpoint) {
    // Make a request to the micropub endpoint to discover the media endpoint if set.
    get_micropub_config($user);
  }

  unset($_SESSION['auth_state']);

  if($skipDebugScreen) {
    $app->redirect($_SESSION['redirect_after_login'], 301);
  } else {
    render('auth_callback', array(
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
  }
});

$app->get('/signout', function() use($app) {
  unset($_SESSION['auth']);
  unset($_SESSION['me']);
  unset($_SESSION['auth_state']);
  unset($_SESSION['user_id']);
  $app->redirect('/', 301);
});

