<?php

namespace PhpSmpp\SMPP\Unit;


use PhpSmpp\SMPP\SMPP;
use PhpSmpp\SMPP\Tag;

abstract class Sm extends SmppPdu
{
    public $serviceType;
    public $source;
    public $destination;
    public $esmClass;
    public $protocolId;
    public $priorityFlag;
    public $registeredDelivery;
    public $dataCoding;
    public $shortMessage;
    /** @var Tag[] */
    public $tags;

    /** @var string sms identifier from smsc */
    public $msgId;

    public static function constructFromPdu(SmppPdu $pdu)
    {
        $sm = new static($pdu->id, $pdu->status, $pdu->sequence, $pdu->body);
        return $sm;
    }

    public function buildResp()
    {
        $id = SMPP::DELIVER_SM_RESP;
        if ($this->id == SMPP::SUBMIT_SM) {
            $id = SMPP::SUBMIT_SM_RESP;
        }
        $response = new SmppPdu($id, SMPP::ESME_ROK, $this->sequence, "\x00");
        return $response;
    }

    /**
     * Calls after filling data by PduParser
     */
    public function afterFill()
    {

    }

}