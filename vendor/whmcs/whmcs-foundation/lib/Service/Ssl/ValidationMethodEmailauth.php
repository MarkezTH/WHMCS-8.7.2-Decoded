<?php

namespace WHMCS\Service\Ssl;

class ValidationMethodEmailauth extends ValidationMethod
{
    public $email = NULL;

    public function methodNameConstant()
    {
        return \WHMCS\Service\Ssl::DOMAIN_VALIDATION_EMAIL;
    }

    public function friendlyName()
    {
        return "Email";
    }

    public function translationKey($language)
    {
        if ($language instanceof \WHMCS\Language\AdminLanguage) {
            return "wizard.ssl.emailMethod";
        }
        return "ssl.emailMethod";
    }

    public function populate($values)
    {
        return $this->populateFromClassProperties($values);
    }

    public function defaults()
    {
        return $this;
    }
}
