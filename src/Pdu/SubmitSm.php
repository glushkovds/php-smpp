<?php

namespace PhpSmpp\Pdu;


class SubmitSm extends Sm
{
    public $scheduleDeliveryTime;
    public $validityPeriod;
    public $smDefaultMsgId;
    public $replaceIfPresentFlag;
}