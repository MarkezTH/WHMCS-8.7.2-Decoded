<?php

namespace WHMCS\Api\ApplicationSupport\ApplicationLinks;

trait ClientUserTrait
{
    private $user = NULL;
    private $client = NULL;

    public function setUserClient($user, $client)
    {
        if ($user instanceof \WHMCS\User\User) {
            $this->user = $user;
        }
        if ($client instanceof \WHMCS\User\Client) {
            $this->client = $client;
        }
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getClient()
    {
        return $this->client;
    }
}
