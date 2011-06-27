<?php
class SNDAEverboxClient extends EverboxClient {
    /**
     *
     * @param EverboxHTTPClient $httpClient
     */
    function __construct($httpClient, $conf) {
        $this->httpClient = $httpClient;
        $this->gatewayUrl = $conf['gateway_url'];
        $this->ioTimeout = $conf['io_timeout'];
        $this->ioConnectTimeout = $conf['io_connect_timeout'];
        $this->urlPatternAnonymous = null;
    }
    
    
    function commonApiCall($name, $params = array()) {
        $params['method'] = 'sdo.everbox.fs.'.$name;
        
        $params = $this->httpClient->addOAuthParams($params);
        //
        $url = $this->gatewayUrl;
        $body = http_build_query($params);
        $headers = array('Content-type'=>'application/x-www-form-urlencoded');
        $resp = $this->httpClient->request($url, 'POST', $body, $headers);
        $ret = array();
        if (!$resp) {
            throw new EverboxClientException(EverboxClient::STATUS_IO_ERROR, 'Request failed');
        }
        
        $resp_data = json_decode($resp['body'], true);
        
        if ($resp_data['return_code'] != 0) {
            $code = -50000000 - $resp_data['return_code'];
            throw new EverboxClientException($code, $resp_data['return_message']);
        }
        $data = $resp_data['data'];
        return $data;
    }
    
    function _putSmallFileDirect($fp, $path, $key, $fileSize, $editTime = null, $base = null) {
        $chunkSize = self::CHUNK_SIZE;
        $mimeType = null;
        
        $params = array(
            'path' => $path,
            'key' => empty($key) ? '' : strval($key),
            'fileSize' => $fileSize,
        );
        
        if (!is_null($editTime)) {
            $params['editTime'] = $editTime;
        }
        if (!is_null($base)) {
            $params['base'] = $base;
        }
        if (!is_null($chunkSize)) {
            $params['chunkSize'] = $chunkSize;
        }
        if (!is_null($mimeType)) {
            $params['mimeType'] = $mimeType;
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        $params['content'] = self::urlsafeBase64Encode($content);
        unset($content);
        $ret = $this->commonApiCall('put', $params);
        return $ret;
    }
    
    function supportPurge() {
        return false;
    }
    
    function getEverboxToken() {
        $ret = $this->commonApiCall('token');
        return $ret;
    }
    
    function anonymousLinkGet($linkid) {
        $this->httpClient->useSystemToken();
        $ret = $this->commonApiCall('anonymous_link_get', array('linkid'=>$linkid) );
        $this->httpClient->useAccessToken();
        return $ret;
    }
}

class SNDAOAuthHTTPClient extends EverboxHTTPClient
{
    /** @var OAuth */
    protected $OAuth;
    
    protected $appKey = '';
    protected $appSecret = '';
    protected $redirectURI = '';
    
    
    const AUTHORIZE_URL = 'http://oauth.snda.com/oauth/authorize';
    const ACCESS_TOKEN_URL = 'http://oauth.snda.com/oauth/token';
    const SYSTEM_TOKEN_URL = 'http://oauth.snda.com/oauth/token';
    
    protected $token = null;
    protected $sysToken = null;
    protected $accessToken = null;
    
    function __construct($conf) {
        $this->appKey = $conf['app_key'];
        $this->appSecret = $conf['app_secret'];
        $this->redirectURI = $conf['redirect_uri'];
        $this->useAccessToken();
        //$this->username = $conf['username'];
    }
    
    function getAuthorizeUrl() {
        return self::AUTHORIZE_URL."?response_type=code&client_id={$this->appKey}&redirect_uri={$this->redirectURI}";
    }
    
