<?php

/**
 * simple standalone class implementing Stream_Master interface
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Stream_Master
 * @version v0.0.1
 * @link https://github.com/clue/Stream_Master
 */
class Stream_Master_Standalone extends Stream_Master{
    
    /**
     * array of clients to read from / write to
     * 
     * @var array[Stream_Master_Client]
     */
    protected $clients = array();
    
    /**
     * ports waiting for new connections
     * 
     * @var array[resource]
     */
    protected $ports = array();
    
    /**
     * event emitter instance
     * 
     * @var EventEmitter
     */
    protected $events = array();
    
    public function __construct(){
        $this->events = new EventEmitter();
    }
    
    /**
     * add event handler
     * 
     * @param string   $name
     * @param callback $function
     * @return Worker_Master_Standalone $this (chainable)
     */
    public function addEvent($name,$function){
        $this->events->addEvent($name,$function);
        return $this;
    }
    
    /**
     * get internal ID for given client
     * 
     * @param mixed $client
     * @return int
     * @throws Stream_Master_Exception when given client does not exist
     */
    public function getClientId($client){
        $key = array_search($client,$this->clients,true);
        if($key === false){
            throw new Stream_Master_Exception('Invalid client given');
        }
        return $key;
    }
    
    /**
     * add new client
     * 
     * @param mixed $client resource or instance providing getStream()/getStreamReceive()/getStreamSend()
     * @return mixed
     * @throws Stream_Master_Exception when given client is invalid
     */
    public function addClient($client){
        $client = Stream_Master_Client::factory($client);
        
        $this->clients[] = $client;
        return $client;
    }
    
    /**
     * get array with all clients
     * 
     * @return array
     */
    public function getClients(){
        return $this->clients;
    }
    
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
    public function addPort($address=0,$context=NULL,$backlog=NULL){
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
        $this->ports[] = $stream;
        return $stream;
    }
    
    /**
     * get array of listening ports
     * 
     * @return array
     */
    public function getPorts(){
        return $this->ports;
    }
    
    /**
     * wait for new events on all clients+ports
     * 
     * @param float|NULL $timeout maximum timeout in seconds (NULL=wait forever)
     * @uses Worker_Master::streamSelect()
     */
    public function startOnce($timeout=NULL){
        $this->streamSelect($timeout);
    }
    
    /**
     * start event loop and wait for events
     * 
     * @uses Worker_Master::streamSelect()
     */
    public function start(){
        while(true){
            $this->streamSelect();
        }
    }
    
    /**
     * called when a new slave has connected
     * 
     * @param resource $stream
     * @param resource $port
     */
    protected function streamClientConnect($stream,$port){
        $this->events->fireEvent('clientConnect',$stream,$port);
    }
    
    /**
     * called when a slave has been disconnected
     * 
     * @param mixed $client
     */
    protected function streamClientDisconnect($client){
        $key = array_search($client,$this->clients,true);
        if($key === false){
            throw new Exception('Invalid client handle');
        }
        unset($this->clients[$key]);
        
        $this->events->fireEvent('clientDisconnect',$client);
    }
    
    /**
     * called when a client can send data
     * 
     * @param mixed    $client
     * @param resource $stream
     * @return mixed false=disconnect
     */
    protected function streamClientSend($client,$stream){
        try{
            $this->events->fireEvent('clientWrite',$client,$stream);
        }
        catch(Stream_Master_Exception $e){
            return false;
        }
    }
    
    /**
     * calls when a client can receive data
     * 
     * @param mixed    $client
     * @param resource $stream
     * @return mixed false=disconnect
     */
    protected function streamClientReceive($client,$stream){
        try{
            $this->events->fireEvent('clientRead',$client,$stream);
        }
        catch(Stream_Master_Exception $e){
            return false;
        }
    }
    
    protected function getStreamClients(){
        return $this->clients;
    }
    
    protected function getStreamPorts(){
        return $this->ports;
    }
}
