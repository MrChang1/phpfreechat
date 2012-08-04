<?php

include_once 'container/users.php';

$app->get('/auth', function () use ($app, $req, $res) {
  
  // check if a user session already exists
  session_start();
  if (isset($_SESSION['userdata']) and isset($_SESSION['userdata']['id'])) {
    $res->status(200, 'User authenticated');
    $res['Content-Type'] = 'application/json; charset=utf-8';
    $res->body(json_encode($_SESSION['userdata']));
    return;
  } 

  // check if a login/password has been set
  // allow Pfc-Authorization header because Authorization can be disabled by reverse proxy
  $auth = $req->headers('Authorization') ?
    $req->headers('Authorization') :
    ($req->headers('Pfc-Authorization') ? $req->headers('Pfc-Authorization') : '');
  if (!$auth) {
    $res->status(403, 'Need authentication');
    $res['Content-Type'] = 'application/json; charset=utf-8';
    $res['Pfc-WWW-Authenticate'] = 'Basic realm="Authentication"';
    return;
  }

  // decode basic http auth header
  $auth = @explode(':', @base64_decode(@array_pop(@explode(' ', $auth))));
  if (!isset($auth[0]) && !$auth[0]) {
    $res->status(400, 'Login is missing');
    return;
  }
  $login    = trim($auth[0]);
  $password = isset($auth[1]) ? $auth[1] : '';
  
  // check login/password
  if ($login and Container_indexes::getIndex('users/name', $login)) {
    $res->status(403, 'Login already used');
    $res['Pfc-WWW-Authenticate'] = 'Basic realm="Authentication"';
    return;
  } else if ($login) {
    $uid = Container_users::generateUid();
    $udata = array(
      'id'       => $uid,
      'name'     => $login,
//      'email'    => (isset($req['params']['email']) and $req['params']['email']) ? $req['params']['email'] : (string)rand(1,10000),
      'role'     => 'user',
    );
    Container_users::setUserData($uid, $udata);
    $_SESSION['userdata'] = $udata;

    $res->status(200);
    $res['Content-Type'] = 'application/json; charset=utf-8';
    $res->body(json_encode($_SESSION['userdata']));
    return;
  } else {
    $res->status(403, 'Wrong credentials');
    $res['Pfc-WWW-Authenticate'] = 'Basic realm="Authentication"';
    return;
  }

});

$app->delete('/auth', function () use ($app, $req, $res) {

  // check if session exists
  session_start();
  if (!isset($_SESSION['userdata']) or !isset($_SESSION['userdata']['id'])) {
    $res->status(200, 'Already disconnected');
    return;
  }
  
  // store userdata in a cache in order to return it later
  $ud = $_SESSION['userdata'];

  // logout
  $_SESSION['userdata'] = array();
  session_destroy();

  // return ok and the user data
  $res->status(201);
  $res['Content-Type'] = 'application/json; charset=utf-8';
  $res->body(json_encode($ud));

});
