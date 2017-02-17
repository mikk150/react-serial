<?php

namespace mikk150\serial;

use Evenement\EventEmitter;
use React\Stream\Stream as FileStreamer;
use React\EventLoop\LoopInterface;

/**
*
*/
class SerialEmitter extends EventEmitter
{
    protected $loop;
    
    protected $fileStreamer;

    /**
     * Creates serialEmitter
     *
     * @param      \React\EventLoop\LoopInterface  $loop    Loop
     * @param      string                          $device  Device
     * @param      string                          $config  Device configuration
     */
    public function __construct(LoopInterface $loop, $device, $config)
    {
        $this->configureDevice($device, $config);

        $stream = fopen($device, 'rw+');

        $this->fileStreamer = new FileStreamer($stream, $loop);

        $that = $this;

        $this->fileStreamer->on('error', function ($error) use ($that) {
            $that->emit('error', [$error, $that]);
        });
        $this->fileStreamer->on('drain', function () use ($that) {
            $that->emit('drain', [$that]);
        });

        $this->fileStreamer->on('data', function ($data) use ($that) {
            $that->handleData($data);
        });
    }
    public function write($data)
    {
        $this->fileStreamer->write($data);
    }

    protected function configureDevice($device, $config)
    {
        $this->exec('stty -F ' . $device . ' ' . $config);
    }

    protected function handleData($data)
    {
        $this->emit('data', [$data]);
    }

    protected function exec($cmd, &$out = null)
    {
        $desc = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $proc = proc_open($cmd, $desc, $pipes);
        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $retVal = proc_close($proc);
        if (func_num_args() == 2) {
            $out = [$ret, $err];
        }
        return $retVal;
    }
}
