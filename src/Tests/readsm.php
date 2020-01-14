<?php

use PhpSmpp\Pdu\Sm;

require __DIR__ . '/../../vendor/autoload.php';

//$service = new \PhpSmpp\Service\Listener(['smscsim.melroselabs.com'], '615419', 'UIKPTS', true);
//$service = new \PhpSmpp\Service\Listener(['smsc-sim.smscarrier.com'], 'test', 'test', true);
$service = new \PhpSmpp\Service\Listener([], '', '', true);
$service->client->setTransport(new \PhpSmpp\Transport\FakeTransport());
$service->listen(function (Sm $sm) {
    var_dump($sm->msgId);
    if ($sm instanceof \PhpSmpp\Pdu\DeliverReceiptSm) {
        var_dump($sm->state);
        var_dump($sm->state == \PhpSmpp\SMPP::STATE_DELIVERED);
    } else {
        echo 'not receipt';
    }
    die;
});