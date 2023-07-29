<?php

namespace WHMCS\Payment\Filter;

class Name extends AbstractFilter
{
    private $acceptableName = [];

    public function __construct($name)
    {
        if (!is_array($name)) {
            $name = [$name];
        }
        $this->acceptableName = $name;
    }

    public function filter(\WHMCS\Payment\Adapter\AdapterInterface $adapter)
    {
        $name = $adapter->getName();
        if (in_array($name, $this->acceptableName)) {
            return true;
        }
        return false;
    }
}
