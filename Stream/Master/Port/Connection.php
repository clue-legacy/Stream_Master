<?php

class Stream_Master_Port_Connection extends Stream_Master_Port{
    /**
     * accept a new connection from this port
     * 
     * @param float $timeout
     * @return resource
     * @throws Stream_Master_Exception on error
     * @uses stream_socket_accept()
     */
    public function accept($timeout=0){
        $cstream = stream_socket_accept($this->stream,$timeout);
        if($cstream === false){
            throw new Stream_Master_Exception('Unable to accept new connection');
        }
        return $cstream;
    }
    
    /**
     * called when it's save to read from this port (i.e. new incomming connection)
     * 
     * @param Worker_Master $master
     * @uses Worker_Master_Standalone::onPortConnection()
     */
    public function onCanRead($master){
        $master->onPortConnection($this);
    }
}
