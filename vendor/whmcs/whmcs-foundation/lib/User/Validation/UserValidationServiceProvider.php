<?php

namespace WHMCS\User\Validation;

class UserValidationServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider
{
    public function register()
    {
        $this->app->singleton("userValidation", function () {
            return UserValidationFactory::createProvider();
        });
    }
}
