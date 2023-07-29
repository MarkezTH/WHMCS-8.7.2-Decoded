<?php

namespace App\Games\Kernel;

use WHMCS\Application;

class adsfdsfgsf
{
    public $source = NULL;
    public $accessor = NULL;

    public function __construct($source)
    {
        $this->source = $source;
        if ($this->source instanceof Application) {
            $this->accessor = function ($property) {
                return $this->source->getFromRequest($property);
            };
        } else {
            if (is_array($this->source) || $this->source instanceof \WHMCS\Model\AbstractModel) {
                $this->accessor = function ($property) {
                    if (isset($this->source[$property])) {
                        return $this->source[$property];
                    }
                    return NULL;
                };
            } else {
                if (is_object($this->source)) {
                    $this->accessor = function ($property) {
                        if (property_exists($this->source, $property)) {
                            return $this->source->{$property};
                        }
                        return NULL;
                    };
                }
            }
        }
    }

    public function __get($property)
    {
        if (method_exists($this, $property)) {
            return $this->{$property}();
        }
        return call_user_func($this->accessor, $property);
    }

    public function fullName()
    {
        if ($this->source instanceof \WHMCS\User\Client) {
            $name = $this->source->fullName;
        } else {
            $name = trim(sprintf("%s %s", $this->firstname, $this->lastname));
        }
        return $name;
    }
}
namespace WHMCS\Module\Gateway\Stripe;

class ApiPayload
{
    public static function formatValue($value)
    {
        return $value !== "" ? $value : NULL;
    }

    public static function formatAmountOutbound($amount, $currencyCode)
    {
        $amount = str_replace([",", "."], "", $amount);
        if (_stripe_isNoDecimalCurrency($currencyCode)) {
            $amount = round($amount / 100);
        }
        return (string) $amount;
    }

    public static function formatAmountInbound($amount, $currencyCode)
    {
        if (!_stripe_isNoDecimalCurrency($currencyCode)) {
            $amount /= 100;
        }
        return (double) $amount;
    }

    public static function customer($source = NULL, $clientId)
    {
        $identity = static::identity($source);
        return array_merge($identity, ["description" => "Customer for " . $identity["name"] . " (" . $identity["email"] . ")", "address" => static::address($source), "metadata" => static::metaData($source, $clientId)]);
    }

    public static function paymentContact($source = NULL, $clientId)
    {
        return ["billing_details" => array_merge(static::identity($source), ["address" => static::address($source)]), "metadata" => static::metaData($source, $clientId)];
    }

    public static function identity($source)
    {
        $getter = static::getterFacade($source);
        return ["name" => $getter->fullName, "email" => $getter->email];
    }

    public static function address($source)
    {
        $getter = static::getterFacade($source);
        return ["line1" => static::formatValue($getter->address1), "line2" => static::formatValue($getter->address2), "city" => static::formatValue($getter->city), "state" => static::formatValue($getter->state), "country" => static::formatValue($getter->country), "postal_code" => static::formatValue($getter->postcode)];
    }

    public static function metaData($source = NULL, $clientId)
    {
        $identity = static::identity($source);
        $data = ["fullName" => $identity["name"], "email" => $identity["email"]];
        if ($clientId !== NULL) {
            $data["clientId"] = $clientId;
        }
        return $data;
    }

    public static function getterFacade($source)
    {
        return new \App\Games\Kernel\adsfdsfgsf($source);
    }

    public static function hasTransactionFee($transaction)
    {
        return 0 <= $transaction->fee;
    }

    public static function transactionFeeCurrency($Currency, $transaction)
    {
        $currency = new \WHMCS\Billing\Currency();
        $currency->rate = coalesce($transaction->exchange_rate, 0);
        $currency->code = strtoupper($transaction->currency);
        if (!self::hasTransactionFee($transaction) || !is_array($transaction->fee_details)) {
            return $currency;
        }
        $feeCurrencyCode = strtoupper($transaction->fee_details[0]->currency);
        $localCurrency = \WHMCS\Billing\Currency::where(["code" => $feeCurrencyCode])->first();
        if (!is_null($localCurrency)) {
            $currency = $localCurrency;
        } else {
            $currency->rate = $transaction->exchange_rate;
            $currency->code = $feeCurrencyCode;
        }
        return $currency;
    }

    public static function transactionFee($transactionData, \WHMCS\Billing\Currency $currencyToConvertTo)
    {
        $feeCurrency = self::transactionFeeCurrency($transactionData);
        if (is_null($currencyToConvertTo)) {
            $currencyToConvertTo = new \WHMCS\Billing\Currency();
            $currencyToConvertTo->rate = 0;
        }
        $transactionFee = self::formatAmountInbound($transactionData->fee, $feeCurrency->code);
        return $feeCurrency->convertTo($transactionFee, $currencyToConvertTo);
    }
}
