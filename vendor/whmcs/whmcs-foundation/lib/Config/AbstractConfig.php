<?php

namespace WHMCS\Config;

#[\AllowDynamicProperties]
abstract class AbstractConfig extends \ArrayObject
{
    private $defaultValue = "";

    public function __construct($data = [])
    {
        parent::setFlags(parent::ARRAY_AS_PROPS);
        parent::__construct($data);
    }

    public function setData($data)
    {
        $this->exchangeArray($data);
        return $this;
    }

    public function getData()
    {
        return $this->getArrayCopy();
    }

    public function setDefaultReturnValue($value)
    {
        $this->defaultValue = $value;
    }

    public function offsetGet($property)
    {
        if ($this->offsetExists($property)) {
            return parent::offsetGet($property);
        }
        return $this->defaultValue;
    }
}
