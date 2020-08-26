<?php

namespace PhpSmpp\Transport;


use PhpSmpp\Pdu\Pdu;
use PhpSmpp\Transport\Exception\SocketTransportException;

interface TransportInterface
{
    /**
     * Sets the receive timeout.
     * Returns true on success, or false.
     * @param int $timeout Timeout in milliseconds.
     * @return boolean
     */
    public function setRecvTimeout($timeout);

    /**
     * Check if the socket is constructed, and there are no exceptions on it
     * Returns false if it's closed.
     * Throws SocketTransportException is state could not be ascertained
     * @throws SocketTransportException
     * @return bool
     */
    public function isOpen();

    /**
     * Do a clean shutdown of the socket.
     * Since we don't reuse sockets, we can just close and forget about it,
     * but we choose to wait (linger) for the last data to come through.
     * @return void
     */
    public function close();

    /**
     * Check if there is data waiting for us on the wire
     * @return boolean
     * @throws SocketTransportException
     */
    public function hasData();

    /**
     * Open the socket, trying to connect to each host in succession.
     * This will prefer IPv6 connections if forceIpv4 is not enabled.
     * If all hosts fail, a SocketTransportException is thrown.
     *
     * @return void
     * @throws SocketTransportException
     */
    public function open();

    /**
     * Prepares and sends PDU to SMSC.
     * @param Pdu $pdu
     */
    public function sendPDU(Pdu $pdu);

    /**
     * @return string|null
     */
    public function readPDU();
}