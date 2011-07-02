<?php
  require_once(dirname(__FILE__).'/everbox/EverboxClient.php');
  require_once(dirname(__FILE__).'/everbox/SNDAOAuthHTTPClient.php');

  $action = $_GET['action'];
  $action();

function request_token(){
  $config = include dirname(__FILE__).'/everbox/apipool.config.php';
  $snda_oauth = new SNDAOAuthHTTPClient($config);
  $oauth_url = $snda_oauth->getAuthorizeURL();
  
  $url = $oauth_url.'&state='.base64_encode($_GET['callback']);

  Header("HTTP/1.1 303 redirect to snda oauth");
  Header("Location: $url");
  exit;
}

function get_file_chunks_url(){
  $token = get_token();
  $keys = $_POST['keys'];

  $config = include dirname(__FILE__).'/everbox/apipool.config.php';
  $snda_oauth = new SNDAOAuthHTTPClient($config);
  $snda_oauth->setAccessToken($token);
  $client = new SNDAEverboxClient($snda_oauth, $config);
}

function get_token() {
  $sdid =  $_GET['sdid'];
  $access_token = $_GET['access_token'];
  $expires_in = $_GET['expires_in'];
  $token_json = "{\"access_token\":\"$access_token\",\"expires_in\":$expires_in,\"sdid\":\"$sdid\"}";
  $token  = json_decode($token_json,true);
  return $token;
}
?>