    function fetchAccessToken($code) {
        $clientSecret = $this->appSecret;
        $url = self::ACCESS_TOKEN_URL."?code={$code}&client_id={$this->appKey}&client_secret={$this->appSecret}&redirect_uri=".rawurlencode($this->redirectURI);
        
        $context = stream_context_create(array('http' => array (
            //'method'=> $method,
            //'content'=>$body,
            'ignore_errors'=>true,
            //'header'=>$headers,
            //'timeout'=>
        )));
        
        $response = file_get_contents($url, false, $context);
        $token = json_decode($response, true);
        if (!isset($token['access_token'])) {
            throw new EverboxClientException(EverboxClient::STATUS_FORBIDDEN, 'fetch access token failed');
        }
        $this->setAccessToken($token);
        return $token;
    }
    
    function fetchSystemToken() {
        $url = self::SYSTEM_TOKEN_URL."?grant_type=client_credentials&client_id={$this->appKey}&client_secret={$this->appSecret}";
        $context = stream_context_create(array('http' => array (
            //'method'=> $method,
            //'content'=>$body,
            'ignore_errors'=>true,
            //'header'=>$headers,
            //'timeout'=>
        )));
        
        $response = file_get_contents($url, false, $context);
        $token = json_decode($response, true);
        if (!isset($token['access_token'])) {
            throw new EverboxClientException(EverboxClient::STATUS_FORBIDDEN, 'fetch system token failed');
        }
        $this->setSystemToken($token);
        return $token;
        
    }
    
    function setAccessToken($token) {
        $this->accessToken = $token;
	$this->useAccessToken();
    }
    
    function setSystemToken($token) {
        $this->sysToken = $token;
    }
    
    function useAccessToken() {
        $this->token = $this->accessToken;
    }
    
    function useSystemToken() {
        $this->token = $this->sysToken;
    }
    
    function request($url, $method = 'GET', $body = '', $additional_headers = array()) {
        $headers = array();
        //$additional_headers['Content-type'] = 'application/x-www-form-urlencoded';
        foreach ($additional_headers as $key=>$value) {
            $headers[]= $key.': '.$value;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SNDA OAuth 2.0');;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        list($resp_header, $resp_body) = explode("\r\n\r\n", $resp, 2);
        
        curl_close($ch);
        
        preg_match('~^HTTP/1\.[01] +(\d{3})\s~', $resp_header, $match);
        $resp_code = $match[1];
        
        return array('body'=>$resp_body, 'code'=>$resp_code);
    }
    
    function addOAuthParams($params) {
        $auth_params = array(
            //'oauth_token' => 'null',
            'oauth_consumer_key' => $this->appKey,
            'oauth_nonce' => self::generateNonce(),
            'oauth_timestamp' => (string) time(),
            'oauth_version' => '2.0',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token' => $this->token['access_token'],
        );
        if (isset($this->token['sdid'])) {
            $auth_params['sdid'] = $this->token['sdid'];
        }
        $ret = array_merge($params, $auth_params);
        $sig = self::generateSig($ret);
        $ret['oauth_signature'] = $sig;
        return $ret;
    }
    
    public static function generateNonce ()
    {
        $mt = microtime();
        $rand = mt_rand();
        return md5($mt . $rand); // md5s look nicer than numbers 
    }
    
    public function generateSig($params)
    {
        $str = $this->flatenParams($params);
        $ret = bin2hex(hash_hmac('sha1', $str, $this->appSecret, true));
        //var_dump($params, $str, $ret);
        return $ret;
    }
    
    function flatenParams($params) {
        $str = '';
        $out = array();
        $gen_tmp = function ($params, $kp = '%s') use (&$gen_tmp, &$out) {
            foreach ($params as $k => $v) {
                if (! is_array($v)) {
                    $out[]= sprintf($kp, $k).'='.$v;
                } else {
                    $out[]= $gen_tmp($v, sprintf($kp, $k)."[%s]", $out);
                }
            }
        };
        $gen_tmp($params);
        sort($out, SORT_STRING);
        $ret = implode($out);
        return $ret;
    }
    
    function supportAnnonymous() {
        return false;
    }
    
}

