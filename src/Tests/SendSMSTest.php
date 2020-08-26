<?php

use PHPUnit\Framework\TestCase;

class SendSMSTest extends TestCase
{
    public function testSendSMS()
    {
        $service = new \PhpSmpp\Service\Sender([''], '', '', \PhpSmpp\Client::BIND_MODE_TRANSMITTER);
        $service->client->debug = true;
        $service->client->setTransport(new \PhpSmpp\Transport\FakeTransport());
        $smsId = $service->send(79001001010, 'Hello world!', 'Sender');
        $this->assertNotEmpty($smsId, 'Has no sms id');
    }
}