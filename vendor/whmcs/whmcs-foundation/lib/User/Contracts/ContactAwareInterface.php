<?php

namespace WHMCS\User\Contracts;

interface ContactAwareInterface
{
    public function client();

    public function contact();
}
