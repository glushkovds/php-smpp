<?php

namespace PhpSmpp;


class Logger
{
    public static $enabled = true;

    public static function debug($mixed)
    {
        echo $mixed . "\n";
    }
}