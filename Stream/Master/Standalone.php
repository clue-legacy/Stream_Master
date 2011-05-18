<?php

class Stream_Master_Standalone extends Stream_Master{
    
    /**
     * array of clients to read from / write to
     * 
     * @var array
     */
    protected $clients;
    
    /**
     * ports waiting for new connections
     * 
     * @var array[resource]
     */
    protected $ports;
    
    /**
     * array of active tasks
     * 
     * @var array[Worker_Task]
     */
    protected $tasks;
    
    public function __construct(){
        $this->clients = array();
        $this->ports   = array();
        $this->tasks   = array();
        
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
        $this->getClientStreamRead($client); // just call to make sure client has read/write streams available 
        $this->getClientStreamWrite($client);
        
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
    
    public function getPorts(){
        return $this->ports;
    }
    
    public function startOnce($timeout){
        //echo 'selectOnce'.NL;
        return $this->streamSelect($timeout);
    }
    
    public function start(){
        while(true){
            $this->streamSelect();
        }
    }
    
    /**
     * called when a new slave has connected
     * 
     * @param resource $socket
     */
    protected function streamClientConnect($socket,$unused){
        $this->events->fireEvent('clientConnect',$socket,$unused);
    }
    
    /**
     * called when a slave has been disconnected
     * 
     * @param Worker_Slave $slav
     */
    protected function streamClientDisconnect($slave){
        $key = array_search($slave,$this->clients,true);
        if($key === false){
            throw new Exception('Invalid client handle');
        }
        unset($this->clients[$key]);
        
        $this->events->fireEvent('clientDisconnect',$slave);
    }
    
    protected function streamClientSend($slave){
        try{
            $slave->streamSend();
        }
        catch(Worker_Disconnect_Exception $e){
            return false;
        }
    }
    
    protected function streamClientReceive($slave){
        try{
            $slave->streamReceive();
        }
        catch(Worker_Disconnect_Exception $e){
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
