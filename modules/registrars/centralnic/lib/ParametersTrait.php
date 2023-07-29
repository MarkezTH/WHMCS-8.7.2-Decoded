<?php

namespace WHMCS\Module\Registrar\CentralNic;

trait ParametersTrait
{
    public function getParam($key, $default = "")
    {
        if ($this->hasParam($key)) {
            return $this->params[$key];
        }
        return $default;
    }

    public function getParamArray($key)
    {
        return (array) $this->getParam($key, []);
    }

    public function getParamString($key)
    {
        return (string) $this->getParam($key);
    }

    public function getParamFloat($key)
    {
        return (double) $this->getParam($key, 0);
    }

    public function getParamInt($key)
    {
        return (int) $this->getParam($key, 0);
    }

    public function hasParam($key)
    {
        return isset($this->params[$key]);
    }

    public function isParamEnabled($key)
    {
        return $this->isEnabled($this->getParam($key));
    }

    public function isEnabled($value)
    {
        return $value == "on" || $value == "1" || $value;
    }

    public function getArrayValueArray($key, $array)
    {
        return (array) $this->getArrayValue($key, $array, []);
    }

    public function getArrayValueString($key, $array)
    {
        return (string) $this->getArrayValue($key, $array);
    }

    public function getArrayValue($key, $array, $default = "")
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        return $default;
    }
}
