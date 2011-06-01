<?php

class Stream_Master_Port_Datagram extends Stream_Master_Port{
    public function read($length,&$address,$flags=0){
        return stream_socket_recvfrom($this->stream,$length,$flags,$address);
    }
    
    public function write($data,$address,$flags=0){
        return stream_socket_sendto($this->stream,$data,$flags,$address);
    }
}
