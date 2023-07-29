<?php

namespace WHMCS\Service\Ssl;

abstract class ValidationMethod
{
    protected $method = NULL;
    public abstract function populate($values);
    public abstract function methodNameConstant();
    public abstract function friendlyName();
    public abstract function translationKey($language);
    public abstract function defaults();

    public function __construct()
    {
        $this->method = $this->methodNameConstant();
    }

    public static function factory($method)
    {
        $expectedClass = "WHMCS\\Service\\Ssl\\ValidationMethod" . ucfirst($method);
        if (!class_exists($expectedClass)) {
            throw new \WHMCS\Exception("Unknown method");
        }
        return new $expectedClass();
    }

    public static function factoryFromPacked($self, $value)
    {
        $unpacked = static::unpack($value);
        $method = static::sanitizeMethodIdentifier($unpacked->method ?? "");
        if (strlen($method) == 0) {
            throw new \WHMCS\Exception("Indistinguishable method");
        }
        return static::factory($method)->populate($unpacked);
    }

    public function is($methodConstant)
    {
        return $this->method === $methodConstant;
    }

    public static function sanitizeMethodIdentifier($ident)
    {
        return substr(preg_replace("/[^[:alpha:]]/", "", $ident), 0, 12);
    }

    public static function unpack($packed)
    {
        if (empty($packed)) {
            throw new \WHMCS\Exception("Nothing to unpack");
        }
        $unpacked = json_decode($packed);
        if (!is_null($unpacked) && json_last_error() === JSON_ERROR_NONE) {
            return $unpacked;
        }
        throw new \WHMCS\Exception("Failed to unpack");
    }

    public function pack()
    {
        $objectClassProperties = [];
        foreach (get_class_vars(get_class($this)) as $property => $value) {
            if ($this->{$property} !== NULL) {
                $objectClassProperties[$property] = $this->{$property};
            }
        }
        return json_encode((object) $objectClassProperties);
    }

    public function populateFromClassProperties($values)
    {
        $properties = array_keys(get_class_vars(get_class($this)));
        foreach ($properties as $property) {
            $value = NULL;
            if (property_exists($values, $property)) {
                $value = $values->{$property};
            }
            $this->{$property} = $value;
        }
        return $this;
    }
}
