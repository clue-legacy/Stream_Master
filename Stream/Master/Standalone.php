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
    
    public function addEvent($name,$function){
        $this->events->addEvent($name,$function);
        return $this;
    }
    
    public function getClientId($client){
        /*foreach($this->clients as $id=>$c){
            if($c === $client){
                return $id;
            }
        }
        throw new Exception('Invalid client given');*/
        
        $key = array_search($client,$this->clients,true);
        if($key === false){
            throw new Exception('Invalid client given');
        }
        return $key;
    }
    
    public function addClient($client){
        //$id = max(max(array_keys($this->clients)+1,0));
        $this->clients[] = $client;
        return $client;
    }
    
    public function getClients(){
        return $this->clients;
    }
    
    public function addPort($port){
        $ip = '127.0.0.1';
        $address = 'tcp://'.$ip.':'.$port;
        $stream = stream_socket_server($address,$errno,$errstr);
        if($stream === false){
            throw new Worker_Exception('Unable to start server on '.Debug::param($address));
        }
        $this->ports[] = $stream;
        return $this;
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
