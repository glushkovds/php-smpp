<?php

namespace PhpSmpp\Transport;


use PhpSmpp\SMPP;
use PhpSmpp\Pdu\Pdu;

class FakeTransport implements Transport
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
        $hex = file_get_contents(__DIR__ . '/../Tests/ussd.hex');
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
}