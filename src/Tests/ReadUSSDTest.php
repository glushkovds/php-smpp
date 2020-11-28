<?php

namespace PhpSmpp\Tests;

use PhpSmpp\Client;
use PhpSmpp\Pdu\SmppUssd;
use PhpSmpp\Pdu\Pdu;
use PhpSmpp\Service\Listener;
use PhpSmpp\SMPP;
use PhpSmpp\Transport\FakeTransport;
use PHPUnit\Framework\TestCase;

class ReadUSSDTest extends TestCase
{
    /**
     * Checks the successful processing of an incoming USSD request.
     */
    public function testReadUSSD(): void
    {
        $service = new Listener([], '', '', Client::BIND_MODE_TRANSCEIVER);
        $service->client->debug = true;
        $service->client->setTransport(new FakeTransport());

        /** @var FakeTransport $transport */
        $transport = $service->client->getTransport();
        $transport->enqueueDeliverReceiptSm('deliver_sm_enquire_link');
        $transport->enqueueDeliverReceiptSm('deliver_sm_ussd');

        $service->listenOnce(function (Pdu $pdu) {
            /** @var SmppUssd $pdu */
            $this->assertInstanceOf(SmppUssd::class, $pdu);
            $this->assertEquals('992000000000', $pdu->source->value);
            $this->assertEquals('992000000001', $pdu->destination->value);
            $this->assertEquals('*3322*0#', $pdu->message);
            $this->assertEquals(SMPP::DELIVER_SM, $pdu->id);
            $this->assertEquals(SMPP::ESME_ROK, $pdu->status);
        });
    }
}
