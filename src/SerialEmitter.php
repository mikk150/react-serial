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
    const CHARLENGTH_8 = 8;
    const CHARLENGTH_7 = 7;
    const CHARLENGTH_6 = 6;
    const CHARLENGTH_5 = 5;

    const STOPBITS_1 = 1;
    const STOPBITS_2 = 2;

    const FLOW_NONE = 0;
    const FLOW_RTC_CTS = 1;
    const FLOW_XON_XOFF = 2;

    const PAIRITY_NONE = 0;
    const PAIRITY_ODD = 1;
    const PAIRITY_EVEN = 2;

    protected $loop;
    
    protected $fileStreamer;

    /**
     * Creates serialemitter
     *
     * @param      \React\EventLoop\LoopInterface  $loop        The loop
     * @param      integer                         $device      The device
     * @param      integer                         $baudrate    The baudrate
     * @param      integer                         $pairity     The pairity
     * @param      integer                         $flow        The flow
     * @param      integer                         $stopbits    The stopbits
     * @param      integer                         $charlength  The charlength
     */
    public function __construct(LoopInterface $loop, $device, $baudrate, $pairity = self::PAIRITY_ODD, $flow = self::FLOW_NONE, $stopbits = self::STOPBITS_1, $charlength = self::CHARLENGTH_8)
    {
        $this->configureDevice($device, $baudrate, $pairity, $flow, $stopbits, $charlength);

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

    protected function configureDevice($device, $baudrate, $pairity, $flow, $stopbits, $charlength)
    {
        $this->exec('stty -F ' . $device . ' ' . $baudrate);
        switch ($pairity) {
            case self::PAIRITY_NONE:
                $this->exec('stty -F ' . $device . ' -parenb');
                break;
            case self::PAIRITY_ODD:
                $this->exec('stty -F ' . $device . ' parenb parodd');
                break;
            case self::PAIRITY_EVEN:
                $this->exec('stty -F ' . $device . ' parenb -parodd');
                break;
        }
        switch ($flow) {
            case self::FLOW_NONE:
                $this->exec('stty -F ' . $device . ' clocal -crtscts -ixon -ixoff');
                break;
            case self::FLOW_XON_XOFF:
                $this->exec('stty -F ' . $device . ' -clocal -crtscts ixon ixoff');
                break;
            case self::FLOW_RTC_CTS:
                $this->exec('stty -F ' . $device . ' -clocal crtscts -ixon -ixoff');
                break;
        }
        switch ($stopbits) {
            case self::STOPBITS_1:
                $this->exec('stty -F ' . $device . ' -cstopb');
                break;
            case self::STOPBITS_2:
                $this->exec('stty -F ' . $device . ' cstopb');
                break;
        }
        switch ($charlength) {
            case self::CHARLENGTH_8:
                $this->exec('stty -F ' . $device . ' cs8');
                break;
            case self::CHARLENGTH_7:
                $this->exec('stty -F ' . $device . ' cs7');
                break;
            case self::CHARLENGTH_6:
                $this->exec('stty -F ' . $device . ' cs6');
                break;
            case self::CHARLENGTH_5:
                $this->exec('stty -F ' . $device . ' cs5');
                break;
        }
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
