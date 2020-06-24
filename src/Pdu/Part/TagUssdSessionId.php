<?php


namespace PhpSmpp\Pdu\Part;


class TagUssdSessionId extends Tag
{
    /**
     * @return static
     */
    public static function build($value)
    {
        $instance = new static(Tag::USSD_SESSION_ID, $value, 4, 'a*');
        return $instance;
    }
}