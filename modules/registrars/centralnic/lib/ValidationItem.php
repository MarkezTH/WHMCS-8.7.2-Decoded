<?php

namespace WHMCS\Module\Registrar\CentralNic;

class ValidationItem
{
    protected $name = "";
    protected $value = NULL;
    protected $rule = "";
    protected $assertionMessage = "";

    public function __construct($name, $value, $rule)
    {
        $this->name = $name;
        $this->value = $value;
        $this->rule = $rule;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getRule()
    {
        return $this->rule;
    }

    public function getAssertionMessage()
    {
        return $this->assertionMessage;
    }

    public function setAssertionMessage($self, $message)
    {
        $this->assertionMessage = $message;
        return $this;
    }
}
