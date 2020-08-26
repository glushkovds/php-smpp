<?php


namespace PhpSmpp\Transport;


use PhpSmpp\Pdu\Pdu;

/**
 * Class SMPPSocketTransport
 * Main implementation of TransportInterface. This class does the real work.
 * @package PhpSmpp\Transport
 */
class SMPPSocketTransport extends SocketTransport implements TransportInterface
{

    /**
     * Prepares and sends PDU to SMSC.
     * @param Pdu $pdu
     */
    public function sendPDU(Pdu $pdu)
    {
        $length = strlen($pdu->body) + 16;
        $header = pack("NNNN", $length, $pdu->id, $pdu->status, $pdu->sequence);
        $this->write($header . $pdu->body, $length);
    }

    public function readPDU()
    {
        // Read PDU length
        $bufLength = $this->read(4);
        if (!$bufLength) {
            return null;
        }
        /** @var int $length */
        extract(unpack("Nlength", $bufLength));

        // Read PDU headers
        $bufHeaders = $this->read(12);
        if (!$bufHeaders) {
            return null;
        }

        // Read PDU body
        if ($length - 16 > 0) {
            $body = $this->readAll($length - 16);
            if (!$body) throw new \RuntimeException('Could not read PDU body');
        } else {
            $body = null;
        }
        return $bufLength . $bufHeaders . $body;
    }
}