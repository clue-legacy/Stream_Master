<?php

class Stream_Master_Client_Write extends Stream_Master_Client{
    public function __construct(Stream_Master_Client $client){
        $this->client = $client;
    }
    
    public function getNative(){
        return $this->client->getNative();
    }
    
    public function getStreamRead(){
        return NULL;
    }
    
    public function getStreamWrite(){
        return $this->client->getStreamWrite();
    }
}
