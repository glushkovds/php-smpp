<?php

namespace PhpSmpp\Pdu;


use PhpSmpp\Pdu\Part\Address;
use PhpSmpp\SMPP;
use PhpSmpp\Pdu\Part\Tag;

abstract class Sm extends Pdu
{
    /** @var string $serviceType empty string for sms and 'USSD' for ussd */
    public $serviceType;
    /** @var Address */
    public $source;
    /** @var Address */
    public $destination;
    /** @var int $esmClass */
    public $esmClass;
    /** @var int $protocolId */
    public $protocolId;
    /** @var int $priorityFlag */
    public $priorityFlag;
    /** @var int $registeredDelivery */
    public $registeredDelivery;
    /** @var int $dataCoding */
    public $dataCoding;
    /** @var string $message */
    public $message;
    /** @var Tag[] */
    public $tags;

    /** @var string $msgId sms identifier from smsc */
    public $msgId;

    public static function constructFromPdu(Pdu $pdu)
    {
        $sm = new static($pdu->id, $pdu->status, $pdu->sequence, $pdu->body);
        return $sm;
    }

    /**
     * @return Pdu
     */
    public function buildResp()
    {
        $id = SMPP::DELIVER_SM_RESP;
        if ($this->id == SMPP::SUBMIT_SM) {
            $id = SMPP::SUBMIT_SM_RESP;
        }
        $response = new Pdu($id, SMPP::ESME_ROK, $this->sequence, "\x00");
        return $response;
    }

    /**
     * Calls after filling data by PduParser
     */
    public function afterFill()
    {

    }

}