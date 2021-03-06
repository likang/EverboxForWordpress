<?php
require_once 'util.php';

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

function confirm_dir(){
  $client = get_client();
echo $client->getEverboxToken();exit;
  $result = $client->mkdir('/home/'.$_GET['folder']);
  echo $result;
}

function commit_put(){
  $path = EverboxClient::urlsafeBase64Decode($_GET['path']);
  $keys = $_POST['keys'];
  $file_size = intval($_GET['file_size']);
  $chunk_size = intval($_GET['chunk_size']);
  
  $client = get_client();
  try{
    $result =  $client->_commitPut($path, $keys, $file_size,null,null,$chunk_size);
    //echo $result;
  } catch (Exception $e){
    echo 'code:'.$e->getCode().'<br>';
    echo 'message:'.$e->getMessage().'<br>';
    echo '<br>';
  }
}

function get_file_chunks_url(){
  $path = EverboxClient::urlsafeBase64Decode($_GET['path']);
  $keys = $_POST['keys'];
  $file_size = intval($_GET['file_size']);
  $chunk_size = intval($_GET['chunk_size']);
  
  $client = get_client();
  try{
    $result =  $client->_preparePut($path, $keys, $file_size,null,$chunk_size);
    $result = $result['required'];
    $data = "";
    foreach ($result as $item ) {
      $data .= $item['url']."\n";
    }
    echo $data;
  } catch (Exception $e){
    echo 'code:'.$e->getCode().'<br>';
    echo 'message:'.$e->getMessage().'<br>';
    echo '<br>';
  }
}

?>
