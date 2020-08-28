<?php

namespace PhpSmpp\Pdu;


use PhpSmpp\Logger;
use PhpSmpp\Pdu\Part\Tag;

class DeliverReceiptSm extends DeliverSm
{

    /** @var int $state SMPP::STATE_* */
    public $state;
    /** @var \DateTime|null $submitDate */
    public $submitDate;
    /** @var \DateTime|null $doneDate */
    public $doneDate;
    /** @var int $receiptErrorCode */
    public $receiptErrorCode;
    /** @var string $receiptErrorText */
    public $receiptErrorText;

    /** @inheritdoc */
    public function afterFill()
    {
        foreach ($this->tags as $tag) {
            if (Tag::MESSAGE_STATE == $tag->id) {
                $this->state = (int)bin2hex($tag->value);
            }
        }
        $this->parseDate('submit date', 'submitDate');
        $this->parseDate('done date', 'doneDate');
        $this->parseError();
    }

    /**
     * @param string $msgText name of date in message text. Example 'submit date'
     * @param string $name name of current object field. Example 'submitDate'
     * @throws \Exception
     */
    protected function parseDate($msgText, $name)
    {
        $matches = [];
        $numMatches = preg_match("/$msgText:(\d{10,12})/si", $this->message, $matches);
        if ($numMatches == 0) {
            Logger::debug("Could not parse $msgText: $this->message\n" . bin2hex($this->body));
            return;
        }
        $dp = str_split($matches[1], 2);
        $dd = gmmktime($dp[3], $dp[4], isset($dp[5]) ? $dp[5] : 0, $dp[1], $dp[2], $dp[0]);
        $this->{$name} = new \DateTime("@$dd", new \DateTimeZone('UTC'));
    }

    protected function parseError()
    {
        $numMatches = preg_match('/err:(\d+)/si', $this->message, $matches);
        if ($numMatches == 0) {
            Logger::debug('Could not parse error code: ' . $this->message . "\n" . bin2hex($this->body));
            return;
        }
        $this->receiptErrorCode = (int)$matches[1];
        // TODO
        $this->receiptErrorText = "Code:$this->receiptErrorCode";
    }

}