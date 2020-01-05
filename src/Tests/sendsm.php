<?php

require '../../vendor/autoload.php';

//$service = new \PhpSmpp\Service\Sender(['smscsim.melroselabs.com'], '615419', 'UIKPTS', true);
$service = new \PhpSmpp\Service\Sender(['smsc-sim.smscarrier.com'], 'test', 'test', true);
$service->client->csmsMethod = \PhpSmpp\SMPP\SmppClient::CSMS_PAYLOAD;
//$smsId = $service->send(79824819070, 'First test', 'VIRTA');
$smsId = $service->send(44615419, 'First test Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет! Привет!', 'VIRTA');
var_dump($smsId);

