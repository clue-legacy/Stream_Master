<?php

class Stream_Master_Client_Stream extends Stream_Master_Client{
    /**
     * stream resource
     * 
     * @var resource
     */
    protected $stream;
    
    public function __construct($stream){
        if(!is_resource($stream)){
            throw new Stream_Master_Exception('Invalid stream resource given');
        }
        $this->stream = $stream;
    }
    
    public function getNative(){
        return $this->stream;
    }
    
    public function getStreamRead(){
        return $this->stream;
    }
    
    public function getStreamWrite(){
        return $this->stream;
    }
}
