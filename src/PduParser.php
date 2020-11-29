<?php

namespace PhpSmpp;

use PhpSmpp\Pdu\Ussd;
use PhpSmpp\Pdu\Part\Address;
use PhpSmpp\Pdu\Part\Tag;
use PhpSmpp\Pdu\DeliverReceiptSm;
use PhpSmpp\Pdu\DeliverSm;
use PhpSmpp\Pdu\Sm;
use PhpSmpp\Pdu\Pdu;
use PhpSmpp\Pdu\SubmitSm;

class PduParser
{
    public static function isSm(Pdu $pdu)
    {
        return in_array($pdu->id, [SMPP::DELIVER_SM, SMPP::SUBMIT_SM]);
    }

    /**
     * @param Pdu $pdu
     * @return Sm
     */
    public static function fromPdu(Pdu $pdu)
    {
        // Check command id
        if (!static::isSm($pdu)) {
            throw new \InvalidArgumentException('PDU is not an SMS');
        }

        // Unpack PDU
        $ar = unpack("C*", $pdu->body);

        // Read mandatory params
        $service_type = Helper::getString($ar, 6, true);

        $source_addr_ton = next($ar);
        $source_addr_npi = next($ar);
        $source_addr = Helper::getString($ar, 21);
        $source = new Address($source_addr, $source_addr_ton, $source_addr_npi);

        $dest_addr_ton = next($ar);
        $dest_addr_npi = next($ar);
        $destination_addr = Helper::getString($ar, 21);
        $destination = new Address($destination_addr, $dest_addr_ton, $dest_addr_npi);

        $esmClass = next($ar);
        $protocolId = next($ar);
        $priorityFlag = next($ar);
        next($ar); // schedule_delivery_time
        next($ar); // validity_period
        $registeredDelivery = next($ar);
        next($ar); // replace_if_present_flag
        $dataCoding = next($ar);
        next($ar); // sm_default_msg_id
        $smLength = next($ar);
        $message = Helper::getString($ar, $smLength);

        // Check for optional params, and parse them
        $tags = [];
        if (current($ar) !== false) {
            $tags = array();
            do {
                $tag = static::parseTag($ar);
                if ($tag !== false) {
                    $tags[] = $tag;
                }
            } while (current($ar) !== false);
        }

        /** @var Sm $class */
        $class = SubmitSm::class;
        if (SMPP::DELIVER_SM == $pdu->id) {
            if (($esmClass & SMPP::ESM_DELIVER_SMSC_RECEIPT) != 0) {
                $class = DeliverReceiptSm::class;
            } elseif ($service_type === SMPP::SERVICE_TYPE_USSD) {
                $class = Ussd::class;
            } else {
                $class = DeliverSm::class;
            }
        }

        $sm = $class::constructFromPdu($pdu);
        $sm->serviceType = $service_type;
        $sm->source = $source;
        $sm->destination = $destination;
        $sm->esmClass = $esmClass;
        $sm->protocolId = $protocolId;
        $sm->priorityFlag = $priorityFlag;
        $sm->registeredDelivery = $registeredDelivery;
        $sm->dataCoding = $dataCoding;
        $sm->message = $message;
        $sm->tags = $tags;

        foreach ($sm->tags as $tag) {
            if (Tag::RECEIPTED_MESSAGE_ID == $tag->id) {
                $sm->msgId = $tag->value;
            }
        }

        $sm->afterFill();

        Logger::debug("Received sms:\n" . print_r($sm, true));

        return $sm;
    }

    /**
     * @param $ar
     * @return bool|Tag
     */
    protected static function parseTag(&$ar)
    {
        $unpackedData = unpack('nid/nlength', pack("C2C2", next($ar), next($ar), next($ar), next($ar)));
        if (!$unpackedData) throw new \InvalidArgumentException('Could not read tag data');
        /** @var int $id */
        /** @var int $length */
        extract($unpackedData);

        // Sometimes SMSC return an extra null byte at the end
        if ($length == 0 && $id == 0) {
            return false;
        }

        $value = Helper::getOctets($ar, $length);
        $tag = new Tag($id, $value, $length);

        Logger::debug("Parsed tag:");
        Logger::debug(" id     :0x" . dechex($tag->id));
        Logger::debug(" length :" . $tag->length);
        Logger::debug(" value  :" . chunk_split(bin2hex($tag->value), 2, " "));
        return $tag;
    }
}