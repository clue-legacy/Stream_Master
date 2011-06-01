<?php

class Stream_Master_Client_Old extends Stream_Master_Client{
    public function __construct($client){
        $this->client = $client;
        
        $this->getStreamRead(); // just call to make sure client has read/write streams available 
        $this->getStreamWrite();
    }
    
    public function getNative(){
        return $this->client;
    }
    
    public function getStreamRead(){
        if(is_resource($this->client)){
            return $this->client;
        }else if(is_callable(array($this->client,'getStreamReceive'))){
            return $this->client->getStreamReceive();
        }else if(is_callable(array($this->client,'getStreamRead'))){
            return $this->client->getStreamRead();
        }else if(is_callable(array($this->client,'getStream'))){
            return $this->client->getStream();
        }else{
            throw new Stream_Master_Exception('Unable to determine read stream for given client'); 
        }
    }
    
    public function getStreamWrite(){
        if(is_resource($this->client)){
            return $this->client;
        }else if(is_callable(array($this->client,'getStreamSend'))){
            if(is_callable(array($this->client,'hasStreamOutgoing')) && !$this->client->hasStreamOutgoing()){
                return NULL;
            }
            return $this->client->getStreamSend();
        }else if(is_callable(array($this->client,'getStreamWrite'))){
            return $this->client->getStreamWrite();
        }else if(is_callable(array($this->client,'getStream'))){
            return $this->client->getStream();
        }else{
            throw new Stream_Master_Exception('Unable to determine write stream for given client'); 
        }
    }
}
