<?php

namespace WHMCS\Mail\Incoming\Protocol;

interface Oauth2Interface
{
    public function oauth2Login($userName, $accessToken);
}
