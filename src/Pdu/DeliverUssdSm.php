<?php


namespace PhpSmpp\Pdu;


use PhpSmpp\Pdu\Part\Tag;

class DeliverUssdSm extends DeliverSm
{
    /** @var string formatted code like *[\d\*]+\# such as *100*1# */
    public $ussdCode;

    /** @var Tag Legacy tag USSD_SESSION_ID = 0x1501 */
    public $sessionTag = null;

    /** @var Tag USSD_SERVICE_OP = 0x0501 */
    public $serviceOpTag;

    /** @inheritdoc */
    public function afterFill()
    {
        $this->ussdCode = preg_replace("@[^\d\*\#]@", '', $this->shortMessage);

        /** @var SmppTag $incomingTag */
        foreach ($this->tags as $tag) {
            if (Tag::USSD_SESSION_ID == $tag->id) {
                $this->sessionTag = $tag;
            }
            if (Tag::USSD_SESSION_ID == $tag->id) {
                $this->serviceOpTag = $tag;
            }
        }
    }
}