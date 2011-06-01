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
     * @param array[Stream_Master_Client]|Stream_Master_Client clients to wait for
     * @param float|NULL $timeout maximum(!) timeout in seconds to wait, NULL = wait forever
     * @throws Stream_Master_Exception on error
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
    protected function streamSelect($clients,$timeout=NULL){
        if(!is_array($clients)){
            $clients = array($clients);
        }
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
            $clients[$id]->onCanWrite($this);
        }
        
        foreach($read as $stream){
            $id = array_search($stream,$oread,true);
            if($id === false){
                throw new Stream_Master_Exception('Invalid stream to read from');
            }
            $clients[$id]->onCanRead($this);
        }
    }
}
