<?php

namespace WHMCS\Payment\Contracts;

interface PayMethodAdapterInterface extends \WHMCS\User\Contracts\ContactAwareInterface, PayMethodTypeInterface, SensitiveDataInterface
{
    public function payMethod();

    public static function factoryPayMethod(\WHMCS\User\Contracts\UserInterface $client, \WHMCS\User\Contracts\ContactInterface $billingContact, $description);

    public function getDisplayName();
}
