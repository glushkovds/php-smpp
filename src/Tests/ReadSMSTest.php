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
        $transport->enqueueDeliverySm();
        $service->listenOnce(function (\PhpSmpp\Pdu\Pdu $pdu) {
            $this->assertInstanceOf(\PhpSmpp\Pdu\Pdu::class, $pdu);
//            var_dump($pdu);
//            if($pdu instanceof \PhpSmpp\Pdu\Sm) {
//                var_dump($pdu->msgId);
//            }
//            if ($pdu instanceof \PhpSmpp\Pdu\DeliverReceiptSm) {
//                var_dump($pdu->state);
//                var_dump($pdu->state == \PhpSmpp\SMPP::STATE_DELIVERED);
//            } else {
//                echo 'not receipt';
//            }
//            die;
        });
    }
}