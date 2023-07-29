<?php

namespace WHMCS\Mail\Incoming\Provider;

interface MailAuthProviderInterface
{
    public static function getSupportedAuthTypes();

    public static function supportsLegacyMailProtocols();
}
