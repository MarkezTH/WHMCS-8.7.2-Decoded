<?php

namespace WHMCS\Payment\Contracts;

interface RemoteTokenDetailsInterface
{
    public function getRemoteToken();

    public function setRemoteToken($value);

    public function createRemote();

    public function updateRemote();

    public function deleteRemote();

    public function getBillingContactParamsForRemoteCall(\WHMCS\User\Contracts\UserInterface $client, \WHMCS\User\Contracts\ContactInterface $contact);
}
