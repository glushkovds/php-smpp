<?php


namespace PhpSmpp\Service;


use PhpSmpp\Client;
use PhpSmpp\Helper;
use PhpSmpp\Logger;
use PhpSmpp\SMPP;
use PhpSmpp\Pdu\Part\Address;

class Sender extends Service
{
    /** @var int */
    public $retriesCount = 2;

    /** @var int */
    public $delayBetweenAttempts = 2;

    public function bind()
    {
        $this->openConnection();
        if (Client::BIND_MODE_TRANSCEIVER == $this->bindMode) {
            $this->client->bindTransceiver($this->login, $this->pass);
        } else {
            $this->client->bindTransmitter($this->login, $this->pass);
        }
    }

    public function send($phone, $message, $from)
    {
        $this->enshureConnection();
        $from = new Address($from, SMPP::TON_ALPHANUMERIC);
        $to = new Address((int)$phone, SMPP::TON_INTERNATIONAL, SMPP::NPI_E164);

        $encodedMessage = $message;
        $dataCoding = SMPP::DATA_CODING_DEFAULT;
        if (Helper::hasUTFChars($message)) {
            $encodedMessage = iconv('UTF-8', 'UCS-2BE', $message);
            $dataCoding = SMPP::DATA_CODING_UCS2;
        }

        $lastError = null;
        $smsId = null;

        for ($i = 0; $i < $this->retriesCount; $i++) {
            try {
                $smsId = $this->client->sendSMS($from, $to, $encodedMessage, null, $dataCoding);
            } catch (\Throwable $e) {
                call_user_func($this->debugHandler, "Got error while sending SMS. Retry=$i");
                $this->unbind();
                $this->enshureConnection();
                $lastError = $e;
            }
            if ($smsId) {
                break;
            }
            sleep($this->delayBetweenAttempts);
        }

        if (empty($smsId)) {
            $error = $lastError ?? new \Error("SMPP: no smsc answer");
            call_user_func($this->debugHandler, $error->getMessage());
            throw $error;
        }

        return $smsId;
    }

    /**
     * @param string $phone
     * @param string $message
     * @param string $from
     * @param array $tags
     * @return string|null
     */
    public function sendUSSD($phone, $message, $from, array $tags)
    {
        $this->enshureConnection();
        $from = new Address($from, SMPP::TON_UNKNOWN, SMPP::NPI_E164);
        $to = new Address((int)$phone, SMPP::TON_INTERNATIONAL, SMPP::NPI_E164);
        $encodedMessage = $message;
        $dataCoding = SMPP::DATA_CODING_DEFAULT;
        if (Helper::hasUTFChars($message)) {
            $encodedMessage = iconv('UTF-8', 'UCS-2BE', $message);
            $dataCoding = SMPP::DATA_CODING_UCS2_USSD;
        }
        $smsId = $this->client->sendUSSD($from, $to, $encodedMessage, $tags, $dataCoding);
        return $smsId;
    }


}