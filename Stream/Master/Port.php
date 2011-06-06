<?php

abstract class Stream_Master_Port extends Stream_Master_Client{
    /**
     * add new listening port
     * 
     * @param int|string    $address port to listen to
     * @param NULL|resource $context stream context to use
     * @param NULL|int      $backlog maximum size of incoming connection queue
     * @return Stream_Master_Port_Connection|Stream_Master_Port_Datagram listing stream port
     * @throws Stream_Master_Exception on error
     * @uses stream_context_create() for optional backlog parameter
     * @uses stream_socket_server() to create new server port
     * @see socket_listen() for more information on backlog parameter
     * @link http://www.php.net/manual/en/transports.php
     */
    public static function factory($address=0,$context=NULL,$backlog=NULL){
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
        $datagram = true;
        $flags = STREAM_SERVER_BIND;
        if(!in_array(substr($address,0,6),array('udp://','udg://'))){
            $flags |= STREAM_SERVER_LISTEN;
            $datagram = false;
        }
        $stream = stream_socket_server($address,$errno,$errstr,$flags,$context);
        if($stream === false){
            throw new Stream_Master_Exception('Unable to start server on '.Debug::param($address));
        }
        return $datagram ? new Stream_Master_Port_Datagram($stream) : new Stream_Master_Port_Connection($stream);
    }
    
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * stream resource to operate on
     * 
     * @var resource
     */
    protected $stream;
    
    /**
     * instanciate new port with given stream resource
     * 
     * @param resource $stream
     */
    public function __construct($stream){
        $this->stream = $stream;
    }
    
    /**
     * get local address of port
     * 
     * @return string
     * @uses stream_socket_get_name()
     */
    public function getAddress(){
        return stream_socket_get_name($this->stream,false);
    }
    
    public function getNative(){
        return $this->stream;
    }
    
    public function getStreamRead(){
        return $this->stream;
    }
    
    public function getStreamWrite(){
        return NULL;
    }
}
