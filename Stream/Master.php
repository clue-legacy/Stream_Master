<?php

/**
 * abstract base class simplifying sending and receiving to/from multiple client streams
 * 
 * @author mE
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
     * @uses Interface_Stream_Duplex::getStream() to get bi-directional incoming/outgoing client stream
     * @uses Interface_Stream_Duplex::getStreamReceive() to get incoming client stream
     * @uses Interface_Stream_Duplex::getStreamSend() to get outgoing client stream (if there's data to send)
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
        
        $id = -1;
        foreach((array)$this->getStreamPorts() as $port){                       // listen on all ports for new connections
            $oread[$id--] = $port;
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
            if($id >= 0){                                                       // id found in read streams
                $client = $clients[$id];
                if($this->streamClientReceive($client,$stream) === false){      // nothing read => disconnect
                    $this->streamClientDisconnect($client);
                }
            }else if($id < 0){                                                  // port socket => new connection
                $cstream = stream_socket_accept($stream,1.0);
                if($cstream === false){
                    throw new Stream_Master_Exception('Unable to accept new connection');
                }
                $this->streamClientConnect($cstream,$stream);
            }else{
                throw new Stream_Master_Exception('Invalid stream to read from');
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
