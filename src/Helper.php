<?php


namespace PhpSmpp;


class Helper
{
    /**
     * Reads C style null padded string from the char array.
     * Reads until $maxlen or null byte.
     *
     * @param array $ar - input array
     * @param integer $maxlen - maximum length to read.
     * @param boolean $firstRead - is this the first bytes read from array?
     * @return read string.
     */
    public static function getString(&$ar, $maxlen = 255, $firstRead = false)
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
    public static function getOctets(&$ar, $length)
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
     * @param string $message
     * @return bool
     */
    public static function hasUTFChars($message)
    {
         $gsm7bitChars = "\\\@£\$¥èéùìòÇ\nØø\rÅå?_FG?O??ST?ÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}[~]|";
        $textlen = mb_strlen($message);
        for ($i = 0; $i < $textlen; $i++) {
            if ((strpos($gsm7bitChars, $message[$i]) == false) && ($message[$i] != "\\")) {
                return false;
            } //strpos not     able to detect \ in string
        }
        return true;
    }

}
