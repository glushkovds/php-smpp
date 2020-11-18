<?php

namespace PhpSmpp;

use PhpSmpp\Pdu\Part\Address;
use PhpSmpp\Pdu\Part\Tag;
use PhpSmpp\PduParser;
use PhpSmpp\Transport\SMPPSocketTransport;
use PhpSmpp\Transport\SocketTransport;
use PhpSmpp\Pdu\Pdu;
use PhpSmpp\Pdu\SmppSms;
use PhpSmpp\Pdu\SmppDeliveryReceipt;
use PhpSmpp\Exception\SmppException;
use PhpSmpp\Transport\TransportInterface;

/**
 * Class for receiving or sending sms through SMPP protocol.
 * This is a reduced implementation of the SMPP protocol, and as such not all features will or ought to be available.
 * The purpose is to create a lightweight and simplified SMPP client.
 *
 * @author hd@onlinecity.dk, paladin
 * @author amkarovec@gmail.com Denis Glushkov
 * @see http://en.wikipedia.org/wiki/Short_message_peer-to-peer_protocol - SMPP 3.4 protocol specification
 * Derived from https://github.com/onlinecity/php-smpp
 *
 * Copyright (C) 2019 Denis Glushkov
 * Copyright (C) 2011 OnlineCity
 * Copyright (C) 2006 Paladin
 *
 */
class Client
{

    const DEFAULT_PORT = 2775;

    const BIND_MODE_RECEIVER = 'receiver';
    const BIND_MODE_TRANSMITTER = 'transmitter';
    const BIND_MODE_TRANSCEIVER = 'transceiver';

    // SMPP bind parameters
    public static $system_type = "WWW";
    public static $interface_version = 0x34;
    public static $addr_ton = 0;
    public static $addr_npi = 0;
    public static $address_range = "";

    // ESME transmitter parameters
    public static $sms_service_type = "";
    public static $sms_esm_class = 0x00;
    public static $sms_protocol_id = 0x00;
    public static $sms_priority_flag = 0x00;
    public static $sms_registered_delivery_flag = 0x00;
    public static $sms_replace_if_present_flag = 0x00;
    public static $sms_sm_default_msg_id = 0x00;

    /**
     * SMPP v3.4 says octect string are "not necessarily NULL terminated".
     * Switch to toggle this feature
     * @var boolean
     */
    public $nullTerminateOctetstrings = false;

    /**
     * Use sar_msg_ref_num and sar_total_segments with 16 bit tags
     * @var integer
     */
    const CSMS_16BIT_TAGS = 0;

    /**
     * Use message payload for CSMS
     * @var integer
     */
    const CSMS_PAYLOAD = 1;

    /**
     * Embed a UDH in the message with 8-bit reference.
     * @var integer
     */
    const CSMS_8BIT_UDH = 2;

    public $csmsMethod = self::CSMS_16BIT_TAGS;

    public $debug;

    protected $pdu_queue;

    /**
     * @var TransportInterface $transport
     */
    protected $transport;
    protected $debugHandler;

    // Used for reconnect
    protected $mode;

    /** @var array */
    protected $hosts;
    protected $login;
    protected $pass;

    protected $sequence_number;
    protected $sar_msg_ref_num;

    /** @var Callable */
    protected $readSmsCallback;

    /**
     * Client constructor.
     * @param array $hosts ['ip:port','ip2:port2']
     */
    public function __construct($hosts)
    {
        $this->hosts = $hosts;

        // Internal parameters
        $this->sequence_number = 1;
        $this->debug = false;
        $this->pdu_queue = [];

        $this->debugHandler = 'error_log';
        $this->mode = null;
    }

    public function setDebugHandler(callable $callback)
    {
        $this->debugHandler = $callback;
        if ($this->transport) {
            $this->setDebugHandler($callback);
        }
    }

