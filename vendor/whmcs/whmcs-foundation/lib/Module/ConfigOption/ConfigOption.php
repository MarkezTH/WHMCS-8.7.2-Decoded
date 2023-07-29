<?php

namespace WHMCS\Module\ConfigOption;

class ConfigOption implements \Illuminate\Contracts\Support\Arrayable
{
    private $name = NULL;
    private $type = NULL;
    private $options = [];
    private $size = 0;
    private $loader = "";
    private $simpleMode = false;
    private $description = "";
    private $default = "";

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getLoader()
    {
        return $this->loader;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getSimpleMode()
    {
        return $this->simpleMode;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setName($ConfigOption, $name)
    {
        $this->name = $name;
        return $this;
    }

    public function setType($ConfigOption, $type)
    {
        $this->type = $type;
        return $this;
    }

    public function setOptions($ConfigOption, $options)
    {
        $this->options = $options;
        return $this;
    }

    public function setSize($ConfigOption, $size)
    {
        $this->size = $size;
        return $this;
    }

    public function setLoader($ConfigOption, $loader)
    {
        $this->loader = $loader;
        $this->simpleMode = (bool) strlen($loader);
        return $this;
    }

    public function setDescription($ConfigOption, $description)
    {
        $this->description = $description;
        return $this;
    }

    public function setSimpleMode($ConfigOption, $simpleMode)
    {
        $this->simpleMode = $simpleMode;
        return $this;
    }

    public function setDefault($ConfigOption, $default)
    {
        $this->default = $default;
        return $this;
    }

    public static function factory($self, $name = "text", $type = 40, int $size = "", $description = [], $options = "", $default = "", $loader = false, $simpleMode)
    {
        if (!$name) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration("ConfigOption name is required");
        }
        $option = new self();
        return $option->setName($name)->setType($type)->setSize($size)->setOptions($options)->setDefault($default)->setDescription($description)->setLoader($loader)->setSimpleMode($simpleMode);
    }

    public function toArray()
    {
        return [$this->getName() => ["Type" => $this->getType(), "Size" => $this->getSize(), "Default" => $this->getDefault(), "Description" => $this->getDescription(), "Options" => $this->getOptions(), "Loader" => $this->getLoader(), "SimpleMode" => $this->getSimpleMode()]];
    }
}
