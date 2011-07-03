<?php
  require_once 'everbox/EverboxClient.php';
  require_once 'everbox/SNDAOAuthHTTPClient.php';

  $action = $_GET['action'];
  $action();

function request_token(){
  $config = include 'everbox/apipool.config.php';
  $http = new SNDAOAuthHTTPClient($config);
  $oauth_url = $http->getAuthorizeURL();
  
  $url = $oauth_url.'&state='.EverboxClient::urlsafeBase64Encode($_GET['callback']);

  Header("HTTP/1.1 303 redirect to snda oauth");
  Header("Location: $url");
  exit;
}

function get_file_chunks_url(){
  $path = EverboxClient::urlsafeBase64Decode($_GET['path']);
  $keys = $_POST['keys'];
  $file_size = intval($_GET['file_size']);
  $chunk_size = intval($_GET['chunk_size']);
  
  $client = get_client();
  try{
  //$client->put('/var/www/blog/wp-content/backups/.backup.2011-07-03-02-32-27.zip','/home/a.zip');
  //exit;
 // echo 'path'.gettype($path).'<br>';
  //echo 'keys'.gettype($keys).'<br>';
 // echo 'filesize'.gettype($file_size).'<br>';
  //echo 'chunksize'.gettype($chunk_size).'<br>';
 // $result = $client->puttoo('/var/www/blog/wp-content/backups/.backup.2011-07-03-02-32-27.zip',$path,$file_size,$chunk_size,$keys);
  //$path = '/home/a.zip';
  //$keys = $client->_calcKeys($file);
  //$stat = fstat($file);
  //$file_size = $stat['size'];
  $result =  $client->_preparePut($path, $keys, $file_size,null,$chunk_size);
  echo json_encode($result['required']);
  } catch (Exception $e){
    echo 'code:'.$e->getCode().'<br>';
    echo 'message:'.$e->getMessage().'<br>';
    echo '<br>';
  }

  //echo 'keys:'.var_dump($_POST['keys']).'<br>';
  //echo 'path:'.EverboxClient::urlsafeBase64Decode($_GET['path']).'<br>';
  //echo 'filesize:'.intval($_GET['file_size']).'<br>';
  //echo 'chunksize:'.intval($_GET['chunk_size']).'<br>';
  //echo 'sdid:'.$_GET['sdid'].'<br>';
  //echo 'oauth_token:'.$_GET['access_token'].'<br>';

}

function get_client(){
  $config = include 'everbox/apipool.config.php';
  $http = new SNDAOAuthHTTPClient($config);
  $http->setAccessToken(get_token());
  return new SNDAEverboxClient($http, $config);
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
