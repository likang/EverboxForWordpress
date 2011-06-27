<?php

/**
 *
 *
 * Everbox 2.0 api client
 */
class EverboxClient
{
    /** @var EverboxHTTPClient */
    protected $httpClient;
    
    protected $urlPattern = '';
    protected $ioTimeout = 30;
    protected $ioConnectTimeout = 5;
    //protected $apiTimeout = 5;
    
    const CHUNK_SIZE = 4194304; // 4M
    const SMALL_FILE_SIZE = 524288; //512K
    const LARGE_FILE_SIZE = 134217728; // 128M
    const MAX_FILE_SIZE = 4294967296; // 4G
    
    const TYPE_FILE = 1;     
    const TYPE_DIR = 2;
    const TYPE_FILE_AND_DIR = 3;     
    const TYPE_LINK = 4;     
    const TYPE_DELETED = 0x8000;
    const TYPE_ALL = 0x8003;
    
    const STATUS_OK = 200;              // success
    const STATUS_PARTIAL_OK = 298;      // success(partially
    const STATUS_PARTIAL_CONTENT = 206;	// partial content
    const STATUS_NOOP = 299;            // noop
    const STATUS_NOT_MODIFIED = 304;    // not modified
    const STATUS_BAD_REQUEST = 400;
    const STATUS_OUTOFSPACE = 402;
    const STATUS_FORBIDDEN = 403;
    const STATUS_EOF = 404;
    const STATUS_CONFLICTED = 409;
    const STATUS_PRECONDITION_FAIL = 412;
    const STATUS_INVALIDARGS = 499;
    const STATUS_NOTIMPL = 501;
    const STATUS_INVALIDRET = -1;
    const STATUS_BUFFER_TOOSMALL = -3;
    const STATUS_IO_ERROR = -4;
    const STATUS_INPUT_ERROR = -8;
    
    const AIM_IMAGE = 0;
    const AIM_IMAGE_SMALL = 1;
    const AIM_AUDIO = 0x10000;
    
    /**
     *
     * @param EverboxHTTPClient $httpClient
     */
    function __construct($httpClient, $conf) {
        $this->httpClient = $httpClient;
        $this->urlPattern = $conf['url_pattern'];
        $this->ioTimeout = $conf['io_timeout'];
        $this->ioConnectTimeout = $conf['io_connect_timeout'];
        $this->urlPatternAnonymous = $conf['url_pattern_anonymous'];
    }
    
    /**
     *
     *
     * @param string $name
     * @param array $params
     * 
     * @throws EverboxClientException
     */
    function commonApiCall($name, $params = array()) {
        $additional_headers = array();
        $url = $this->getCommonApiUrl($name);
        $body = json_encode($params);
        $resp = $this->httpClient->request($url, 'POST', $body, array('Content-type'=>'text/plain'));
        $ret = array();
        if (!$resp) {
            throw new EverboxClientException(EverboxClient::STATUS_IO_ERROR, 'Request failed');
        }
        
        $info = json_decode($resp['body'], true);
        if ($resp['code'] != self::STATUS_OK) {
            throw new EverboxClientException($resp['code'], $info);
        }
        return $info;
    }
    
    function getCommonApiUrl($name) {
        $tpl = $this->urlPattern;
        $url = sprintf($tpl, $name);
        return $url;
    }
    
    /**
     * 列出目录的信息和成员
     * 
     * @param string $path 目标目录的路径
     * @param boolean $list 是否列出成员
     * @param int $showType 显示成员类型，为 self::TYPE_* 的组合
     * @param int $limit 最多列出的成员数
     * @param int $skip 跳过头 $skip 个成员
     * @param string $base 版本
     *
     * @throws EverboxClientException
     * @return array
     */
    function dir($path, $list = 1, $showType = self::TYPE_FILE_AND_DIR, $limit = 1000, $skip = 0, $base = null) {
        $param = array('path'=>$path, 'list'=>intval($list));
        if (!is_null($showType)) {
            $param['showType'] = intval($showType);
        }
        if (!is_null($limit)) {
            $param['limit'] = intval($limit);
        }
        if (!is_null($skip)) {
            $param['skip'] = intval($skip);
        }
        if (!is_null($base)) {
            $param['base'] = $base;
        }
        return $this->commonApiCall('list', $param);
    }
    
    
    function _preparePut($path, $keys, $fileSize, $base = null) {
        $chunkSize = self::CHUNK_SIZE;
        
        $param = array('path'=>$path, 'keys'=>$keys, 'fileSize'=>$fileSize);
        if (!is_null($base)) {
            $param['base'] = $base;
        }
        
        if (!is_null($chunkSize)) {
            $param['chunkSize'] = $chunkSize;
        }
        return $this->commonApiCall('prepare_put', $param);
    }
    
