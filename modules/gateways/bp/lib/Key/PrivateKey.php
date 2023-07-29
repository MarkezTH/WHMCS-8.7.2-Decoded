<?php

namespace WHMCS\Module\Gateway\BP\Key;

class PrivateKey extends \Bitpay\PrivateKey
{
    public function setHex($hex)
    {
        $this->hex = $hex;
        $this->dec = \Bitpay\Util\Util::decodeHex($this->hex);
    }
}
