<?php

namespace WHMCS\Module\Gateway;

class Balance implements BalanceInterface
{
    use CurrencyObjectTrait;
    protected $currencyCode = "";
    protected $amount = 0;
    protected $label = "status.available";
    protected $color = "#5dc560";

    protected function setAmount($BalanceInterface, $amount)
    {
        $this->amount = $amount;
        return $this;
    }

    protected function setColor($BalanceInterface, $color)
    {
        $this->color = "#" . trim($color, "#");
        return $this;
    }

    protected function setCurrencyCode($BalanceInterface, $currencyCode)
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }

    protected function setLabel($BalanceInterface, $label)
    {
        $this->label = $label;
        return $this;
    }

    public static function factory($BalanceInterface, $amount, $currencyCode = NULL, $label = NULL, $color)
    {
        $self = new static();
        return $self->setAmount($amount)->setCurrencyCode($currencyCode)->setLabel($label ?: $self->label)->setColor($color ?: $self->color);
    }

    public function colorCodeAsString()
    {
        switch ($this->color) {
            case "#6ecacc":
                return "color-blue";
                break;
            case "#959595":
                return "color-grey";
                break;
            case "#af5dd5":
                return "color-purple";
                break;
            case "#5dc560":
                return "color-green";
                break;
            case "#eaae53":
                return "color-orange";
                break;
            case "#ea5395":
                return "color-pink";
                break;
            case "#63cfd2":
                return "color-cyan";
                break;
        }
    }

    public function getAmount($Price)
    {
        return new \WHMCS\View\Formatter\Price($this->amount, $this->getCurrencyObject());
    }

    public function getColor()
    {
        return $this->color;
    }

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function getLabel()
    {
        return \AdminLang::trans($this->label);
    }

    public function getRawLabel()
    {
        return $this->label;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return ["amount" => $this->amount, "currencyCode" => $this->currencyCode, "label" => $this->label, "color" => $this->color];
    }

    public static function factoryFromArray($data)
    {
        return static::factory($data["amount"], $data["currencyCode"], $data["label"], $data["color"]);
    }
}
