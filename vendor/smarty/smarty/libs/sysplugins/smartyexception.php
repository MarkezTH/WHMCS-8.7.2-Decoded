<?php
/**
 * Smarty exception class
 *
 * @package Smarty
 */
class SmartyException extends Exception
{
    public static $escape = false;

    /**
     * @return string
     */
    public function __toString()
    {
        return ' --> Smarty: ' . (self::$escape ? htmlentities($this->message) : $this->message) . ' <-- ';
    }
}
