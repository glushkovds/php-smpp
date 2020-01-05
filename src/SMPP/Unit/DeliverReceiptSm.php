<?php

namespace PhpSmpp\SMPP\Unit;


use PhpSmpp\SMPP\Tag;

class DeliverReceiptSm extends DeliverSm
{

    public $state;

    /** @inheritdoc */
    public function afterFill()
    {
        foreach ($this->tags as $tag) {
            if (Tag::MESSAGE_STATE == $tag->id) {
                $this->state = (int)bin2hex($tag->value);
            }
        }
    }
}