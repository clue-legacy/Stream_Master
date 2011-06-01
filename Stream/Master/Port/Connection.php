<?php

class Stream_Master_Port_Connection extends Stream_Master_Port{
    public function accept($timeout=0){
        $cstream = stream_socket_accept($this->stream,$timeout);
        if($cstream === false){
            throw new Stream_Master_Exception('Unable to accept new connection');
        }
        return $cstream;
    }
}
