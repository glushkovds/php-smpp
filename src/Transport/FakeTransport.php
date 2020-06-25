<?php

namespace PhpSmpp\Transport;


use PhpSmpp\SMPP;
use PhpSmpp\Pdu\Pdu;

class FakeTransport implements TransportInterface
{

    public static $sendDeliversm = 1;
    public static $sendBindOk = 0;

    public $queue;

    public function __construct()
    {
        $this->initQueue();
    }

    public function initQueue()
    {
        $this->queue[] = (new Pdu(SMPP::BIND_RECEIVER_RESP, SMPP::ESME_ROK, 1, "\x00"))->getBinary();
        $this->queue[] = (new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, 1, "\x00"))->getBinary();
        $this->queue[] = self::getDelivery();
    }

    protected function getDelivery()
    {
        $hex = file_get_contents(__DIR__ . '/../Tests/deliversm.hex');
        $bin = hex2bin($hex);
        return $bin;
    }

    public function readPDU()
    {
        return array_shift($this->queue);
    }

    public function isOpen()
    {
        return true;
    }

    public function write($buffer, $length)
    {

    }

    public function setRecvTimeout($timeout)
    {
        // TODO: Implement setRecvTimeout() method.
    }

    public function close()
    {
        // TODO: Implement close() method.
    }

    public function hasData()
    {
        // TODO: Implement hasData() method.
    }

    public function open()
    {
        // TODO: Implement open() method.
    }
}