<?php

namespace WHMCS\Billing\Tax;

class Vat
{
    private $apiUrl = "https://api1.whmcs.com";
    const EU_COUNTRIES = ["AT" => 20, "BE" => 21, "BG" => 20, "HR" => 25, "CY" => 19, "CZ" => 21, "DK" => 25, "EE" => 20, "FI" => 24, "FR" => 20, "DE" => 19, "GR" => 24, "HU" => 27, "IE" => 23, "IT" => 22, "LV" => 21, "LT" => 21, "LU" => 17, "MT" => 18, "NL" => 21, "PL" => 23, "PT" => 23, "RO" => 19, "ES" => 21, "SE" => 25, "SK" => 20, "SI" => 22, "GB" => 20];

    public static function validateNumber($vatNumber)
    {
        if (!static::isValidFormat($vatNumber)) {
            return false;
        }
        if (\WHMCS\Config\Setting::getValue("TaxEUTaxValidation")) {
            return self::sendValidateTaxNumber($vatNumber);
        }
        return true;
    }

    public static function validateNumberSilently($vatNumber)
    {
        try {
            return static::validateNumber($vatNumber);
        } catch (\Throwable $e) {
        }
        return false;
    }

    public static function isValidFormat($vatIdentifier)
    {
        if (ctype_digit($vatIdentifier)) {
            $length = strlen($vatIdentifier);
            return $length === 9 || $length === 12;
        }
        list($prefix) = static::explode($vatIdentifier);
        $prefix = preg_replace("/[^A-Za-z]/", "", $prefix);
        return strlen($prefix) == 2;
    }

    public static function explode($vatIdentifier)
    {
        return [substr($vatIdentifier, 0, 2), substr($vatIdentifier, 2)];
    }

    public static function asObject($vatIdentifier)
    {
        list($prefix, $number) = static::explode($vatIdentifier);
        return (object) ["prefix" => $prefix, "number" => $number, "original" => $vatIdentifier];
    }

    public static function setTaxExempt(\WHMCS\User\Client &$client)
    {
        $exempt = false;
        $taxId = $client->taxId;
        if (Vat::getFieldName() !== "tax_id") {
            $customFieldId = (int) \WHMCS\Config\Setting::getValue("TaxVatCustomFieldId");
            $taxId = $client->customFieldValues()->where("fieldid", $customFieldId)->value("value");
        }
        if (\WHMCS\Config\Setting::getValue("TaxEUTaxExempt") && $taxId) {
            $validNumber = static::validateNumberSilently($taxId);
            if ($validNumber && in_array($client->country, array_keys(self::EU_COUNTRIES))) {
                $exempt = true;
                if (\WHMCS\Config\Setting::getValue("TaxEUHomeCountryNoExempt") && $client->country == \WHMCS\Config\Setting::getValue("TaxEUHomeCountry")) {
                    $exempt = false;
                }
            }
            $client->taxExempt = $exempt;
            self::removeSessionData($taxId);
        }
        return $exempt;
    }

    protected static function assertSoapSupported()
    {
        if (!\WHMCS\Environment\Php::isClassAvailable("SoapClient")) {
            throw new \WHMCS\Exception("The PHP SOAP extension is required to perform EU VAT number verification.");
        }
    }

    public function initiateInvoiceNumberingReset()
    {
        $resetFrequency = \WHMCS\Config\Setting::getValue("TaxAutoResetNumbering");
        if ($resetFrequency) {
            $this->resetInvoiceNumbering("TaxNextCustomInvoiceNumber", $resetFrequency);
        }
        $resetFrequency = \WHMCS\Config\Setting::getValue("TaxAutoResetPaidNumbering");
        if ($resetFrequency) {
            $this->resetInvoiceNumbering("SequentialInvoiceNumberValue", $resetFrequency);
        }
    }

    protected function resetInvoiceNumbering($key, $resetFrequency)
    {
        $resetKey = $key . "ResetTimestamp";
        try {
            $lastResetDate = \WHMCS\Carbon::createFromFormat("Y-m-d H:i:s", \WHMCS\Config\Setting::getValue($resetKey) . "-01 00:00:00");
        } catch (\Exception $e) {
            $lastResetDate = \WHMCS\Carbon::today();
            \WHMCS\Config\Setting::setValue($resetKey, $lastResetDate->format("Y-m"));
        }
        if ($resetFrequency == "monthly" && $lastResetDate->format("Y-m") != \WHMCS\Carbon::today()->format("Y-m") || $resetFrequency == "annually" && $lastResetDate->format("Y") != \WHMCS\Carbon::today()->format("Y")) {
            \WHMCS\Config\Setting::setValue($resetKey, \WHMCS\Carbon::today()->format("Y-m"));
            \WHMCS\Config\Setting::setValue($key, 1);
        }
    }

