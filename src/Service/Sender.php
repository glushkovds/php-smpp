<?php


namespace PhpSmpp\Service;


class Sender extends Service
{
    /**
     * Максимальное кол-во попыток отправки СМС
     *
     * @var integer
     */
    public $retriesCount = 3;

    /**
     * Задержка в секундах перед следующей попыткой отправки СМС
     *
     * @var integer
     */
    public $delayBetweenAttempts = 2;

    public function bind()
    {
        $this->openConnection();
        $this->client->bindTransmitter($this->login, $this->password);
    }

    public function send($phone, $message, $from)
    {
        $this->enshureConnection();
        $from = new SmppAddress($from, \OnlineCity\SMPP\SMPP::TON_ALPHANUMERIC);
        $to = new SmppAddress((int)$phone, SMPP::TON_INTERNATIONAL, SMPP::NPI_E164);

        $encodedMessage = $message;
        $dataCoding = SMPP::DATA_CODING_DEFAULT;
        if (SMS::hasUTFChars($message)) {
            $encodedMessage = iconv('UTF-8', 'UCS-2BE', $message);
            $dataCoding = SMPP::DATA_CODING_UCS2;
        }

        $lastError = null;
        $smsId = null;

        for ($i = 0; $i < $this->retriesCount; $i++) {
            try {
                $smsId = $this->client->sendSMS($from, $to, $encodedMessage, null, $dataCoding);
            } catch (\Throwable $e) {
                \Yii::info("Got error while sending SMS. Retry=$i", SMS::LOG_CATEGORY);
                \Yii::info($e, SMS::LOG_CATEGORY);
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
            Yii::error($error, SMS::LOG_CATEGORY);
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
        $from = new SmppAddress($from, SMPP::TON_UNKNOWN, SMPP::NPI_E164);
        $to = new SmppAddress((int)$phone, SMPP::TON_INTERNATIONAL, SMPP::NPI_E164);
        $encodedMessage = iconv('UTF-8', 'UCS-2BE', $message);
        $dataCoding = $encodedMessage === $message ? SMPP::DATA_CODING_DEFAULT : SMPP::DATA_CODING_UCS2_USSD;
        $smsId = $this->client->sendUSSD($from, $to, $encodedMessage, $tags, $dataCoding);
        return $smsId;
    }


}