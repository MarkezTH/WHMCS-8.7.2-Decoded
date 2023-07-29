<?php

namespace WHMCS\Support\Traits;

trait Message
{
    public function getSafeMessage()
    {
        return strip_tags($this->message);
    }
}
