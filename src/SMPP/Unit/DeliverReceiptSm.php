<?php

namespace PhpSmpp\SMPP\Unit;


use PhpSmpp\Logger;
use PhpSmpp\SMPP\Tag;

class DeliverReceiptSm extends DeliverSm
{

    public $state;
    public $doneDate;
    public $receiptErrorCode;
    public $receiptErrorText;

    /** @inheritdoc */
    public function afterFill()
    {
        foreach ($this->tags as $tag) {
            if (Tag::MESSAGE_STATE == $tag->id) {
                $this->state = (int)bin2hex($tag->value);
            }
        }
        $this->parseDoneDate();
        $this->parseError();
    }

    protected function parseDoneDate()
    {
        $numMatches = preg_match('/done date:(\d{10,12})/si', $this->shortMessage, $matches);
        if ($numMatches == 0) {
            Logger::debug('Could not parse delivery receipt: ' . $this->message . "\n" . bin2hex($this->body));
        }
        $dp = str_split($matches[1], 2);
        $dd = gmmktime($dp[3], $dp[4], isset($dp[5]) ? $dp[5] : 0, $dp[1], $dp[2], $dp[0]);
        $this->doneDate = new \DateTime("@$dd", new \DateTimeZone('UTC'));
    }

    protected function parseError()
    {
        $numMatches = preg_match('/edd:(\d+)/si', $this->shortMessage, $matches);
        if ($numMatches == 0) {
            Logger::debug('Could not parse error code: ' . $this->message . "\n" . bin2hex($this->body));
        }
        $this->receiptErrorCode = (int)$matches[1];
        // TODO
        $this->receiptErrorText = "Code:$this->receiptErrorCode";
    }
}