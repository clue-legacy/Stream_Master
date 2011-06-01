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
    
    public function removeClient($client){
        $key = array_search($client,$this->clients,true);
        if($key === false){
            throw new Exception('Invalid client handle');
        }
        unset($this->clients[$key]);
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
        $this->streamSelect($this->clients,$timeout);
    }
    
    /**
     * start event loop and wait for events
     * 
     * @uses Worker_Master::streamSelect()
     */
    public function start(){
        while(true){
            $this->streamSelect($this->clients);
        }
    }
    
    /**
     * close all clients
     * 
     * @return Worker_Master_Standalone $this (chainable)
     * @uses Worker_Master_Client::close()
     */
    public function close(){
        foreach($this->clients as $client){
            $client->close();
        }
        $this->clients = array();
        return $this;
    }
    
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * called when a new slave has connected
     * 
     * @param Stream_Master_Port_Connection $port
     * @uses Stream_Master_Port_Connection::accept() to accept new client connection
     * @uses Worker_Master_Standalone::addClient()
     * @uses EventEmitter::fireEvent()
     * @uses Worker_Master_Standalone::removeClient()
     * @uses Worker_Master_Client::close()
     */
    public function onPortConnection(Stream_Master_Port_Connection $port){
        $client = $this->addClient($port->accept());
        try{
            $this->events->fireEvent('clientConnect',$client);
        }
        catch(Stream_Master_Exception $e){ // exception caught, try to disconnect client
            //Debug::dump('Connection rejected, remove client');
            
            $this->removeClient($client);
            $client->close();
        }
    }
    
    /**
     * called when a new slave has connected
     * 
     * @param Stream_Master_Port_Connection $port
     */
    public function onPortDatagram(Stream_Master_Port_Connection $port){
        throw new Worker_Master_Exception('No datagram handler');
    }
    
    /**
     * called when a slave has been disconnected
     * 
     * @param Stream_Master_Client $client
     */
    public function onClientClose(Stream_Master_Client $client){
        $this->removeClient($client);
        
        $this->events->fireEvent('clientDisconnect',$client);
    }
    
    /**
     * called when a client can send data
     * 
     * @param Stream_Master_Client $client
     */
    public function onClientWrite(Stream_Master_Client $client){
        $this->events->fireEvent('clientWrite',$client);
    }
    
    /**
     * calls when a client can receive data
     * 
     * @param Stream_Master_Client $client
     */
    public function onClientRead(Stream_Master_Client $client){
        $this->events->fireEvent('clientRead',$client);
    }
}
