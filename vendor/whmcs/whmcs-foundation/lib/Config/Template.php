<?php

namespace WHMCS\Config;

class Template
{
    protected $configFile = NULL;
    protected $name = NULL;
    protected $metaData = [];
    protected $properties = [];
    protected $configDefinitions = [];
    protected $config = [];
    protected $provides = [];
    protected $dependencies = [];
    protected $checksum = "";
    const PATTERN_REFERENCE_PARENT = "/^__parent__\$/";
    const PATTERN_REFERENCE_DEFAULT = "/^__default__\$/";
    const PATTERN_ABSOLUTE_URL_PATH = "/^(https:|http:|\\/\\/)/";
    const PATTERN_RELATIVE_SYSTEM_URL_PATH = "/^\\/[^\\/]/";
    const PATTERN_RELATIVE_THEME_URL_PATH = "/^\\*\\//";

    public function __construct(\WHMCS\File $configFile)
    {
        if ($configFile->exists()) {
            $content = $configFile->contents();
            $this->checksum = sha1($content);
            $config = \Symfony\Component\Yaml\Yaml::parse($content);
            $this->configFile = $configFile;
            $this->name = isset($config["name"]) ? $config["name"] : NULL;
            $this->metaData = isset($config["meta"]) ? $config["meta"] : [];
            $this->properties = isset($config["properties"]) ? $config["properties"] : [];
            $this->configDefinitions = isset($config["config-definitions"]) ? $config["config-definitions"] : [];
            $this->config = isset($config["config"]) ? $config["config"] : [];
            $provides = [];
            if (!empty($config["provides"]) && is_array($config["provides"])) {
                $provides = $config["provides"];
            }
            $this->setProvides($provides);
            $deps = [];
            if (!empty($config["dependencies"]) && is_array($config["dependencies"])) {
                $deps = $config["dependencies"];
            }
            $this->setDependencies($deps);
        }
    }

    public function checksum()
    {
        return $this->checksum;
    }

    public function toArray()
    {
        return ["name" => $this->name, "meta" => $this->metaData, "properties" => $this->properties, "config-definitions" => $this->configDefinitions, "config" => $this->config, "provides" => $this->getProvides(), "dependencies" => $this->getDependencies()];
    }

    public function save(\WHMCS\File $saveTo = NULL)
    {
        $yaml = \Symfony\Component\Yaml\Yaml::dump($this->toArray(), 4);
        if (is_null($saveTo)) {
            $this->configFile->create($yaml);
        } else {
            $saveTo->create($yaml);
        }
        $this->checksum = sha1($yaml);
        return $this;
    }

    public function saveTo($path)
    {
        return $this->save(new \WHMCS\File($path));
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperty($key, $value)
    {
        $this->properties[$key] = $value;
        return $this;
    }

    public function getConfigDefinitions()
    {
        return $this->configDefinitions;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig($key, $value)
    {
        if (!array_key_exists($key, $this->configDefinitions)) {
            throw new \WHMCS\Exception("Unknown config key " . $key . ".");
        }
        if (isset($this->configDefinitions[$key]["type"])) {
            switch ($this->configDefinitions[$key]["type"]) {
                case "int":
                case "integer":
                    $value = intval($value);
                    break;
                case "float":
                    $value = floatval($value);
                    break;
                case "bool":
                case "boolean":
                    $value = (bool) $value;
                    break;
                default:
                    $value = trim($value);
            }
        } else {
            $value = trim($value);
        }
        $this->config[$key] = $value;
        return $this;
    }

    public function getProvides()
    {
        return $this->provides;
    }

    public function setProvides($provides)
    {
        $this->provides = $provides;
        return $this;
    }

    public function getDependencies()
    {
        return $this->dependencies;
    }

    public function setDependencies($dependencies)
    {
        $this->dependencies = $dependencies;
        return $this;
    }

    public static function isParent($value)
    {
        return (bool) preg_match(static::PATTERN_REFERENCE_PARENT, $value);
    }

    public static function isDefault($value)
    {
        if (empty($value)) {
            return true;
        }
        return (bool) preg_match(static::PATTERN_REFERENCE_DEFAULT, $value);
    }

    public static function isExternalUrl($value)
    {
        return (bool) preg_match(static::PATTERN_ABSOLUTE_URL_PATH, $value);
    }

    public static function isSystemPath($value)
    {
        return (bool) preg_match(static::PATTERN_RELATIVE_SYSTEM_URL_PATH, $value);
    }

    public function getName()
    {
        return (string) $this->name;
    }
}