    function _putSmallFileDirect($fp, $path, $key, $fileSize, $editTime = null, $base = null) {
        $chunkSize = self::CHUNK_SIZE;
        $mimeType = null;
        
        $url = $this->getCommonApiUrl('put');
        $query = array(
            'path' => $path,
            'key' => empty($key) ? '' : strval($key),
            'fileSize' => $fileSize,
            'editTime' => $editTime,
        );
        if (!is_null($base)) {
            $query['base'] = $base;
        }
        if (!is_null($chunkSize)) {
            $query['chunkSize'] = $chunkSize;
        }
        if (!is_null($mimeType)) {
            $query['mimeType'] = $mimeType;
        }
        rewind($fp);
        $content = stream_get_contents($fp);//fread($fp, $fileSize);
        $qstr = http_build_query($query);
        $url.= '?'.$qstr;
        $headers = array('Content-Type'=>'text/plain');
        $result = $this->httpClient->request($url, 'POST', $content, $headers);
        $info = json_decode($result['body'], true);
        
        if ($result['code'] != self::STATUS_OK) {
            throw new EverboxClientException($result['code'], $info);
        }
        return $info;
    }
    
    function _putFileChunk($fp, $url, $fileSize, $chunkID = 0, $chunkSize = self::CHUNK_SIZE) {
        $pos = $chunkSize * $chunkID;
        fseek($fp, $pos);
        $size = ($fileSize - $pos) > $chunkSize ? $chunkSize : ($fileSize - $pos);
        $size = max($size, 0);
        
        $header = array('Content-Type: application/octet-stream');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000 * atol($this->ioTimeout));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000 * atol($this->ioConnectTimeout));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $bytes_sent = 0;
        
        $this->_bytes_sent = $bytes_sent;
        $this->_size = $size;
        
        curl_setopt($ch, CURLOPT_READFUNCTION, array($this, '_curlReadFunction'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $response = curl_exec($ch);
        if (!$response) {
            $err = 'put file to io failed: '.curl_error($ch);
        }
        curl_close($ch);
        if (!$response) {
            throw new EverboxClientException(EverboxClient::STATUS_IO_ERROR, $err);
        }
        preg_match('~^HTTP/1\.[01] +(?!100)(\d{3})\s+(.+)~m', $response, $match);
        return array($match[1]=>$match[2]);
    }
    
    function _curlReadFunction($ch, $in, $to_read) {
        $ret_size = $this->_bytes_sent + $to_read < $this->_size ? $to_read : $this->_size - $this->_bytes_sent;
        $this->_bytes_sent+= $ret_size;
        return $ret_size > 0 ? fread($in, $ret_size) : '';
    }

    function _commitPut($path, $keys, $fileSize, $editTime = null, $base = null) {
        $chunkSize = self::CHUNK_SIZE;
        $mimeType = null;
        
        $param = array('path'=>$path, 'keys'=>$keys, 'fileSize'=>$fileSize);
        if (!is_null($editTime)) {
            $param['editTime'] = $editTime;
        }
        if (!is_null($base)) {
            $param['base'] = $base;
        }
        if (!is_null($chunkSize)) {
            $param['chunkSize'] = $chunkSize;
        }
        return $this->commonApiCall('commit_put', $param);
    }
    
    /**
     * 上传文件
     *
     * @param mixed $file 源文件，可以是本地文件路径或资源类型
     * @param string $path 目标路径
     * @param string $editTime 文件编辑时间
     * @param string $base 版本
     * @param boolean $accelerateSmallFile 是否优化小文件上传
     *
     * @throws EverboxClientException
     * @return array
     */
    function put($file, $path, $editTime = null, $base = null, $accelerateSmallFile = true) {
        $chunkSize = self::CHUNK_SIZE;
        $mimeType = null;
        
        $closeFrom = false;
        if (is_string($file)) {
            $file = @fopen($file, 'r');
            $closeFrom = true;
        }
        if (!is_resource($file)) {
            throw new EverboxClientException(self::STATUS_INPUT_ERROR, 'source is not a valid file');
        }
        $stat = fstat($file);
        $fileSize = $stat['size'];
        
        $keys = $this->_calcKeys($file);
        
        if ($accelerateSmallFile && $fileSize < self::SMALL_FILE_SIZE) {
            $ret = null;
            $exception = null;
            try {
                $ret = $this->_putSmallFileDirect($file, $path, current($keys), $fileSize, $editTime, $base);//, $chunkSize, $mimeType
            } catch (Exception $e) {
                $exception = $e;
            }
            if ($closeFrom && $file) {
                fclose($file);
            }
            if ($exception) {
                throw $exception;
            }
            return $ret;
        }
        
        $exception = null;
        try {
            $result = $this->_preparePut($path, $keys, $fileSize, $base); //, $chunkSize
            for ($i = 0; $i < count($result['required']); $i++) {
                //echo "\n[chunk $i]\n";
                //var_dump($result['required'][$i]);
                $put_result = $this->_putFileChunk($file, $result['required'][$i]['url'], $fileSize, $i);
            }
            
            $result = $this->_commitPut($path, $keys, $fileSize, $editTime, $base);//, $chunkSize
        } catch (Exception $e) {
            $exception = $e;
        }
        
        if ($closeFrom && $file) {
            fclose($file);
        }
        if ($exception) {
            throw $exception;
        }
        return $result;
    }
    
    function _calcKeys($fp) {
        $ret = array();
        do {
            $data = fread($fp, self::CHUNK_SIZE);
            if ($data === '') {
                break;
            }
            $key = self::urlsafeBase64Encode(sha1($data, true));
            $ret[] = $key;
        } while (!feof($fp));
        return $ret;
    }
    
    /**
     * 获取文件信息
     * 
     * @param string $path 服务端文件路径，以 '/home' 开头
     * @param string $attName 下载时的文件名
     * @param string $key 校验码
     * @param string $base 版本
     *
     * @throws EverboxClientException
     * @return array
     */
    function get($path, $attName = null, $key = 0, $base = null) {
        $param = array('path'=>$path);
        if (!is_null($attName)) {
            $param['attName'] = $attName;
        }
        if (!is_null($key)) {
            $param['key'] = $key;
        }
        if (!is_null($base)) {
            $param['base'] = $base;
        }
        return $this->commonApiCall('get', $param);
    }
    
    /**
     * 下载文件
     * 
     * @param string $path 服务端文件路径，以 '/home' 开头
     * @param string $local_path 本地目标路径
     * @param string $attName 下载时的文件名
     * @param string $key 校验码
     * @param string $base 版本
     *
     * @throws EverboxClientException
     * @return boolean
     */
    function download($path, $local_path, $attName = null, $key = 0, $base = null) {
        $info = $this->get($path, $attName, $key, $base);
        if (!isset($info['dataUrl'])) {
            throw new EverboxClientException(EverboxClient::STATUS_INVALIDRET, 'download failed: no dataUrl');
        }
        return copy($info['dataUrl'], $local_path);
    }
    
    /**
     * 删除文件或目录
     * 
     * @param mixed $paths 服务端路径，以 '/home' 开头。可以为数组或字符串
     *
     * @throws EverboxClientException
     * @return array
     */
    function delete($paths) {
        $param = array('paths'=>$paths);
        return $this->commonApiCall('delete', $param);
    }
    
    /**
     * 撤销删除文件或目录
     * 
     * @param mixed $paths 服务端路径，以 '/home' 开头。可以为数组或字符串
     *
     * @return array
     */
    function undelete($paths) {
        $param = array('paths'=>$paths);
        return $this->commonApiCall('undelete', $param);
    }
    
    /**
     * 创建目录
     * 
     * @param string $path 服务端目录路径，以 '/home' 开头。可以为数组或字符串
     * @param int $parents 是否递归创建目录
     * @param  string $editTime 文件编辑时间
     *
     * @throws EverboxClientException
     * @return array
     */
    function mkdir($path, $parents = null, $editTime = null) {
        $param = array('path'=>$path);
        if (!is_null($editTime)) {
            $param['editTime'] = $editTime;
        }
        if (!is_null($parents)) {
            $param['parents'] = intval($parents);
        }
        return $this->commonApiCall('mkdir', $param);
    }
    
    /**
     * 彻底删除已删除的文件或目录
     * 
     * @param mixed $paths 服务端路径，以 '/home' 开头。可以为数组或字符串
     *
     * @throws EverboxClientException
     * @return array
     */
    function purge($paths) {
        $param = array('paths'=>$paths);
        return $this->commonApiCall('purge', $param);
    }
    
    /**
     * 获取用户信息
     * 
     * @return array
     */
    function info() {
        $param = array();
        return $this->commonApiCall('info', $param);
    }
    
    /**
     * 移动文件或目录
     *
     * @param mixed $from 源路径，可以为数组或字符串
     * @param string $to 目标路径
     *
     * @throws EverboxClientException
     * @return array
     */
    function move($from, $to) {
        $param = array('from'=>$from, 'to'=>$to);
        return $this->commonApiCall('move', $param);
    }
    
    /**
     * 重命名文件或目录，和移动操作的差异是：源只能是单个字符串，与目标路径重名会抛出异常
     * 
     * @param string $from 源路径
     * @param string $to 目标路径
     * 
     * @throws EverboxClientException
     * @return array
     */
    function rename($from, $to) {
        $param = array('from'=>$from, 'to'=>$to);
        return $this->commonApiCall('rename', $param);
    }
    
    /**
     * 复制文件或目录
     *
     * @param mixed $from 源路径，可以为数组或字符串
     * @param string $to 目标路径
     *
     * @throws EverboxClientException
     * @return array
     */
    function copy($from, $to) {
        $param = array('from'=>$from, 'to'=>$to);
        return $this->commonApiCall('copy', $param);
    }
    
    /**
     * 获取缩略图信息
     *
     * @param string $path 服务端文件路径，以 '/home' 开头
     * @param int $aimType 缩略图类型，见self::AIM_*
     *
     * @throws EverboxClientException
     * @return array
     */
    function thumbnail($path, $aimType) {
        $param = array('path'=>$path, 'aimType'=>$aimType);
        return $this->commonApiCall('thumbnail', $param);
    }
    
    /**
     * 下载缩略图
     *
     * @param string $path 服务端文件路径，以 '/home' 开头
     * @param int $aimType 缩略图类型，见self::AIM_*
     *
     * @throws EverboxClientException
     * @return boolean
     */
    function downloadThumbnail($path, $aimType) {
        $info = $this->thumbnail($path, $aimType);
        if (!isset($info['dataUrl'])) {
            throw new EverboxClientException(EverboxClient::STATUS_INVALIDRET, 'download failed: no dataUrl');
        }
        return copy($info['dataUrl'], $local_path);
    }
    
    /* <REMOVE> */
    function anonymousPut($file, $name) {
        $old_pattern = $this->urlPattern;
        $this->urlPattern = $this->urlPatternAnonymous;
        
        $exception = null;
        $result = null;
        try {
            $result = $this->put($file, $name, null, null, false);
        } catch (Exception $e) {
            $exception = $e;
        }
        $this->urlPattern = $old_pattern;
        if ($exception) {
            if ($exception->getCode() == self::STATUS_CONFLICTED) {
                return $exception->getInfo();
            } else {
                throw $exception;
            }
        }
        if (!empty($result)) {
            return $result;
        }
    }
    /* </REMOVE> */
    
    static function urlsafeBase64Encode($str)
    {
        return strtr(base64_encode($str), '+/', '-_');
    }
    
    static function urlsafeBase64Decode($str)
    {
        return base64_decode(strtr($str, '-_', '+/'));
    }
    
    function clientVersionEncode($service_verion)
    {
        if ($service_verion < (100000000))
        {
            $base_version = sprintf("%016d", $service_verion);
            return self::urlsafeBase64Encode($base_version);
        }
        $base_version = sprintf("%016x", $service_verion);
        return self::urlsafeBase64Encode($base_version);
    }
    
    function clientVersionDecode($base_version)
    {
        $base_version = self::urlsafeBase64Decode($base_version);
        $ver = 0;
        if (substr($base_version, 0, 8) == "00000000")
        {
            sscanf(substr($base_version, 8), "%d", $ver);
        }
        else
        {
            $ver = hexdec($base_version);
        }
        return $ver;
    }
    
    static function getCurrentTimeString() {
        return self::getTimeString(microtime());
    }
    
    static function getTimeString($microtime) {
        if (is_string($microtime)) {
            list($usec, $sec) = explode(" ", $microtime);
            return $sec.substr($usec, 2, 7);
        } elseif (is_float($microtime)) {
            $s = sprintf('%0.7f', $microtime);
            return str_replace('.', '', $s);
        } else {
            throw new EverboxClientException('bad $microtime', 0);
        }
    }
    
    function supportAnnonymous() {
        return $this->httpClient->supportAnnonymous();
    }
    
    function supportPurge() {
        return $this->httpClient->supportPurge();
    }
}


class EverboxClientException extends Exception {
    protected $info;
    
    function __construct($code, $info) {
        $this->info = $info;
        if (is_string($info)) {
            $this->message = $info;
        } else {
            $this->message = json_encode($info);
        }
        $this->code = $code;
    }
    
    function getInfo() {
        return $this->info;
    }
}

abstract class EverboxHTTPClient {
    abstract function request($url, $method = 'GET', $body = '', $additional_headers = array());
    
    function supportAnnonymous() {
        return true;
    }
    
    function supportPurge() {
        return true;
    }
}

