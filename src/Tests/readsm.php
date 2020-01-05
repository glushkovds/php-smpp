<?php

use PhpSmpp\SMPP\Unit\Sm;

require '../../vendor/autoload.php';

$service = new \PhpSmpp\Service\Listener([], '', '', true);
$service->client->setTransport(new \PhpSmpp\Transport\FakeTransport());
$service->listen(function (Sm $sm) {
    var_dump($sm->msgId);
    if ($sm instanceof \PhpSmpp\SMPP\Unit\DeliverReceiptSm) {
        var_dump($sm->state);
        var_dump($sm->state == \PhpSmpp\SMPP\SMPP::STATE_DELIVERED);
    } else {
        echo 'not receipt';
    }
    die;
});