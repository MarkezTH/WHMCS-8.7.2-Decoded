<?php

namespace WHMCS\Api\ApplicationSupport\ApplicationLinks\GrantType;

class ClientOtp extends \WHMCS\ApplicationLink\GrantType\SingleSignOn
{
    use \WHMCS\Api\ApplicationSupport\ApplicationLinks\ClientUserTrait;

    public function getUserId()
    {
        $uuid = "";
        $client = $this->getClient();
        $user = $this->getUser();
        if ($user) {
            if ($client && !$user->hasAccessToClient($client)) {
                throw new \WHMCS\Exception("SSO authentication blocked for user " . $user->id);
            }
            $uuid = $user->id;
        }
        if ($client) {
            if (!$client->isAllowedToAuthenticate() || !$client->hasSingleSignOnPermission()) {
                throw new \WHMCS\Exception("SSO authentication blocked for client " . $client->id);
            }
            $uuid .= ":" . $client->id;
        }
        return $uuid;
    }
}
