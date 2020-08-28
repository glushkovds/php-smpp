<?php

use PHPUnit\Framework\TestCase;

class ReadSMSTest extends TestCase
{
    public function testReadSMS()
    {
        $service = new \PhpSmpp\Service\Listener([], '', '', \PhpSmpp\Client::BIND_MODE_RECEIVER);
        $service->client->debug = true;
        $service->client->setTransport(new \PhpSmpp\Transport\FakeTransport());
        /** @var \PhpSmpp\Transport\FakeTransport $transport */
        $transport = $service->client->getTransport();
        $transport->enqueueDeliverReceiptSm();
        $service->listenOnce(function (\PhpSmpp\Pdu\Pdu $pdu) {
            $this->assertInstanceOf(\PhpSmpp\Pdu\DeliverReceiptSm::class, $pdu);
            /** @var \PhpSmpp\Pdu\DeliverReceiptSm $pdu */
            $this->assertEquals('992900249911', $pdu->destination->value);
            $this->assertEquals(\PhpSmpp\SMPP::STATE_REJECTED, $pdu->state);
            $this->assertNotEmpty($pdu->msgId);
        });
    }
}