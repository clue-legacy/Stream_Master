<?php

/**
 * abstract base class simplifying sending and receiving to/from multiple client streams
 * 
 * @author Christian Lück <christian@lueck.tv>
 * @copyright Copyright (c) 2011, Christian Lück
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @package Stream_Master
 * @version v0.0.1
 * @link https://github.com/clue/Stream_Master
 */
abstract class Stream_Master{
    /**
     * wait on all streams for incoming/outgoing changes once
     * 
     * this will issue stream_select() once only and return immediately when
     * something interesting happens.
     * 
     * @param float|NULL $timeout maximum(!) timeout in seconds to wait, NULL = wait forever
     * @throws Stream_Master_Exception on error
     * @uses Stream_Master::getStreamClients() to get all client streams (incoming and outgoing)
     * @uses Stream_Master::getClientStreamRead() to get incoming client stream
     * @uses Stream_Master::getClientStreamWrite() to get outgoing client stream
     * @uses Stream_Master::getStreamPorts() to get additional ports for check for new incoming client connections
     * @uses Stream_Master::streamClientSend() when data is ready to be sent
     * @uses Stream_Master::streamClientReceive() when data is ready to be received
     * @uses Stream_Master::streamClientDisconnect() to disconnect client when sending/receiving failed
     * @uses Stream_Master::streamClientConnect() when a new connection is established to either of the ports
     * @uses stream_select() internally to check streams for changes
     * @uses stream_socket_accept() internally to accept new client connections
     */
    protected function streamSelect($timeout=NULL){
        $clients = (array)$this->getStreamClients();
        $oread   = array();
        $owrite  = array();
        foreach($clients as $id=>$client){                                      // cycle through clients to initialize streams
            if(($r = $this->getClientStreamRead($client)) !== NULL){
                $oread[$id] = $r;
            }
            if(($w = $this->getClientStreamWrite($client)) !== NULL){
                $owrite[$id] = $w;
            }
        }
        
        $ports = (array)$this->getStreamPorts();
        foreach($ports as $port){                                               // listen on all ports for new connections
            $oread[] = $port;
        }
        
        $ssleep  = NULL;
        $usleep  = NULL;
        if($timeout !== NULL){                                                  // calculate timeout into ssleep/usleep
            $usleep = (int)(($timeout - (int)$timeout)*1000000);
            $ssleep = (int)$timeout;
        }
        
        $read   = $oread;
        $write  = $owrite;
        $ignore = NULL;
        
        $ret = stream_select($read,$write,$ignore,$ssleep,$usleep);
        if($ret === false){
            throw new Stream_Master_Exception('stream_select() failed');
        }
        
        foreach($write as $stream){
            $id = array_search($stream,$owrite,true);
            if($id === false){
                throw new Stream_Master_Exception('Invalid stream to write to');
            }
            
            $client = $clients[$id];
            if($this->streamClientSend($client,$stream) === false){             // nothing sent => disconnect
                $this->streamClientDisconnect($client);
            }
        }
        
        foreach($read as $stream){
            $id = array_search($stream,$oread,true);
            if($id === false){
                throw new Stream_Master_Exception('Invalid stream to read from');
            }
            if(array_search($stream,$ports,true) === false){                    // stream is not a port
                $client = $clients[$id];
                if($this->streamClientReceive($client,$stream) === false){      // nothing read => disconnect
                    $this->streamClientDisconnect($client);
                }
            }else{
                if($this->isPortDatagram($stream)){
                    $this->streamPortDatagram($stream);
                }else{
                    $cstream = stream_socket_accept($stream,1.0);
                    if($cstream === false){
                        throw new Stream_Master_Exception('Unable to accept new connection');
                    }
                    $this->streamClientConnect($cstream,$stream);
                }
            }
        }
    }
    
    /**
     * get stream clients to read from / to write to
     * 
     * @return array[Interface_Stream_Duplex]|Interface_Stream_Duplex multiple or single Stream_Client used to send data to and receive data from
     */
    abstract protected function getStreamClients();
    
    /**
     * get port streams where new clients can connect to
     * 
     * @return array[resource]|resource multiple or single port streams used to accept new connections on
     */
    protected function getStreamPorts(){
        return array();
    }
    
    /**
     * called when data can be sent to given client
     * 
     * return '(boolean)false' when nothing can be sent. this will automatically
     * close the client connection.
     * 
     * @param Interface_Stream_Duplex $client
     * @return mixed return (boolean)false when no data can be sent
     */
    protected function streamClientSend($client,$stream){
        return $client->send();
    }
    
    /**
     * called when data can be read from given client
     * 
     * return '(boolean)false' when nothing can be read. this will automatically
     * close the client connection.
     * 
     * @param Interface_Stream_Duplex $client
     * @return mixed return '(boolean)false' when no data can be sent
     */
    protected function streamClientReceive($client,$stream){
        return $client->receive();
    }
    
    /**
     * called when the given client disconnects
     * 
     * this is the place where you would usually want to remove the given client
     * from the list of active clients.
     * 
     * also, it's your responsibility to close all client streams
     * 
     * @param Interface_Stream_Duplex $client
     */
    abstract protected function streamClientDisconnect($client);
    
    /**
     * called when the given clients connects on the given port
     * 
     * this is the place where you would usually want to instanciate a new
     * client and add it to your list of active clients.
     * 
     * @param resource $clientStream newly accepted client stream
     * @param resource $port         port the connection was established on
     */
    abstract protected function streamClientConnect($clientStream,$port);
    
    /**
     * called when a datagram (UDP/UDG) is available on the given port
     * 
     * @param resource $port
     */
    protected function streamPortDatagram($port){
        throw new Stream_Master_Exception('No datagram handler');
        /*
        // example code
        $request = stream_socket_recvfrom($port,1000,0,$address);
        $response = $address.': '.$request;
        stream_socket_sendto($port,$response,0,$address);
        */
    }
    
    /**
     * returns whether the given port resource is a datagram port
     * 
     * @param resource $port
     * @return boolean true=datagram port (UDP/UDG), false=stream port (TCP/unix)
     */
    protected function isPortDatagram($port){
        $meta = stream_get_meta_data($port);
        return ($meta['stream_type'] === 'udp_socket' || $meta['stream_type'] === 'udg_socket');
    }
    
    /**
     * get readable stream resource for given client
     * 
     * @param mixed $client
     * @return resource|NULL
     */
    protected function getClientStreamRead($client){
        if(is_resource($client)){
            return $client;
        }else if(is_callable(array($client,'getStreamReceive'))){
            return $client->getStreamReceive();
        }else if(is_callable(array($client,'getStreamRead'))){
            return $client->getStreamRead();
        }else if(is_callable(array($client,'getStream'))){
            return $client->getStream();
        }else{
            throw new Stream_Master_Exception('Unable to determine read stream for given client'); 
        }
    }
    
    /**
     * get writeable stream resource for given client
     * 
     * @param mixed $client
     * @return resource|NULL
     */
    protected function getClientStreamWrite($client){
        if(is_resource($client)){
            return $client;
        }else if(is_callable(array($client,'getStreamSend'))){
            if(is_callable(array($client,'hasStreamOutgoing')) && !$client->hasStreamOutgoing()){
                return NULL;
            }
            return $client->getStreamSend();
        }else if(is_callable(array($client,'getStreamWrite'))){
            return $client->getStreamWrite();
        }else if(is_callable(array($client,'getStream'))){
            return $client->getStream();
        }else{
            throw new Stream_Master_Exception('Unable to determine write stream for given client'); 
        }
    }
}
