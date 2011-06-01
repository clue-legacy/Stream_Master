<?php

class Stream_Master_Port_Datagram extends Stream_Master_Port{
    /**
     * read single packet with (up to) $length bytes from port and return address
     * 
     * @param int    $length
     * @param string $address
     * @param int    $flags
     * @return string
     * @uses stream_socket_recvfrom()
     */
    public function read($length,&$address,$flags=0){
        return stream_socket_recvfrom($this->stream,$length,$flags,$address);
    }
    
    /**
     * send packet to given address
     * 
     * @param string $data
     * @param string $address
     * @param int    $flags
     * @return int
     * @uses stream_socket_sendto()
     */
    public function write($data,$address,$flags=0){
        return stream_socket_sendto($this->stream,$data,$flags,$address);
    }
    
    public function onCanRead($master){
        $master->onPortDatagram($this);
        /*
        // example code
        $request = $port->read(1000,$address);
        $response = $address.': '.$request;
        $port->write($response,$address);
        */
    }
}
