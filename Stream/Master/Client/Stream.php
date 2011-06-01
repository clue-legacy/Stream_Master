<?php

class Stream_Master_Client_Stream extends Stream_Master_Client{
    public function __construct($stream){
        $this->stream = $stream;
    }
    
    public function getNative(){
        return $this->stream->getNative();
    }
    
    public function getStreamRead(){
        return $this->stream;
    }
    
    public function getStreamWrite(){
        return $this->stream;
    }
}
