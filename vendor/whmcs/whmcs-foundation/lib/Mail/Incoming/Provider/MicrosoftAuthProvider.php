<?php

namespace WHMCS\Mail\Incoming\Provider;

class MicrosoftAuthProvider extends \Stevenmaguire\OAuth2\Client\Provider\Microsoft implements MailAuthProviderInterface
{
    protected $urlAuthorize = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize";
    protected $urlAccessToken = "https://login.microsoftonline.com/common/oauth2/v2.0/token";
    const SCOPES = ["offline_access", "User.Read", "Mail.Send", "Mail.ReadWrite"];

    protected function getScopeSeparator()
    {
        return " ";
    }

    protected function getAuthorizationParameters($options)
    {
        $options["prompt"] = "consent";
        return parent::getAuthorizationParameters($options);
    }

    protected function getAccessTokenRequest($params)
    {
        $params["scope"] = implode(" ", self::SCOPES);
        return parent::getAccessTokenRequest($params);
    }

    public static function getSupportedAuthTypes()
    {
        return [\WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2];
    }

    public static function supportsLegacyMailProtocols()
    {
        return false;
    }

    public function clearOpposingAuthData($department)
    {
        $department->host = "";
        $department->login = "";
        $department->port = "";
        $department->password = "";
    }
}