    protected static function sendValidateTaxNumber($vatNumber)
    {
        $vatNumber = strtoupper($vatNumber);
        $vatNumber = preg_replace("/[^A-Z0-9]/", "", $vatNumber);
        $existingSessionValidation = \WHMCS\Session::get("TaxCodeValidation");
        $valid = false;
        if ($existingSessionValidation) {
            $existingSessionValidation = json_decode(decrypt($existingSessionValidation), true);
        }
        if (!is_array($existingSessionValidation)) {
            $existingSessionValidation = [];
        }
        if (array_key_exists($vatNumber, $existingSessionValidation)) {
            return $existingSessionValidation[$vatNumber];
        }
        $vat = static::asObject($vatNumber);
        try {
            if (is_numeric($vatNumber) || $vat->prefix === "GB") {
                $valid = (new \WHMCS\Billing\VAT\HMRC())->validate(!is_numeric($vat->prefix) ? $vat->number : $vatNumber);
            } else {
                $valid = static::verifyEUVATNumber($vat->prefix, $vat->number);
            }
            $existingSessionValidation[$vatNumber] = $valid;
            \WHMCS\Session::set("TaxCodeValidation", encrypt(json_encode($existingSessionValidation)));
        } catch (\Exception $e) {
            logActivity("Tax Code Check Failure - " . $vatNumber . " - " . $e->getMessage());
            throw \WHMCS\Exception\ServiceUnavailable::factory("tax-code", $e);
        }
        return (bool) $valid;
    }

    protected static function verifyEUVATNumber($memberPrefix, $number)
    {
        static::assertSoapSupported();
        $valid = false;
        try {
            $response = static::getEUViesClient()->checkVat(["countryCode" => $memberPrefix, "vatNumber" => $number]);
            $valid = $response->valid;
        } catch (\SoapFault $e) {
            if ($e->faultstring != "INVALID_INPUT") {
                throw $e;
            }
        }
        return $valid;
    }

    public static function getEUViesClient($SoapClient)
    {
        return new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl", ["connection_timeout" => 5]);
    }

    protected static function removeSessionData($vatNumber)
    {
        $vatNumber = strtoupper($vatNumber);
        $vatNumber = preg_replace("/[^A-Z0-9]/", "", $vatNumber);
        $existingSessionValidation = \WHMCS\Session::get("TaxCodeValidation");
        if ($existingSessionValidation) {
            $existingSessionValidation = json_decode(decrypt($existingSessionValidation), true);
        }
        if (!is_array($existingSessionValidation)) {
            $existingSessionValidation = [];
        }
        if (array_key_exists($vatNumber, $existingSessionValidation)) {
            unset($existingSessionValidation[$vatNumber]);
        }
        \WHMCS\Session::set("TaxCodeValidation", encrypt(json_encode($existingSessionValidation)));
    }

    public static function getLabel($prefix = "tax")
    {
        $key = "taxLabel";
        if (\WHMCS\Config\Setting::getValue("TaxVATEnabled")) {
            $key = "vatLabel";
        }
        if ($prefix) {
            $key = $prefix . "." . $key;
        }
        return $key;
    }

    public static function getFieldName($contact = false)
    {
        $field = "tax_id";
        $customFieldId = (int) \WHMCS\Config\Setting::getValue("TaxVatCustomFieldId");
        if ($customFieldId && !$contact) {
            $field = "customfield[" . $customFieldId . "]";
        }
        return $field;
    }

    public static function isUsingNativeField($contact = false)
    {
        return self::isTaxEnabled() && self::isTaxIdEnabled() && self::getFieldName($contact) == "tax_id";
    }

    public static function isTaxIdEnabled()
    {
        $isTaxIDDisabled = \WHMCS\Config\Setting::getValue("TaxIDDisabled");
        if (is_null($isTaxIDDisabled)) {
            $isTaxIDDisabled = true;
        }
        return !$isTaxIDDisabled;
    }

    public static function isTaxIdDisabled()
    {
        $isTaxIDDisabled = \WHMCS\Config\Setting::getValue("TaxIDDisabled");
        if (is_null($isTaxIDDisabled)) {
            $isTaxIDDisabled = true;
        }
        return $isTaxIDDisabled;
    }

    public static function isTaxEnabled()
    {
        return (bool) \WHMCS\Config\Setting::getValue("TaxEnabled");
    }

    public function getVatRates()
    {
        try {
            $data = $this->getVatRatesFromApi();
            if ($data->getStatusCode() !== 200) {
                throw new \WHMCS\Exception("API Call Failed.");
            }
            $rates = json_decode($data->getBody()->getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($rates)) {
                $rates = self::EU_COUNTRIES;
            }
        } catch (\Exception $e) {
            $rates = self::EU_COUNTRIES;
        }
        return $rates;
    }

    public function persistVatRates($rates = "VAT", $vatLabel)
    {
        $return = true;
        try {
            foreach ($rates as $countryCode => $countryData) {
                if (!(is_array($countryData) && (!isset($countryData["rate"]) || !is_numeric($countryData["rate"]) || $countryData["rate"] < 0))) {
                    $rate = is_array($countryData) ? $rate = $countryData["rate"] : $countryData;
                    \WHMCS\Database\Capsule::table("tbltax")->updateOrInsert(["country" => $countryCode, "state" => "", "level" => 1], ["taxrate" => $rate, "name" => $vatLabel]);
                }
            }
        } catch (\Exception $e) {
            $return = false;
        }
        return $return;
    }

    protected function getVatRatesBaseUri()
    {
        return $this->apiUrl;
    }

    private function getGuzzleClient($HttpClient, $exceptions)
    {
        return new \WHMCS\Http\Client\HttpClient(["base_uri" => $this->getVatRatesBaseUri(), \GuzzleHttp\RequestOptions::HTTP_ERRORS => $exceptions]);
    }

    protected function getVatRatesFromApi()
    {
        return $this->getGuzzleClient(true)->request("GET", "/feeds/vatrates");
    }
}
