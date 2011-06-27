<?php

class EverboxOAuthHTTPClient extends EverboxHTTPClient
{
    /** @var OAuth */
    protected $OAuth;
    
    function getOAuth() {
        return $this->OAuth;
    }
    
    
    function __construct($url_pattern, $consumerKey, $consumerSecret, $username, $password) {
        $this->OAuth = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1,OAUTH_AUTH_TYPE_URI);
        //$this->OAuth->setTimeout(5000);
        $this->OAuth->enableDebug();
		$this->OAuth->disableSSLChecks();
        
        $url = sprintf($url_pattern, rawurlencode($username), rawurlencode($password));
        
        $tokens = $this->OAuth->getRequestToken($url);
		$this->OAuth->setToken($tokens['oauth_token'], $tokens['oauth_token_secret']);
    }
    
    
    function request($url, $method = 'GET', $body = '', $additional_headers = array()) {
        $this->OAuth->setAuthType(OAUTH_AUTH_TYPE_AUTHORIZATION);
        try {
            $result = $this->OAuth->fetch($url, $body, $method, $additional_headers);
        } catch (OAuthException $e) {
            return array(
                'body'=>$this->OAuth->getLastResponse(),
                'code'=>$e->getCode()
            );
        }
        if ($result) {
            $info = $this->OAuth->getLastResponseInfo();
            $resp = $this->OAuth->getLastResponse();
            return array(
                    'body'=>$resp,
                    'code'=>$info['http_code']
                );
        }
        return null;
    }
}

