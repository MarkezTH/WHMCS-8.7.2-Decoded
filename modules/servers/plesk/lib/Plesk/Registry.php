<?php

class Plesk_Registry
{
    private $_instances = [];
    private static $_instance = NULL;

    public static function getInstance($Plesk_Registry)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __get($name)
    {
        if (isset($this->_instances[$name])) {
            return $this->_instances[$name];
        }
        throw new Exception("There is no object \"" . $name . "\" in the registry.");
    }

    public function __set($name, $value)
    {
        $this->_instances[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->_instances[$name]);
    }
}
