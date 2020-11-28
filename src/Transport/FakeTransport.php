<?php

namespace PhpSmpp\Transport;


use PhpSmpp\Pdu\DeliverReceiptSm;
use PhpSmpp\Pdu\Part\Address;
use PhpSmpp\Pdu\Part\Tag;
use PhpSmpp\Pdu\SmppSms;
use PhpSmpp\SMPP;
use PhpSmpp\Pdu\Pdu;

/**
 * Class FakeTransport
 * Implementation of TransportInterface. Used for testing.
 * @package PhpSmpp\Transport
 */
class FakeTransport implements TransportInterface
{

    /**
     * Here we keep all sent pdu's via this transport.
     * In order to understand what we need to answer in readPDU method
     * @var array [ ['pdu' => Pdu object, 'handled' => true], ['pdu' => Pdu object, 'handled' => false] ]
     */
    protected $sent = [];

    protected $queue;

    protected $isOpen = false;

    protected $dataDir = __DIR__ . '/../Tests/data';

    public function enqueueDeliverReceiptSm($name = 'deliver_receipt')
    {
        $this->queue[] = $this->getPduBinary($name);
    }

    protected function getPduBinary($name)
    {
        $hex = file_get_contents("$this->dataDir/$name.hex");
        $bin = hex2bin($hex);
        return $bin;
    }

    protected function getDeliverReceiptSm()
    {
        $sms = new DeliverReceiptSm(
            SMPP::DELIVER_SM,
            SMPP::ESME_ROK,
            20,
            '',
            '',
            new Address('123123'),
            new Address('666'),
            0, 0, 0, 0, 0,
            'id:12345-9876 sub:001 dlvrd:000 submit date:20180721020159 done date:20180721020259 stat:ABC err:XYZ text:Sample'
        );
        return $sms;
    }

    protected function getTestUssd()
    {
        $sms = new SmppSms(
            5,
            SMPP::ESME_ROK,
            1,
            'USSDzz123123z44*666#',
            'USSD',
            new Address('123123'),
            new Address('666'),
            0, 0, 0, 0, 0,
            '*666#',
            [
                new Tag(Tag::USSD_SERVICE_OP, 0, 1),
                new Tag(Tag::USER_MESSAGE_REFERENCE, '', 2),
            ]
        );
        return $sms;
    }

    public function readPDU()
    {
        foreach ($this->sent as &$item) {
            if ($item['handled']) {
                continue;
            }
            $sequence = $item['pdu']->sequence;
            $answerMap = [
                SMPP::ENQUIRE_LINK => (new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $sequence, "\x00"))->getBinary(),
                SMPP::BIND_TRANSMITTER => (new Pdu(SMPP::BIND_TRANSMITTER_RESP, SMPP::ESME_ROK, $sequence, "\x00"))->getBinary(),
                SMPP::BIND_RECEIVER => (new Pdu(SMPP::BIND_RECEIVER_RESP, SMPP::ESME_ROK, $sequence, "\x00"))->getBinary(),
                SMPP::BIND_TRANSCEIVER => (new Pdu(SMPP::BIND_TRANSCEIVER_RESP, SMPP::ESME_ROK, $sequence, "\x00"))->getBinary(),
                SMPP::SUBMIT_SM => (new Pdu(SMPP::SUBMIT_SM_RESP, SMPP::ESME_ROK, $sequence, rand(100000, 999999)))->getBinary(),
            ];
            $answer = $answerMap[$item['pdu']->id] ?? null;
            if ($answer) {
                $item['handled'] = true;
                return $answer;
            }
        }
        if ($this->queue) {
            return array_shift($this->queue);
        }
        return null;
    }

    public function sendPDU(Pdu $pdu)
    {
        $this->sent[] = ['pdu' => $pdu, 'handled' => false];
    }

    public function setRecvTimeout($timeout)
    {
    }

    public function isOpen()
    {
        return $this->isOpen;
    }

    public function open()
    {
        $this->isOpen = true;
    }

    public function close()
    {
        $this->isOpen = false;
    }

    /**
     * @return bool
     */
    public function hasData()
    {
        return array_reduce(
            $this->sent,
            function ($result, $item) {
                return $result || $item['handled'];
            },
            false
        );
    }

}