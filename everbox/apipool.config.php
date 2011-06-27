<?php

return array(
    //'gateway_url' => 'http://test.api.snda.com:81' ,
    'gateway_url' => 'http://api.snda.com' , 
    'authorize_url' => 'http://oauth.snda.com/oauth/authorize' , 
    'access_token_url' => 'http://oauth.snda.com/oauth/token' , 
    'url_pattern_anonymous' => null,

    'io_connect_timeout' => 5, //secs
    'io_timeout' => 60, //secs
    'api_timeout' => 5, //secs

    'app_key' => '4aca8b1b65dace1aa1d0c54de8d160dc',
    'app_secret' => '86d1ceb4e3f6ca4d7e5546d7bb57ce21',
    'redirect_uri' => 'http://127.0.0.1/cb/callback.php',
);

