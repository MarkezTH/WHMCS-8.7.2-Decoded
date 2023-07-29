<?php

namespace WHMCS\Mail\Incoming;

trait MailboxOauthTokenTrait
{
    protected $isTest = false;

    protected function getOauth2AccessToken($department)
    {
        $handler = new \WHMCS\Mail\MailAuthHandler();
        $provider = $handler->createProvider($department->mailAuthConfig["service_provider"], $department->mailAuthConfig["oauth2_client_id"], $department->mailAuthConfig["oauth2_client_secret"], \WHMCS\Mail\MailAuthHandler::CONTEXT_SUPPORT_DEPARTMENT);
        $accessToken = $provider->getAccessToken(new \League\OAuth2\Client\Grant\RefreshToken(), ["refresh_token" => $department->mailAuthConfig["oauth2_refresh_token"]]);
        if (!$this->isTest) {
            $updatedRefreshToken = $accessToken->getRefreshToken();
            if (!is_null($updatedRefreshToken)) {
                $department->mailAuthConfig = array_merge($department->mailAuthConfig, ["oauth2_refresh_token" => $updatedRefreshToken]);
                $department->save();
            }
        }
        return $accessToken;
    }
}
