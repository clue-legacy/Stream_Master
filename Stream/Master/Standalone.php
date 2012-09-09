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
    
    protected $running = false;
    
    protected $return = NULL;
    
    /**
     * when to time out (timestamp)
     * 
     * @var float|NULL
     * @see Stream_Master_Standalone::setTimeout()
     */
    protected $timeout = NULL;
    
    public function __construct(){
        $this->events = new EventEmitter();
    }
    
    /**
     * add event handler
     * 
     * @param string   $name
     * @param callback $function
     * @return Stream_Master_Standalone $this (chainable)
     */
    public function addEvent($name,$function){
        $this->events->addEvent($name,$function);
        return $this;
    }
    
    public function removeEvent($function){
        $this->events->removeEvent($function);
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
     * get client instance for given client ID
     * 
     * @param int $id
     * @return Stream_Master_Client
     * @throws Stream_Master_Exception if there's no client with the given ID
     */
    public function getClient($id){
        if(!isset($this->clients[$id])){
            throw new Stream_Master_Exception('Unknown client ID given');
        }
        return $this->clients[$id];
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
     * get array of clients that are an instance of the given class
     * 
     * @param string $instance
     * @return array
     */
    public function getClientsInstance($instance){
        $ret = array();
        foreach($this->clients as $id=>$client){
            if($client instanceof $instance){
                $ret[$id] = $client;
            }
        }
        return $ret;
    }
    
    /**
     * remove client
     * 
     * @param Stream_Master_Client $client
     * @return Stream_Master_Standalone $this (chainable)
     * @uses Stream_Master_Standalone::getClientId()
     */
    public function removeClient($client){
        $id = $this->getClientId($client);
        unset($this->clients[$id]);
        return $this;
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
     * set target time when to time out (absolute target timestamp)
     * 
     * @param float|NULL $timeout
     * @return Stream_Master_Standalone $this (cainable)
     */
    public function setTimeout($timeout){
        $this->timeout = $timeout;
        return $this;
    }
    
    /**
     * set time when to time out in seconds (relative time offset)
     * 
     * @param float|NULL $seconds
     * @return Stream_Master_Standalone $this (cainable)
     * @uses microtime()
     * @uses Stream_Master_Standalone::setTimeout()
     */
    public function setTimeoutIn($seconds){
        if($seconds !== NULL){
            $seconds = $seconds + microtime(true);
        }
        return $this->setTimeout($seconds);
    }
    
    /**
     * get timeout
     * 
     * @return float|NULL absolute timestamp or NULL=no timeout set
     */
    public function getTimeout(){
        return $this->timeout;
    }

    /**
     * get seconds remaining until timeout occurs
     * 
     * @return float|NULL seconds remaining until timeout occurs (never < 0) or NULL=no timeout set
     */
    public function getTimeoutIn(){
        if($this->timeout === NULL){
            return NULL;
        }
        $timeout = $this->timeout - microtime(true);
        return $timeout < 0 ? 0 : $timeout;
    }
    
    /**
     * check whether a timeout is actually set
     * 
     * @return boolean
     */
    public function hasTimeout(){
        return ($this->timeout !== NULL);
    }
    
    /**
     * check whether timeout is expired
     * 
     * timeout is considered expired when actually set and current time > time limit
     * 
     * @return boolean
     */
    public function isTimeoutExpired(){
        return ($this->timeout !== NULL && microtime(true) > $this->timeout);
    }
    
    /**
     * check whether the event loop is still running
     * 
     * @return boolean
     * @see Stream_Master_Standalone::start()
     */
    public function isRunning(){
        return $this->running;
    }
    
    /**
     * stop the event loop
     * 
     * @param mixed $return return value to be passed to start()
     * @return Stream_Master_Standalone $this (chainable)
     * @throws Stream_Master_Exception if event loop is not running
     */
    public function stop($return=NULL){
        if(!$this->running){
            throw new Stream_Master_Exception('Event loop not running');
        }
        $this->running = false;
        $this->return = $return;
        return $this;
    }
    
    /**
     * wait for new events on all clients+ports
     * 
     * @param float|NULL $timeoutIn maximum timeout in seconds (NULL=wait forever)
     * @uses Stream_Master::streamSelect()
     */
    public function startOnce($timeoutIn=NULL){
        $this->streamSelect($this->clients,$timeoutIn);
    }
    
    /**
     * start event loop and wait for events
     * 
     * @return mixed
     * @throws Stream_Master_Exception
     * @see Stream_Master_Standalone::stop()
     * @uses Stream_Master_Standalone::getTimeoutIn()
     * @uses Stream_Master::streamSelect()
     * @uses Stream_Master_Standalone::isTimeoutExpired()
     * @uses EventEmitter::fireEvent()
     */
    public function start(){
        if($this->running){
            throw new Stream_Master_Exception('Already running');
        }
        $this->running = true;
        $this->return  = NULL;
        do{
            try{
                if(!$this->clients){
                    throw new Stream_Master_Exception('No clients to operate on');
                }
                $this->streamSelect($this->clients,$this->getTimeoutIn());
                
                if($this->running && $this->isTimeoutExpired()){
                    $this->events->fireEvent('timeout');
                    $this->running = false;
                }
            }
            catch(Exception $e){                                                // an error occured
                $this->running = false;                                         // make sure to remove 'running' state
                throw $e;
            }
        }while($this->running);
        
        return $this->return;
    }
    
    /**
     * close all clients
     * 
     * @return Stream_Master_Standalone $this (chainable)
     * @uses Stream_Master_Client::close()
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
     * @uses Stream_Master_Standalone::addClient()
     * @uses EventEmitter::fireEvent()
     * @uses Stream_Master_Standalone::removeClient()
     * @uses Stream_Master_Client::close()
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
    public function onPortDatagram(Stream_Master_Port_Datagram $port){
        throw new Stream_Master_Exception('No datagram handler');
    }
    
    /**
     * called when a slave has been disconnected
     * 
     * @param Stream_Master_Client $client
     * @uses EventEmitter::fireEvent()
     * @uses Stream_Master_Standalone::removeClient()
     */
    public function onClientClose(Stream_Master_Client $client){
        $ex = NULL;
        try{
            $this->events->fireEvent('clientDisconnect',$client);               // fire event
        }
        catch(Exception $ex){ }                                                 // remember unexpected exception
        
        $this->removeClient($client);
        
        if($ex !== NULL){                                                       // re-throw after client has been removed
            throw $ex;
        }
    }
    
    /**
     * called when a client can send data
     * 
     * @param Stream_Master_Client $client
     * @uses EventEmitter::fireEvent()
     */
    public function onClientWrite(Stream_Master_Client $client){
        $this->events->fireEvent('clientWrite',$client);
    }
    
    /**
     * calls when a client can receive data
     * 
     * @param Stream_Master_Client $client
     * @uses EventEmitter::fireEvent()
     */
    public function onClientRead(Stream_Master_Client $client){
        $this->events->fireEvent('clientRead',$client);
    }
}
