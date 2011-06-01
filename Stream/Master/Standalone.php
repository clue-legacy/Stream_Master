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
     * event emitter instance
     * 
     * @var EventEmitter
     */
    protected $events;
    
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
     * @return Stream_Master_Client
     * @uses Stream_Master_Client::factory()
     */
    public function addClient($client){
        if(!($client instanceof Stream_Master_Client)){
            $client = Stream_Master_Client::factory($client); 
        }
        
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
     * @param Stream_Master_Port|int|string $port port to listen to
     * @return Stream_Master_Port listing stream port
     * @uses Stream_Master_Port::factory()
     */
    public function addPort($port=0){
        if(!($port instanceof Stream_Master_Port)){
            $port = Stream_Master_Port::factory($port);
        }
        
        $this->clients []= $port;
        return $port;
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
}