    /**
     * @return TransportInterface
     */
    public function getTransport()
    {
        if (empty($this->transport)) {
            $hosts = $ports = [];
            foreach ($this->hosts as $host) {
                $ar = explode(':', $host);
                $hosts[] = $ar[0];
                $ports[] = $ar[1] ?? static::DEFAULT_PORT;
            }
            $this->transport = new SMPPSocketTransport($hosts, $ports, false, $this->debugHandler);
            $this->transport->setRecvTimeout(10000); // 10 seconds
        }
        return $this->transport;
    }

    public function setTransport(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Binds the receiver.
     * @param string $login - ESME system_id
     * @param string $pass - ESME password
     * @return bool
     * @throws SmppException
     */
    public function bindReceiver($login, $pass)
    {
        $this->mode = static::BIND_MODE_RECEIVER;
        $this->bind($login, $pass, SMPP::BIND_RECEIVER);
        return true;
    }

    /**
     * Binds the transmitter.
     * @param string $login - ESME system_id
     * @param string $pass - ESME password
     * @return bool
     * @throws SmppException
     */
    public function bindTransmitter($login, $pass)
    {
        $this->mode = static::BIND_MODE_TRANSMITTER;
        $this->bind($login, $pass, SMPP::BIND_TRANSMITTER);
        return true;
    }

    /**
     * Binds the transceiver.
     * @param string $login - ESME system_id
     * @param string $pass - ESME password
     * @return bool
     * @throws SmppException
     */
    public function bindTransceiver($login, $pass)
    {
        $this->mode = static::BIND_MODE_TRANSCEIVER;
        $this->bind($login, $pass, SMPP::BIND_TRANSCEIVER);
        return true;
    }

    /**
     * Set callback handler for Sm, when it arrived
     * @param Callable $callback param is Sm object
     */
    public function setListener(callable $callback)
    {
        $this->readSmsCallback = $callback;
    }

    /**
     * Closes the session on the SMSC server.
     */
    public function close()
    {
        $this->sequence_number = 1;
        $this->pdu_queue = [];

        if (!$this->transport->isOpen()) {
            return;
        }
        if ($this->debug) {
            call_user_func($this->debugHandler, 'Unbinding...');
        }

        try {
            $response = $this->sendCommand(SMPP::UNBIND, "");
            if ($this->debug) {
                call_user_func($this->debugHandler, "Unbind status: " . $response->status);
            }
        } catch (\Throwable $e) {
            if ($this->debug) {
                call_user_func($this->debugHandler, "Unbind status: thrown error: " . $e->getMessage());
            }
            // Do nothing
        }

        $this->transport->close();
    }

    /**
     * Parse a timestring as formatted by SMPP v3.4 section 7.1.
     * Returns an unix timestamp if $newDates is false or DateTime/DateInterval is missing,
     * otherwise an object of either DateTime or DateInterval is returned.
     *
     * @param string $input
     * @param boolean $newDates
     * @return mixed
     * @throws \Exception
     */
    public function parseSmppTime($input, $newDates = true)
    {
        // Check for support for new date classes
        if (!class_exists('DateTime') || !class_exists('DateInterval')) {
            $newDates = false;
        }

        $numMatch = preg_match('/^(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{2})(\\d{1})(\\d{2})([R+-])$/', $input, $matches);
        if (!$numMatch) {
            return null;
        }
        list($whole, $y, $m, $d, $h, $i, $s, $t, $n, $p) = $matches;

        // Use strtotime to convert relative time into a unix timestamp
        if ($p == 'R') {
            if ($newDates) {
                $spec = "P";
                if ($y) $spec .= $y . 'Y';
                if ($m) $spec .= $m . 'M';
                if ($d) $spec .= $d . 'D';
                if ($h || $i || $s) $spec .= 'T';
                if ($h) $spec .= $h . 'H';
                if ($i) $spec .= $i . 'M';
                if ($s) $spec .= $s . 'S';
                return new \DateInterval($spec);
            } else {
                return strtotime("+$y year +$m month +$d day +$h hour +$i minute $s +second");
            }
        } else {
            $offsetHours = floor($n / 4);
            $offsetMinutes = ($n % 4) * 15;
            $time = sprintf("20%02s-%02s-%02sT%02s:%02s:%02s%s%02s:%02s", $y, $m, $d, $h, $i, $s, $p, $offsetHours, $offsetMinutes); // Not Y3K safe
            if ($newDates) {
                return new \DateTime($time);
            } else {
                return strtotime($time);
            }
        }
    }

    /**
     * Query the SMSC about current state/status of a previous sent SMS.
     * You must specify the SMSC assigned message id and source of the sent SMS.
     * Returns an associative array with elements: message_id, final_date, message_state and error_code.
     *    message_state would be one of the SMPP::STATE_* constants. (SMPP v3.4 section 5.2.28)
     *    error_code depends on the telco network, so could be anything.
     *
     * @param string $messageid
     * @param Address $source
     * @return array
     * @throws \Exception
     */
    public function queryStatus($messageid, Address $source)
    {
        $pduBody = pack(
            'a' . (strlen($messageid) + 1) . 'cca' . (strlen($source->value) + 1),
            $messageid, $source->ton, $source->npi, $source->value
        );
        $reply = $this->sendCommand(SMPP::QUERY_SM, $pduBody);
        if (!$reply || $reply->status != SMPP::ESME_ROK) {
            return null;
        }

        // Parse reply
        $posId = strpos($reply->body, "\0", 0);
        $posDate = strpos($reply->body, "\0", $posId + 1);
        $data = [];
        $data['message_id'] = substr($reply->body, 0, $posId);
        $data['final_date'] = substr($reply->body, $posId, $posDate - $posId);
        $data['final_date'] = $data['final_date'] ? $this->parseSmppTime(trim($data['final_date'])) : null;
        $status = unpack("cmessage_state/cerror_code", substr($reply->body, $posDate + 1));
        return array_merge($data, $status);
    }

    protected function handleNonSmPdu(Pdu $pdu)
    {
        if ($pdu->id == SMPP::ENQUIRE_LINK) {
            $response = new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00");
            $this->sendPDU($response);
        }
    }

    public function listenSm(callable $callback)
    {
        do {
            $pdu = $this->readPDU();
            if ($pdu === false) {
                return;
            }
            if (PduParser::isSm($pdu)) {
                try {
                    $sm = PduParser::fromPdu($pdu);
                    $this->sendPDU($sm->buildResp());
                } catch (\Throwable $e) {
                    call_user_func($this->debugHandler, 'Failed parse pdu: ' . $e->getMessage() . ' ' . print_r($pdu, true));
                    usleep(10e4);
                    continue;
                }
                $callback($sm);
                continue;
            }
            $this->handleNonSmPdu($pdu);
        } while ($pdu);
    }

    /**
     * Send one SMS to SMSC. Can be executed only after bindTransmitter() call.
     * $message is always in octets regardless of the data encoding.
     * For correct handling of Concatenated SMS, message must be encoded with GSM 03.38 (data_coding 0x00) or UCS-2BE (0x08).
     * Concatenated SMS'es uses 16-bit reference numbers, which gives 152 GSM 03.38 chars or 66 UCS-2BE chars per CSMS.
     *
     * @param Address $from
     * @param Address $to
     * @param string $message
     * @param array $tags (optional)
     * @param integer $dataCoding (optional)
     * @param integer $priority (optional)
     * @param string $scheduleDeliveryTime (optional)
     * @param string $validityPeriod (optional)
     * @return string message id
     */
    public function sendSMS(
        Address $from,
        Address $to,
        $message,
        $tags = null,
        $dataCoding = SMPP::DATA_CODING_DEFAULT,
        $priority = 0x00,
        $scheduleDeliveryTime = null,
        $validityPeriod = null
    )
    {
        $msg_length = strlen($message);

        if ($msg_length > 160 && $dataCoding != SMPP::DATA_CODING_UCS2 && $dataCoding != SMPP::DATA_CODING_DEFAULT) {
            return false;
        }

        switch ($dataCoding) {
            case SMPP::DATA_CODING_UCS2:
                $singleSmsOctetLimit = 140; // in octets, 70 UCS-2 chars
                $csmsSplit = 132; // There are 133 octets available, but this would split the UCS the middle so use 132 instead
                break;
            case SMPP::DATA_CODING_DEFAULT:
                $singleSmsOctetLimit = 160; // we send data in octets, but GSM 03.38 will be packed in septets (7-bit) by SMSC.
                $csmsSplit = 152; // send 152 chars in each SMS since, we will use 16-bit CSMS ids (SMSC will format data)
                break;
            default:
                $singleSmsOctetLimit = 254; // From SMPP standard
                break;
        }

        // Figure out if we need to do CSMS, since it will affect our PDU
        if ($msg_length > $singleSmsOctetLimit) {
            $doCsms = true;
            if ($this->csmsMethod != Client::CSMS_PAYLOAD) {
                $parts = $this->splitMessageString($message, $csmsSplit, $dataCoding);
                $short_message = reset($parts);
                $csmsReference = $this->getCsmsReference();
            }
        } else {
            $short_message = $message;
            $doCsms = false;
        }

        // Deal with CSMS
        if ($doCsms) {
            if ($this->csmsMethod == Client::CSMS_PAYLOAD) {
                $payload = new Tag(Tag::MESSAGE_PAYLOAD, $message, $msg_length);
                return $this->submit_sm(
                    $from, $to, null, (empty($tags) ? array($payload) : array_merge($tags, $payload)),
                    $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod
                );
            } elseif ($this->csmsMethod == Client::CSMS_8BIT_UDH) {
                $seqnum = 1;
                foreach ($parts as $part) {
                    $udh = pack('cccccc', 5, 0, 3, substr($csmsReference, 1, 1), count($parts), $seqnum);
                    $res = $this->submit_sm(
                        $from, $to, $udh . $part, $tags, $dataCoding, $priority,
                        $scheduleDeliveryTime, $validityPeriod, (Client::$sms_esm_class | 0x40),
                        $seqnum == count($parts)
                    );
                    $seqnum++;
                }
                return $res;
            } else {
                $sar_msg_ref_num = new Tag(Tag::SAR_MSG_REF_NUM, $csmsReference, 2, 'n');
                $sar_total_segments = new Tag(Tag::SAR_TOTAL_SEGMENTS, count($parts), 1, 'c');
                $seqnum = 1;
                foreach ($parts as $part) {
                    $sartags = [$sar_msg_ref_num, $sar_total_segments, new Tag(Tag::SAR_SEGMENT_SEQNUM, $seqnum, 1, 'c')];
                    $res = $this->submit_sm(
                        $from, $to, $part, (empty($tags) ? $sartags : array_merge($tags, $sartags)),
                        $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod
                    );
                    $seqnum++;
                }
                return $res;
            }
        }

        return $this->submit_sm($from, $to, $short_message, $tags, $dataCoding);
    }

    /**
     * Perform the actual submit_sm call to send SMS.
     * Implemented as a protected method to allow automatic sms concatenation.
     * Tags must be an array of already packed and encoded TLV-params.
     *
     * @param Address $source
     * @param Address $destination
     * @param string $short_message
     * @param array $tags
     * @param integer $dataCoding
     * @param integer $priority
     * @param string $scheduleDeliveryTime
     * @param string $validityPeriod
     * @param string $esmClass
     * @return string message id
     */
    protected function submit_sm(
        Address $source,
        Address $destination,
        $short_message = null,
        $tags = null,
        $dataCoding = SMPP::DATA_CODING_DEFAULT,
        $priority = 0x00,
        $scheduleDeliveryTime = null,
        $validityPeriod = null,
        $esmClass = null,
        $needReply = true
    )
    {
        if (is_null($esmClass)) {
            $esmClass = self::$sms_esm_class;
        }

        // Construct PDU with mandatory fields
        $pdu = pack(
            'a1cca' . (strlen($source->value) + 1)
            . 'cca' . (strlen($destination->value) + 1)
            . 'ccc' . ($scheduleDeliveryTime ? 'a16x' : 'a1') . ($validityPeriod ? 'a16x' : 'a1')
            . 'ccccca' . (strlen($short_message) + ($this->nullTerminateOctetstrings ? 1 : 0)),
            self::$sms_service_type,
            $source->ton,
            $source->npi,
            $source->value,
            $destination->ton,
            $destination->npi,
            $destination->value,
            $esmClass,
            self::$sms_protocol_id,
            $priority,
            $scheduleDeliveryTime,
            $validityPeriod,
            self::$sms_registered_delivery_flag,
            self::$sms_replace_if_present_flag,
            $dataCoding,
            self::$sms_sm_default_msg_id,
            strlen($short_message) + ($this->nullTerminateOctetstrings ? 1 : 0),//sm_length
            $short_message//short_message
        );

        // Add any tags
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $pdu .= $tag->getBinary();
            }
        }

