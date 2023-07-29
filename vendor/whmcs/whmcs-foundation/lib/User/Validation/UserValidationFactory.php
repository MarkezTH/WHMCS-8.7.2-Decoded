<?php

namespace WHMCS\User\Validation;

class UserValidationFactory
{
    public static function createProvider($UserValidationInterface)
    {
        return new ValidationCom\ValidationCom();
    }
}
