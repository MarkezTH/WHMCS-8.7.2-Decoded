<?php

namespace WHMCS\Payment\Contracts;

interface PayMethodInterface extends \WHMCS\User\Contracts\ContactAwareInterface, PayMethodTypeInterface
{
    public function payment();

    public function isDefaultPayMethod();

    public function setAsDefaultPayMethod();

    public function getDescription();

    public function setDescription($value);

    public function getGateway();

    public function setGateway(\WHMCS\Module\Gateway $value);

    public function isUsingInactiveGateway();

    public function getPaymentDescription();

    public function save($options);
}
