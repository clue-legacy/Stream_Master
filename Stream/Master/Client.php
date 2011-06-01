<?php

abstract class Stream_Master_Client{
    public static function factory($client){
        if(is_resource($client)){
            return new Stream_Master_Client_Stream($client);
        }else{
            return new Stream_Master_Client_Old($client);
        }
        return $client;
    }
    
    /**
     * get native client handle/resource/instance
     * 
     * @return mixed
     */
    public function getNative(){
        return $this;
    }
    
    /**
     * get readable stream resource for this client
     * 
     * @return resource|NULL
     */
    abstract public function getStreamRead();
    
    /**
     * get writable stream resource for this client
     * 
     * @return resource|NULL
     */
    abstract public function getStreamWrite();
    
    /**
     * called when it's save to read from this client
     * 
     * @param Worker_Master $master
     * @uses Worker_Master_Standalone::onClientRead()
     * @uses Worker_Master_Client::onClose() when reading fails
     */
    public function onCanRead($master){
        try{
            $master->onClientRead($this);
        }
        catch(Stream_Master_Exception $e){
            $this->onClose($master);
        }
    }
    
    /**
     * called when it's save to write to this client
     * 
     * @param Worker_Master $master
     * @uses Worker_Master_Standalone::onClientWrite()
     * @uses Worker_Master_Client::onClose() when writing fails
     */
    public function onCanWrite($master){
        try{
            $master->onClientWrite($this);
        }
        catch(Stream_Master_Exception $e){
            $this->onClose($master);
        }
    }
    
    /**
     * called when reading/writing to client failed
     * 
     * @param Worker_Master $master
     * @uses Worker_Master_Standalone::onClientClose() to remove from list of known clients
     * @uses Worker_Master_Client::close() to close open streams
     */
    public function onClose($master){
        $master->onClientClose($this);
        $this->close();
    }
    
    /**
     * close streams
     * 
     * @return Worker_Master_Client $this (chainable)
     * @uses Worker_Master_Client::getStreamRead()
     * @uses Worker_Master_Client::getStreamWrite()
     * @uses fclose()
     */
    public function close(){
        $r = $this->getStreamRead();
        $w = $this->getStreamWrite();

        if($r !== NULL){
            fclose($r);
        }
        if($w !== NULL && $w !== $r){
            fclose($w);
        }
        return $this;
    }
}
