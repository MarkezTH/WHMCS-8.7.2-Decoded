<?php

namespace WHMCS\Mail\Incoming\Provider;

class GoogleAuthProvider extends \League\OAuth2\Client\Provider\Google implements MailAuthProviderInterface
{
    public static function getSupportedAuthTypes()
    {
        return [\WHMCS\Mail\MailAuthHandler::AUTH_TYPE_PLAIN, \WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2];
    }

    public static function supportsLegacyMailProtocols()
    {
        return true;
    }

    public function clearOpposingAuthData($department)
    {
        $department->password = "";
    }
}
