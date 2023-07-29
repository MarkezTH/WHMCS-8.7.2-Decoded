<?php

namespace WHMCS\Utility;

class Phone
{
    protected $countryCode = NULL;
    protected $phoneNumberOrig = NULL;
    protected $phoneCc = NULL;
    protected $phoneNumber = NULL;
    protected $phoneFormatted = NULL;

    public function __construct($phoneNumber, $countryCode = NULL)
    {
        $this->setCountryCode($countryCode)->setPhoneNumberOrig($phoneNumber)->formatNumber();
    }

    protected function setPhoneNumberOrig($phoneNumber)
    {
        $this->phoneNumberOrig = $phoneNumber;
        return $this;
    }

    protected function setCountryCode($countryCode)
    {
        if (empty($countryCode)) {
            $countryCode = \WHMCS\Config\Setting::getValue("DefaultCountry");
        }
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getTelephoneNumber()
    {
        return \WHMCS\Config\Setting::getValue("PhoneNumberDropdown") ? $this->phoneFormatted : $this->phoneNumberOrig;
    }

    protected function formatNumber()
    {
        $phoneUnformatted = trim($this->phoneNumberOrig);
        $phonePrefix = "";
        if (substr($phoneUnformatted, 0, 1) == "+") {
            $phoneParts = explode(".", ltrim($phoneUnformatted, "+"), 2);
            if (count($phoneParts) == 2) {
                list($phonePrefix, $phoneNumber) = $phoneParts;
            } else {
                $phoneNumber = $phoneParts[0];
            }
        } else {
            $phoneNumber = $phoneUnformatted;
        }
        $phonePrefix = preg_replace("/[^0-9]/", "", $phonePrefix);
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
        $countries = new Country();
        if (!$phonePrefix) {
            $phonePrefix = $countries->getCallingCode($this->countryCode);
        }
        $trimmedPhoneNumber = $phoneNumber;
        if ($phonePrefix != $countries->getCallingCode("IT")) {
            $trimmedPhoneNumber = ltrim($trimmedPhoneNumber, "0");
        }
        $fullyFormattedPhoneNumber = $phonePrefix ? "+" . $phonePrefix . "." . $trimmedPhoneNumber : $phoneNumber;
        $this->phoneCc = $phonePrefix;
        $this->phoneNumber = $phoneNumber;
        $this->phoneFormatted = $phoneNumber ? $fullyFormattedPhoneNumber : $phoneNumber;
        return $this;
    }
}
