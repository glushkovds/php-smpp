<?php

namespace PhpSmpp;


class Logger
{
    public static $enabled = true;

    public static function debug($mixed)
    {
        if (!static::$enabled) {
            return;
        }
        // TODO
    }
}