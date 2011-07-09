<?php

require_once 'everbox/EverboxClient.php';
require_once 'everbox/SNDAOAuthHTTPClient.php';

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

/**
 * Return human readable sizes
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.3.0
 * @link        http://aidanlister.com/2004/04/human-readable-file-sizes/
 * @param       int     $size        size in bytes
 * @param       string  $max         maximum unit
 * @param       string  $system      'si' for SI, 'bi' for binary prefixes
 * @param       string  $retstring   return string format
 */
function size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f %s')
{
    // Pick units
    $systems['si']['prefix'] = array('B', 'K', 'MB', 'GB', 'TB', 'PB');
    $systems['si']['size']   = 1000;
    $systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
    $systems['bi']['size']   = 1024;
    $sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];
 
    // Max unit to display
    $depth = count($sys['prefix']) - 1;
    if ($max && false !== $d = array_search($max, $sys['prefix'])) {
        $depth = $d;
    }
 
    // Loop
    $i = 0;
    while ($size >= $sys['size'] && $i < $depth) {
        $size /= $sys['size'];
        $i++;
    }
 
    return sprintf($retstring, $size, $sys['prefix'][$i]);
}

?>
