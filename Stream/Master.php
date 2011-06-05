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
     * wait on given streams for incoming/outgoing changes once
     * 
     * this will issue stream_select() once only and return immediately when
     * something interesting happens.
     * 
     * @param array[Stream_Master_Client]|Stream_Master_Client clients to wait for
     * @param float|NULL $timeout maximum(!) timeout in seconds to wait, NULL = wait forever
     * @throws Stream_Master_Exception on error
     * @uses Stream_Master_Client::getStreamRead() to get incoming client stream
     * @uses Stream_Master_Client::getStreamWrite() to get outgoing client stream
     * @uses stream_select() internally to check streams for changes
     * @uses Stream_Master_Client::onCanWrite() when data is ready to be sent
     * @uses Stream_Master_Client::onCanRead() when data is ready to be received
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
        
        if(!$oread && !$owrite){                                                // nothing to be done
            return;
        }
        
        $ssleep  = NULL;
        $usleep  = NULL;
        if($timeout !== NULL){                                                  // calculate timeout into ssleep/usleep
            $ssleep = (int)$timeout;
            $usleep = (int)(($timeout - $ssleep)*1000000);
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