        $response = $this->sendCommand(SMPP::SUBMIT_SM, $pdu, $needReply);
        if ($needReply) {
            $body = unpack("a*msgid", $response->body);
            return $body['msgid'];
        }
        return null;
    }

    /**
     * Get a CSMS reference number for sar_msg_ref_num.
     * Initializes with a random value, and then returns the number in sequence with each call.
     */
    protected function getCsmsReference()
    {
        $limit = ($this->csmsMethod == static::CSMS_8BIT_UDH) ? 255 : 65535;
        if (!isset($this->sar_msg_ref_num)) {
            $this->sar_msg_ref_num = mt_rand(0, $limit);
        }
        $this->sar_msg_ref_num++;
        if ($this->sar_msg_ref_num > $limit) {
            $this->sar_msg_ref_num = 0;
        }
        return $this->sar_msg_ref_num;
    }


    /**
     * Split a message into multiple parts, taking the encoding into account.
     * A character represented by an GSM 03.38 escape-sequence shall not be split in the middle.
     * Uses str_split if at all possible, and will examine all split points for escape chars if it's required.
     *
     * @param string $message
     * @param integer $split
     * @param integer $dataCoding (optional)
     * @return array
     */
    protected function splitMessageString($message, $split, $dataCoding = SMPP::DATA_CODING_DEFAULT)
    {
        switch ($dataCoding) {
            case SMPP::DATA_CODING_DEFAULT:
                $msg_length = strlen($message);
                // Do we need to do php based split?
                $numParts = floor($msg_length / $split);
                if ($msg_length % $split == 0) $numParts--;
                $slowSplit = false;

                for ($i = 1; $i <= $numParts; $i++) {
                    if ($message[$i * $split - 1] == "\x1B") {
                        $slowSplit = true;
                        break;
                    };
                }
                if (!$slowSplit) {
                    return str_split($message, $split);
                }

                // Split the message char-by-char
                $parts = array();
                $part = null;
                $n = 0;
                for ($i = 0; $i < $msg_length; $i++) {
                    $c = $message[$i];
                    // reset on $split or if last char is a GSM 03.38 escape char
                    if ($n == $split || ($n == ($split - 1) && $c == "\x1B")) {
                        $parts[] = $part;
                        $n = 0;
                        $part = null;
                    }
                    $part .= $c;
                }
                $parts[] = $part;
                return $parts;
            // UCS2-BE can just use str_split since we send 132 octets per message, which gives a fine split using UCS2
            case SMPP::DATA_CODING_UCS2:
            default:
                return str_split($message, $split);
        }
    }

    /**
     * Binds the socket and opens the session on SMSC
     * @param string $login ESME system_id
     * @param string $pass ESME password
     * @param string $command_id
     * @return Pdu
     */
    protected function bind($login, $pass, $command_id)
    {
        $this->login = null;
        $this->pass = null;

        if (!$this->transport->isOpen()) {
            return false;
        }
        if ($this->debug) {
            call_user_func($this->debugHandler, "Binding $this->mode...");
        }

        // Make PDU body
        $pduBody = pack(
            'a' . (strlen($login) + 1) .
            'a' . (strlen($pass) + 1) .
            'a' . (strlen(self::$system_type) + 1) .
            'CCCa' . (strlen(self::$address_range) + 1),
            $login, $pass, self::$system_type,
            self::$interface_version, self::$addr_ton,
            self::$addr_npi, self::$address_range
        );

        $response = $this->sendCommand($command_id, $pduBody);

        if ($this->debug) {
            call_user_func($this->debugHandler, "Binding status  : " . $response->status);
        }

        if ($response->status != SMPP::ESME_ROK) {
            throw new SmppException(SMPP::getStatusMessage($response->status), $response->status);
        }

        $this->login = $login;
        $this->pass = $pass;
        return $response;
    }

    /**
     * Send the enquire link command.
     * @return Pdu
     */
    public function enquireLink()
    {
        $response = $this->sendCommand(SMPP::ENQUIRE_LINK, null);
        return $response;
    }

    /**
     * Respond to any enquire link we might have waiting.
     * If will check the queue first and respond to any enquire links we have there.
     * Then it will move on to the transport, and if the first PDU is enquire link respond,
     * otherwise add it to the queue and return.
     *
     */
    public function respondEnquireLink()
    {
        // Check the queue first
        $ql = count($this->pdu_queue);
        for ($i = 0; $i < $ql; $i++) {
            $pdu = $this->pdu_queue[$i];
            if ($pdu->id == SMPP::ENQUIRE_LINK) {
                //remove response
                array_splice($this->pdu_queue, $i, 1);
                $this->sendPDU(new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00"));
            }
        }

        // Check the transport for data
        if ($this->transport->hasData()) {
            $pdu = $this->readPDU();
            if ($pdu->id == SMPP::ENQUIRE_LINK) {
                $this->sendPDU(new Pdu(SMPP::ENQUIRE_LINK_RESP, SMPP::ESME_ROK, $pdu->sequence, "\x00"));
            } elseif ($pdu) {
                array_push($this->pdu_queue, $pdu);
            }
        }
    }

    /**
     * Reconnect to SMSC.
     * This is mostly to deal with the situation were we run out of sequence numbers
     */
    protected function reconnect()
    {
        $this->close();
        sleep(1);
        $this->transport->open();
        $this->sequence_number = 1;

        if ($this->mode == 'receiver') {
            $this->bindReceiver($this->login, $this->pass);
        } else {
            $this->bindTransmitter($this->login, $this->pass);
        }
    }

    /**
     * Sends the PDU command to the SMSC and waits for response.
     * @param integer $id - command ID
     * @param string $pduBody - PDU body
     * @return Pdu
     */
    protected function sendCommand($id, $pduBody, $needReply = true)
    {
        if (!$this->transport->isOpen()) {
            return false;
        }
        $pdu = new Pdu($id, 0, $this->sequence_number, $pduBody);
        $this->sendPDU($pdu);
        if ($needReply) {
            $response = $this->readPDU_resp($this->sequence_number, $pdu->id);
            if ($response === false) {
                throw new SmppException('Failed to read reply to command: 0x' . dechex($id));
            }

            if ($response->status != SMPP::ESME_ROK) {
                throw new SmppException(SMPP::getStatusMessage($response->status), $response->status);
            }
        }

        $this->sequence_number++;

        // Reached max sequence number, spec does not state what happens now, so we re-connect
        if ($this->sequence_number >= 0x7FFFFFFF) {
            $this->reconnect();
        }

        return $needReply ? $response : null;
    }

    /**
     * Prepares and sends PDU to SMSC.
     * @param Pdu $pdu
     */
    protected function sendPDU(Pdu $pdu)
    {
        if ($this->debug) {
            $length = strlen($pdu->body) + 16;
            $header = pack("NNNN", $length, $pdu->id, $pdu->status, $pdu->sequence);
            call_user_func(
                $this->debugHandler,
                "Send PDU: $length bytes;\ncommand_id: 0x" . dechex($pdu->id) . ";\n"
                . "sequence number: $pdu->sequence;\n"
                . ' ' . chunk_split(bin2hex($header . $pdu->body), 2, ' ')
            );
        }
        $this->transport->sendPDU($pdu);
    }

    /**
     * Waits for SMSC response on specific PDU.
     * If a GENERIC_NACK with a matching sequence number, or null sequence is received instead it's also accepted.
     * Some SMPP servers, ie. logica returns GENERIC_NACK on errors.
     *
     * @param integer $seq_number - PDU sequence number
     * @param integer $command_id - PDU command ID
     * @return Pdu
     * @throws SmppException
     */
    protected function readPDU_resp($seq_number, $command_id)
    {
        // Get response cmd id from command id
        $command_id = $command_id | SMPP::GENERIC_NACK;

        // Check the queue first
        $ql = count($this->pdu_queue);
        for ($i = 0; $i < $ql; $i++) {
            $pdu = $this->pdu_queue[$i];
            if (
                ($pdu->sequence == $seq_number && ($pdu->id == $command_id || $pdu->id == SMPP::GENERIC_NACK))
                || ($pdu->sequence == null && $pdu->id == SMPP::GENERIC_NACK)
            ) {
                // remove response pdu from queue
                array_splice($this->pdu_queue, $i, 1);
                return $pdu;
            }
        }

        // Read PDUs until the one we are looking for shows up, or a generic nack pdu with matching sequence or null sequence
        do {
            $pdu = $this->readPDU();
            if ($pdu) {
                if ($pdu->sequence == $seq_number && ($pdu->id == $command_id || $pdu->id == SMPP::GENERIC_NACK)) {
                    return $pdu;
                }
                if ($pdu->sequence == null && $pdu->id == SMPP::GENERIC_NACK) {
                    return $pdu;
                }
                array_push($this->pdu_queue, $pdu); // unknown PDU push to queue
            }
        } while ($pdu);
        return false;
    }

    protected function readPDU()
    {
        $bin = $this->transport->readPDU();
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

        if ($this->debug) {
            call_user_func(
                $this->debugHandler,
                "Read PDU: $length bytes;\ncommand id: 0x" . dechex($command_id) . ";\n"
                . "command status: 0x" . dechex($command_status) . '; ' . SMPP::getStatusMessage($command_status) . ";\n"
                . "sequence number: $sequence_number;\n"
                . ' ' . chunk_split(bin2hex($bufLength . $bufHeaders . $body), 2, ' ')
            );
        }

        return $pdu;
    }

    /**
     * Reads C style null padded string from the char array.
     * Reads until $maxlen or null byte.
     *
     * @param array $ar - input array
     * @param integer $maxlen - maximum length to read.
     * @param boolean $firstRead - is this the first bytes read from array?
     * @return read string.
     */
    protected function getString(&$ar, $maxlen = 255, $firstRead = false)
    {
        $s = "";
        $i = 0;
        do {
            $c = ($firstRead && $i == 0) ? current($ar) : next($ar);
            if ($c != 0) {
                $s .= chr($c);
            }
            $i++;
        } while ($i < $maxlen && $c != 0);
        return $s;
    }

    /**
     * Read a specific number of octets from the char array.
     * Does not stop at null byte
     *
     * @param array $ar - input array
     * @param intger $length
     * @return string
     */
    protected function getOctets(&$ar, $length)
    {
        $s = "";
        for ($i = 0; $i < $length; $i++) {
            $c = next($ar);
            if ($c === false) {
                return $s;
            }
            $s .= chr($c);
        }
        return $s;
    }

    /**
     * @param $ar
     * @return bool|Tag
     */
    protected function parseTag(&$ar)
    {
        $unpackedData = unpack('nid/nlength', pack("C2C2", next($ar), next($ar), next($ar), next($ar)));
        if (!$unpackedData) throw new \InvalidArgumentException('Could not read tag data');
        extract($unpackedData);

        // Sometimes SMSC return an extra null byte at the end
        if ($length == 0 && $id == 0) {
            return false;
        }

        $value = $this->getOctets($ar, $length);
        $tag = new Tag($id, $value, $length);
        if ($this->debug) {
            call_user_func(
                $this->debugHandler,
                "Parsed tag: id: 0x" . dechex($tag->id) . ";\n"
                . "length: $tag->length;\n"
                . 'value: ' . chunk_split(bin2hex($tag->value), 2, ' ')
            );
        }
        return $tag;
    }

    /**
     * Extended version of submit_sm.
     * Allows to send USSD
     *
     * @param string $serviceType
     * @param Address $source
     * @param Address $destination
     * @param string $short_message
     * @param Tag[] $tags
     * @param integer $dataCoding
     * @param integer $priority
     * @param string $scheduleDeliveryTime
     * @param string $validityPeriod
     * @param string $esmClass
     * @return string message id
     */
    protected function submit_sm_ex(
        $serviceType,
        Address $source,
        Address $destination,
        $short_message = null,
        $tags = null,
        $dataCoding = SMPP::DATA_CODING_DEFAULT,
        $priority = 0x00,
        $scheduleDeliveryTime = null,
        $validityPeriod = null,
        $esmClass = null
    )
    {
        if (is_null($esmClass)) {
            $esmClass = self::$sms_esm_class;
        }
        $serviceTypeLen = strlen($serviceType) + 1;
        // Construct PDU with mandatory fields
        $pdu = pack(
            "a{$serviceTypeLen}cca" . (strlen($source->value) + 1)
            . 'cca' . (strlen($destination->value) + 1)
            . 'ccc' . ($scheduleDeliveryTime ? 'a16x' : 'a1') . ($validityPeriod ? 'a16x' : 'a1')
            . 'ccccca' . (strlen($short_message) + ($this->nullTerminateOctetstrings ? 1 : 0)),
            $serviceType,
            $source->ton,
            $source->npi,
            $source->value,
            $destination->ton,
            $destination->npi,
            $destination->value,
            $esmClass,
            self::$sms_protocol_id,
            $priority,
            $scheduleDeliveryTime,
            $validityPeriod,
            self::$sms_registered_delivery_flag,
            self::$sms_replace_if_present_flag,
            $dataCoding,
            self::$sms_sm_default_msg_id,
            strlen($short_message) + ($this->nullTerminateOctetstrings ? 1 : 0),//sm_length
            $short_message//short_message
        );

        // Add any tags
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $pdu .= $tag->getBinary();
            }
        }
        if ($this->debug) {
            $savepdu = chunk_split(bin2hex($pdu), 2, " ");
            call_user_func($this->debugHandler, "PDU hex: $savepdu");
        }
        $response = $this->sendCommand(SMPP::SUBMIT_SM, $pdu);
        $body = unpack("a*msgid", $response->body);
        return $body['msgid'];
    }

    public function sendUSSD(Address $from, Address $to, $message, $tags, $dataCoding)
    {
        return $this->submit_sm_ex(
            SMPP::SERVICE_TYPE_USSD, $from, $to, $message, $tags, $dataCoding,
            0x00, null, null, SMPP::ESM_SUBMIT_MODE_STOREANDFORWARD
        );
    }
}
