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
     * @uses Stream_Master_Client::getStreamRead() to get incoming client stream
     * @uses Stream_Master_Client::getStreamWrite() to get outgoing client stream
     * @uses Stream_Master::streamClientSend() when data is ready to be sent
     * @uses Stream_Master::streamClientReceive() when data is ready to be received
     * @uses Stream_Master::streamClientDisconnect() to disconnect client when sending/receiving failed
     * @uses Stream_Master::streamClientConnect() when a new connection is established to either of the ports
     * @uses Stream_Master::streamPortDatagram() when a new packet is available for reading
     * @uses stream_select() internally to check streams for changes
     * @uses Stream_Master_Port_Connection::accept() internally to accept new client connections
     */
    protected function streamSelect($timeout=NULL){
        $clients = (array)$this->getStreamClients();
        $oread   = array();
        $owrite  = array();
        foreach($clients as $id=>$client){                                      // cycle through clients to initialize streams
            if(($r = $client->getStreamRead()) !== NULL){
                $oread[$id] = $r;
            }
            if(($w = $client->getStreamWrite()) !== NULL){
                $owrite[$id] = $w;
            }
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
            
            $client = $clients[$id];
            if($client instanceof Stream_Master_Port_Connection){
                $this->streamClientConnect($client->accept(),$client);
            }else if($client instanceof Stream_Master_Port_Datagram){
                $this->streamPortDatagram($client);
            }else{
                if($this->streamClientReceive($client,$stream) === false){      // nothing read => disconnect
                    $this->streamClientDisconnect($client);
                }
            }
        }
    }
    
    /**
     * get stream clients to read from / to write to
     * 
     * @return array[Stream_Master_Client] multiple or single Stream_Master_Client used to send data to and receive data from
     */
    abstract protected function getStreamClients();
    
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
     * @param resource           $clientStream newly accepted client stream
     * @param Stream_Master_Port $port         port the connection was established on
     */
    abstract protected function streamClientConnect($clientStream,$port);
    
    /**
     * called when a datagram (UDP/UDG) is available on the given port
     * 
     * @param Stream_Master_Port_Datagram $port
     */
    protected function streamPortDatagram($port){
        throw new Stream_Master_Exception('No datagram handler');
        /*
        // example code
        $request = $port->read(1000,$address);
        $response = $address.': '.$request;
        $port->write($response,$address);
        */
    }
}
