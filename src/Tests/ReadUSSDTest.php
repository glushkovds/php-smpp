<?php

namespace PhpSmpp\Tests;

use PhpSmpp\Client;
use PhpSmpp\Pdu\Pdu;
use PhpSmpp\Pdu\Ussd;
use PhpSmpp\Service\Listener;
use PhpSmpp\Service\Sender;
use PhpSmpp\SMPP;
use PhpSmpp\Transport\FakeTransport;
use PHPUnit\Framework\TestCase;

class ReadUSSDTest extends TestCase
{
    public function testReadUSSD(): void
    {
        $listener = new Listener([], '', '', Client::BIND_MODE_TRANSCEIVER);
        $listener->client->debug = true;
        $listener->client->setTransport(new FakeTransport());

        $sender = new Sender([''], '', '', Client::BIND_MODE_TRANSCEIVER);
        $sender->client->debug = true;
        $sender->client->setTransport(new FakeTransport());

        /** @var FakeTransport $transport */
        $transport = $listener->client->getTransport();
        $transport->enqueueDeliverReceiptSm('deliver_sm_enquire_link');
        $transport->enqueueDeliverReceiptSm('deliver_sm_ussd');

        $listener->listenOnce(function (Pdu $pdu) use ($sender) {
            /** @var Ussd $pdu */
            $this->assertInstanceOf(Ussd::class, $pdu);
            $this->assertEquals(SMPP::SERVICE_TYPE_USSD, $pdu->serviceType);
            $this->assertEquals('992000000000', $pdu->source->value);
            $this->assertEquals('992000000001', $pdu->destination->value);
            $this->assertEquals('*3322*0#', $pdu->message);
            $this->assertEquals(SMPP::DELIVER_SM, $pdu->id);
            $this->assertEquals(SMPP::ESME_ROK, $pdu->status);

            $smsId = $sender->sendUSSD('992000000000', 'Your request has been accepted, wait for SMS confirmation', '3322', []);
            $this->assertNotEmpty($smsId, 'Has no sms id');
        });
    }
}
