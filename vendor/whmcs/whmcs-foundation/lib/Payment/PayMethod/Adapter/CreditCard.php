<?php

namespace WHMCS\Payment\PayMethod\Adapter;

class CreditCard extends CreditCardModel
{
    use \WHMCS\Payment\PayMethod\Traits\CreditCardDetailsTrait {
        getRawSensitiveData as ccGetRawSensitiveData;
    }

    public static function boot()
    {
        parent::boot();
        static::saving(function (CreditCard $model) {
            $sensitiveData = $model->getSensitiveData();
            $name = $model->getSensitiveDataAttributeName();
            $model->{$name} = $sensitiveData;
        });
    }

    protected function getRawSensitiveData()
    {
        return $this->ccGetRawSensitiveData();
    }

    public function getDisplayName()
    {
        return implode("-", [$this->card_type, $this->last_four]);
    }
}
