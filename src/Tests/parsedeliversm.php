<?php

require __DIR__ . '/../../vendor/autoload.php';

use PhpSmpp\PduParser;
use PhpSmpp\Pdu\Pdu;

$hex = file_get_contents(__DIR__ . '/deliversm.hex');
$bin = hex2bin($hex);

// Read PDU length
$bufLength = substr($bin, 0, 4);
if (!$bufLength) {
    return false;
}
extract(unpack("Nlength", $bufLength));

// Read PDU headers
$bufHeaders = substr($bin, 4, 12);
if (!$bufHeaders) {
    return false;
}
extract(unpack("Ncommand_id/Ncommand_status/Nsequence_number", $bufHeaders));

// Read PDU body
if ($length - 16 > 0) {
    $body = substr($bin, 16);
    if (!$body) throw new \RuntimeException('Could not read PDU body');
} else {
    $body = null;
}
$pdu = new Pdu($command_id, $command_status, $sequence_number, $body);
$message = PduParser::fromPdu($pdu);
print_r($pdu);
print_r($message);
