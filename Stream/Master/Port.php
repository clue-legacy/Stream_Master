<?php

class Stream_Master_Port extends Stream_Master_Client{
    /**
     * add new listening port
     * 
     * @param int|string    $address port to listen to
     * @param NULL|resource $context stream context to use
     * @param NULL|int      $backlog maximum size of incoming connection queue
     * @return resource listing stream port
     * @throws Stream_Master_Exception on error
     * @uses stream_context_create() for optional backlog parameter
     * @uses stream_socket_server() to create new server port
     * @see socket_listen() for more information on backlog parameter
     * @link http://www.php.net/manual/en/transports.php
     */
    public static function factory($address=0,$context=NULL,$backlog=NULL){
        if($address instanceof Stream_Master_Port){
            return $address;
        }
        if(is_int($address)){
            $address = 'tcp://127.0.0.1:'.$address;
        }else if(preg_match('/^(?<protocol>tcp|udp)\:\/\/(?<port>\d+)$/',$address,$match)){
            $address = $match['protocol'].'://127.0.0.1:'.$match['port'];
        }
        
        if($context === NULL){
            $context = stream_context_create();
        }
        if($backlog !== NULL){
            stream_context_set_option($context,'socket','backlog',$backlog);
        }
        $flags = STREAM_SERVER_BIND;
        if(!in_array(substr($address,0,6),array('udp://','udg://'))){
            $flags |= STREAM_SERVER_LISTEN;
        }
        $stream = stream_socket_server($address,$errno,$errstr,$flags,$context);
        if($stream === false){
            throw new Worker_Exception('Unable to start server on '.Debug::param($address));
        }
        return new Stream_Master_Client_Port($stream);
    }
    
    public function __construct($stream){
        $this->stream = $stream;
    }
    
    public function getNative(){
        return $this->stream;
    }
    
    /**
     * returns whether this port is a datagram port (UDP/UDG)
     * 
     * @return boolean true=datagram port (UDP/UDG), false=stream port (TCP/unix)
     */
    public function isDatagram(){
        $meta = stream_get_meta_data($this->stream);
        return ($meta['stream_type'] === 'udp_socket' || $meta['stream_type'] === 'udg_socket');
    }
    
    public function getStreamRead(){
        return $this->stream;
    }
    
    public function getStreamWrite(){
        return NULL;
    }
}

