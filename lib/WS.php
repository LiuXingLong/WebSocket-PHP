<?php
include_once('Robot.php');
class WS
{   
    private $log;         
    private $port;
    private $rotot;
    private $address; 
    private $master;            // 连接 server 的 client
    private $sockets = array(); // 不同状态的 socket 管理
    private $client = array();
    
    public function __construct($address,$port){
        $this->log = false;
        $this->port = $port;
        $this->address = $address;
        $this->master = $this->WebSocket();
        $this->sockets[] = $this->master;
        $this->rotot = new Robot();
        $this->run();
    }
    
    /**
     *  建立一个 socket套接字
     * @return resource 返回socket套接字
     */
    private function WebSocket(){
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1) or die("socket_option() failed");
        socket_bind($server, $this->address, $this->port) or die("socket_bind() failed");
        socket_listen($server,100) or die("socket_listen() failed");    
        return $server;
    }
    
    // 监听socket请求并做对应处理
    private function run(){    
        while(true) {
            $changes = $this->sockets;            
            /******   当没有套字节可以读写继续等待， 第四个参数为null为阻塞， 为0位非阻塞， 为 >0 为等待时间       ******/            
            //@socket_select($changes, $write = NULL, $except = NULL, NUll );  // 阻塞等待客户端发起连接
            if ( @socket_select($changes, $write = NULL, $except = NULL, 0 ) < 1 ) {  // 非阻塞没有客户端连接执行下一个选择
                continue;
            }
            foreach ($changes as $socket) {
                //连接主机的 client
                if ($socket == $this->master){
                    $client = socket_accept($this->master);  // 接受一个Socket连接
                    if ($client < 0) {
                        $this->log("socket_accept() failed");
                        continue;
                    } else {
                        $this->sockets[] = $client;
                        socket_getpeername($client, $addr, $por);
                        $this->client[] = array('socket'=>$client,'ip'=>$addr,'hand'=>false,'type'=>false,'status' => false);
                        $this->log("连接到客户端： " . $client);
                    }
                } else {
                    $bytes = @socket_recv($socket,$buffer,4096,0);
                    $opcode = ord($buffer[0]) & 15;
                    if($opcode == 8) {
                        $this->close($socket);                        
                        $this->log('close :' . $socket);
                        continue;
                    }
                    $key = $this->getClientKey($socket);
                    if ( $this->client[$key]['hand'] === false ) {
                        $this->doHandShake($socket, $buffer); // 如果没有握手，先握手回应
                        $this->client[$key]['hand'] = true;
                        $this->log("握手成功！");
                    } else if( $this->client[$key]['type'] === false ){
                        $this->setSendType($socket,$key,$buffer);
                    } else if( $this->client[$key]['type'] == 1 ) {
                        $this->rototSend($socket, $buffer);     // 系统机器人回复
                    } else if( $this->client[$key]['type'] == 2 ){
                        $this->massSend($socket,$key,$buffer);  // 群发给其他客户端
                    }
                    unset($buffer);
                }    
            }
        }
    }
    
    /**
     * 获取连接客户端的 key
     * @param  $socket   客户端socket
     * @return key|false 客户端key
     */
    private function getClientKey($socket){
        foreach($this->client as $k => $v){
            if($this->client[$k]['socket'] == $socket ){
                return $k;
            }
        }
        return false;
    }
    /**
     * 获取客户端的 SocketKey
     * @param  $socket   客户端socket
     * @return key|false 客户端SocketKey
     */
    private function getSocketKey($socket){
        foreach($this->sockets as $k => $v){
            if($this->sockets[$k] == $socket ){
                return $k;
            }
        }
        return false;
    }
    
    /**
     * 提取 Sec-WebSocket-Key 信息
     * @param $req 客户端请求数据头
     * @return     提取到的Sec-WebSocket-Key
     */
    private function getKey($req) {
        $key = null;
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) {
            $key = $match[1];
        }
        return $key;
    }
    
    /**
     * 得到 Sec-WebSocket-Accept 值
     * @param  $req   请求头数据
     * @return string Sec-WebSocket-Accept
     */
    private function encry($req){
        $key = $this->getKey($req);
        $mask = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
        return base64_encode(sha1($key . $mask, true));
    }
 
    /**
     * 应答 Sec-WebSocket-Accept
     * @param $socket 客户端端socket
     * @param $req    请求头数据
     */
    private function doHandShake($socket, $req){
        // 获取加密key
        $acceptKey = $this->encry($req);
        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: " . $acceptKey . "\r\n" .
            "\r\n"; 
        $this->socketWrite($socket,$upgrade);// 写入socket
    }
    
    /**
     * 解析帧数据
     * @param $buffer 需要解析的帧数据
     * @return string 解析后的数据
     */
    private function decode($buffer)  {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;
        if ($len === 126)  {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127)  {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return $decoded;
    }
    
    /**
     * 封装数据成帧       （发送数据处理）
     * @param $msg    需要发送的数据
     * @param $byte   数据分片字节大小
     * @param $type   数据编码类型
     * @return string 处理后的帧信息
     */    
    private function frame($msg,$type = 'utf8') {
        if($msg === false){
            return '';
        }
        $length = strlen($msg);
        if($length <= 125) {
            return "\x81".chr($length).$msg;
        } else if($length <= 65535) {
            //  2^16 - 1
            return "\x81".chr(126).pack("n", $length).$msg;      // 数据长度使用16进制填空两个字节（16位）     ‘n’： An unsigned short in 
        } else if($length <= 4294967295) {
            // 最大数据长度不能超过 2^32 - 1  文件以4G了
            return "\x81".chr(127).pack("xxxxN", $length).$msg; // 数据长度使用16进制填空八个字节 （32位）      ‘N’： An unsigned long in
        } else {
            // 分片处理
            $result = $this->splitZh($msg,4294967295,$type);
            return "\x81".chr(127).pack("xxxxN", 4294967295).$result[0].$this->frame($result[1],$type);
        }
    }
    
    /**
     * 中英文混合字符串   按字节分割出一串最长的字符串，并返回剩下的
     * @param $str      分割字符字符串
     * @param $byte     分割字节数大小
     * @param $type     字符串编码
     * @return string[] 分割出的字符和剩下的字符串
     */
    private function splitZh($str,$byte = 125, $type = 'utf8') {
        $result = array();
        if( $type == 'utf8' ){
            $len_byte = 3;
            $len_zh = mb_strlen($str,'utf8');
        } else if ($type == 'gbk'){
            $len_byte = 2;
            $len_zh = mb_strlen($str,'gbk');
        } else if ($type == 'gb2312'){
            $len_byte = 2;
            $len_zh = mb_strlen($str,'gb2312');
        }
        for( $i = 0, $start = 0, $end = 0; $i<$len_zh; $i++) {
            $len = $end - $start;
            if (!preg_match("/^[\x7f-\xff]+$/", $str[$end])) { //兼容gb2312,utf-8
                // 英文字符
                if( $len + 1 > $byte ){
                    $result[] = substr($str, $start, $len);
                    $result[] = substr($str, $end);
                    return $result;
                }
                $end++;
            } else {
                // 中文字符
                if( $len + $len_byte >$byte ){
                    $result[] = substr($str, $start, $len);
                    $result[] = substr($str, $end);
                    return $result;
                }
                $end += $len_byte;
            }
            if( $i == $len_zh - 1 && $start < strlen($str)){
                $result[] = substr($str, $start);
                $result[] = false;
            }
        }
        return $result;
    }
        
    /**
     * 分片封装数据成帧       （发送数据处理）
     * @param $msg    需要发送的数据
     * @param $byte   数据分片字节大小
     * @param $type   数据编码类型
     * @return string 处理后的帧信息
     */
    private function sliceFrame($msg , $byte = 125 ,$type = 'utf8') {
        $a = $this->arr_split_zh($msg, $byte,$type);
        if (count($a) == 1) {
            return "\x81" . chr(strlen($a[0])) . $a[0];
        }
        $ns = "";
        foreach ($a as $o) {
            $ns .= "\x81" . chr(strlen($o)) . $o;
        }
        return $ns;
    }
    
    /**
     * 中英文混合字符串  按字节分割成数组
     * @param $str      分割字符字符串
     * @param $byte     分割字节数大小
     * @param $type     字符串编码
     * @return string[] 分割后的字符串数组
     */
    private function arr_split_zh($str,$byte = 125, $type = 'utf8') {
        $result = array();
        if( $type == 'utf8' ){
            $len_byte = 3;
            $len_zh = mb_strlen($str,'utf8');
        } else if ($type == 'gbk'){
            $len_byte = 2;
            $len_zh = mb_strlen($str,'gbk');
        } else if ($type == 'gb2312'){
            $len_byte = 2;
            $len_zh = mb_strlen($str,'gb2312');
        }
        for( $i = 0, $start = 0, $end = 0; $i<$len_zh; $i++) {
            $len = $end - $start;
            if (!preg_match("/^[\x7f-\xff]+$/", $str[$end])) { //兼容gb2312,utf-8
                // 英文字符
                if( $len + 1 > $byte ){
                    $result[] = substr($str, $start, $len);
                    $start = $end;
                }
                $end++;
            } else {
                // 中文字符
                if( $len + $len_byte >$byte ){
                    $result[] = substr($str, $start, $len);
                    $start = $end;
                }
                $end += $len_byte;
            }
            if( $i == $len_zh - 1 && $start < strlen($str)){
                $result[] = substr($str, $start);
            }            
        }
        return $result;
    }
    
    /**
     * 设置客户端聊天模式
     * @param $Sender  客户端socket
     * @param $key     客户端Id
     * @param $buffer  客户端发生数据
     */
    private function setSendType($Sender,$key,$buffer){
        $ip = $this->client[$key]['ip'];
        if($this->client[$key]['status'] === false){
            $msg = '请先设置聊天模式  [1]系统机器人 [2]和其他人群聊';
            $this->client[$key]['status'] = true;
            $this->Send($Sender,$msg);
            $this->log(++$key . "号用户进入IP:" . $ip);
        } else {
            $type = $this->decode($buffer);
            $type = trim($type);
            if( $type == 1 ){
                $msg = '系统机器人回复开启';
                $this->client[$key]['type'] = 1;
                $this->Send($Sender,$msg);
            }else if( $type == 2 ){
                $msg = '群发给其他用户开启';
                $this->client[$key]['type'] = 2;
                $this->Send($Sender,$msg);
                $msg = ++$key . "号用户进入IP:" . $ip;
                $data['msg'] = $msg;
                $data['key'] = $key;
                // 由服务端推送信息通知其他用户     当前客户端进入
                $this->systemPushMsg($data,1,2,$Sender);
                // 推送在线用户列表给  当前客户端
                $this->systemPushList($Sender,2);                
            }else{
                $msg = '聊天模式设置错误请重新设置 [1]系统机器人 [2]和其他人群聊';
                $this->Send($Sender,$msg);
            }
        }
    }
    
    /**
     * 服务器发送消息给客户端
     * @param $client 客户端接受的socket
     * @param $msg    发送的消息
     */
    private function Send($client, $msg){
        $msg = $this->frame("系统消息: ".$msg);
        $this->socketWrite($client,$msg);
    }
    
    /**
     * 服务端（机器人）发送数据到客户端
     * @param $client 客户端接受的socket
     * @param $msg    客户端发来的消息
     */
    private function rototSend($client, $msg){ 
       $msg = $this->decode($msg);
       $msg = $this->rotot->index($msg);
       $msg = $this->frame("系统消息: ".$msg);     
       $this->socketWrite($client,$msg);
    }
    
    /**
     * （群发）服务器中转当前客户端消息到其他所有客户端
     * @param $Sender 当前客户端socket
     * @param $id     当前客户端id
     * @param $msg    当前客户端消息
     */
    private function massSend($Sender,$id,$msg){
       $msg = $this->decode($msg);
       $msg = $this->frame(++$id . '号用户：' . $msg);
       foreach($this->sockets as $k => $v){
           $key = $this->getClientKey($v);
           if( $v != $this->master && $v != $Sender && $this->client[$key]['hand'] !== false && $this->client[$key]['type'] == 2){
               $this->socketWrite($v,$msg);
           }
       }
    }
    /**
     * 服务器推送消息给客户端
     * @param $data        消息内容
     * @param $msgType     消息类型：       1用户进入              2用户离开
     * @param $clientType  客户端类型：  1系统聊天用户    2群发用户 
     * @param $socket      状态变更客户端socket
     */
    private function systemPushMsg($data,$msgType,$clientType,$socket = false ){        
        $msg['data']  = $data;
        $msg['type'] = $msgType;
        $msg = json_encode($msg);
        $msg = $this->frame($msg);
        foreach($this->sockets as $k => $v){
            $key = $this->getClientKey($v);
            if( $v != $this->master && $v != $socket && $this->client[$key]['hand'] !== false && $this->client[$key]['type'] == $clientType){
                $this->socketWrite($v,$msg);
            }
        }  
    }
    
    /**
     * 服务器推送当前用户列表到客户端
     * @param $Sender     接收客户端方
     * @param $clientType 推送用户类型   1系统聊天   2群聊
     */
    private function systemPushList($Sender,$clientType){
        foreach($this->sockets as $k => $v){
            $key = $this->getClientKey($v);
            if( $v != $this->master && $this->client[$key]['hand'] !== false && $this->client[$key]['type'] == $clientType){
                $data['key'] = $key + 1;
                $data['msg'] = $data['key'] . '号用户进入IP:' . $this->client[$key]['ip'];                
                $msg['data'][] = $data;
            }
        }
        $msg['type']  = 3; // 首次进入获取在线用户列表
        $msg = json_encode($msg);
        $msg = $this->frame($msg);
        $this->socketWrite($Sender,$msg);
    }
    
    /**
     * 关闭socket连接
     * @param $socket 端口的socket
     */
    private function close($socket){
        $cKey = $this->getClientKey($socket);
        $sKey = $this->getSocketKey($socket);       
        $key = $cKey + 1;
        $ip = $this->client[$cKey]['ip'];
        $type = $this->client[$cKey]['type'];
        socket_close($socket);
        unset($this->client[$cKey]);
        unset($this->sockets[$sKey]);  
        $msg = $key . "号用户离开IP:" . $ip;
        $this->log($msg);
        // 群用户退出通知群内其他人
        if( $type == 2 ){
            $data['msg'] = $msg;
            $data['key'] = $key;
            // 由服务端推送信息通知其他用户     当前客户端离开
            $this->systemPushMsg($data,2,2,$socket);
        }       
    }
    function socketWrite($socket,$msg){
        try {
            socket_write($socket, $msg, strlen($msg));
        } catch (Exception $e) {
            $this->close($socket);
        }
    }
    /**
     * 控制台输出信息
     * @param $msg 输出信息
     */
    private function log($msg){
        if($this->log){
            echo iconv('UTF-8','gbk',$msg);
            echo "\n";
        }
    }
}
