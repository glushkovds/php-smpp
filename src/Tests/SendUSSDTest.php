<?php

namespace PhpSmpp\Tests;

use PhpSmpp\Client;
use PhpSmpp\Service\Sender;
use PhpSmpp\Transport\FakeTransport;
use PHPUnit\Framework\TestCase;

class SendUSSDTest extends TestCase
{
    /**
     * Checks the successful processing of an outgoing USSD request.
     */
    public function testSendUSSD(): void
    {
        $service = new Sender([''], '', '', Client::BIND_MODE_TRANSCEIVER);
        $service->client->debug = true;
        $service->client->setTransport(new FakeTransport());
        $smsId = $service->sendUSSD('992000000000', 'Your request has been accepted, wait for SMS confirmation', '3322', []);
        $this->assertNotEmpty($smsId, 'Has no sms id');
    }
}
