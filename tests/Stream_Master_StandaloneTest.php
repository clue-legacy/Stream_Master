<?php

require_once('../EventEmitter/EventEmitter.class.php');
require_once('Stream/Master.php');
require_once('Stream/Master/Standalone.php');

class Stream_Master_StandaloneTest extends PHPUnit_Framework_TestCase{
    public function testUdp(){
        $stream = new Stream_Master_Standalone();
        
        var_dump(stream_get_transports());
        
        $this->dump($stream->addPort());
        $this->dump($stream->addPort('udp://0'));
        $this->dump($stream->addPort('localhost:1234'));
        $this->dump($stream->addPort('tcp://0:0'));
        
        $this->dump($stream->addPort('unix:///tmp/test.unix.socket'));
        $this->dump($stream->addPort('udg:///tmp/test.udg.socket'));
        
        //$this->dump($stream->addPort('udg:///'.uniqid().'.sock'));
    }
    
    private function isDatagram($stream){
        $meta = stream_get_meta_data($stream);
        return ($meta['stream_type'] === 'udp_socket');
    }
    
    private function dump($stream){
        var_dump('Stream',$stream);
        var_dump('Meta',stream_get_meta_data($stream));
        var_dump('is datagram?',$this->isDatagram($stream));
        var_dump('local name',stream_socket_get_name($stream,false));
        //var_dump('peer name',stream_socket_get_name($stream,true));
        //var_dump('resource type',get_resource_type($stream));
        
        echo "\r\n";
    }
}
