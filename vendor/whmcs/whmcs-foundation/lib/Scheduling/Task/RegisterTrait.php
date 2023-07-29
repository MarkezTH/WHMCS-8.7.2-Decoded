<?php

namespace WHMCS\Scheduling\Task;

trait RegisterTrait
{
    protected $outputInstances = [];
    protected $details = ["success" => [], "failure" => []];

    public function output($key)
    {
        $namespaceKey = $this->getNamespace() . "." . $key;
        if (empty($this->outputInstances[$key])) {
            $outputKeys = $this->getOutputKeys();
            $friendlyName = isset($outputKeys[$key]["name"]) ? $outputKeys[$key]["name"] : $key;
            $defaultValue = isset($outputKeys[$key]["defaultValue"]) ? $outputKeys[$key]["defaultValue"] : 0;
            $output = new \WHMCS\Log\Register();
            $output->setNamespace($namespaceKey);
            $output->setName($friendlyName);
            $output->setNamespaceId($this->id);
            $output->setValue($defaultValue);
            $this->outputInstances[$key] = $output;
        }
        return $this->outputInstances[$key];
    }

    public function getNamespace()
    {
        if (method_exists($this, "getSystemName")) {
            return $this->getSystemName();
        }
        $classname = static::class;
        $namespaces = explode("\\", $classname);
        return array_pop($namespaces);
    }

    public function getLatestOutputs($outputKeys = [])
    {
        if (empty($outputKeys)) {
            $namespaceKeys = array_keys($this->getOutputKeys());
        } else {
            $namespaceKeys = $outputKeys;
        }
        $namespace = $this->getNamespace();
        $applyNamespace = function ($value) use($namespace) {
            return $namespace . "." . $value;
        };
        $namespaces = array_map($applyNamespace, $namespaceKeys);
        return (new \WHMCS\Log\Register())->latestByNamespaces($namespaces, $this->id);
    }

    public function getOutputsSince(\WHMCS\Carbon $since, $outputKeys = [])
    {
        if (empty($outputKeys)) {
            $namespaceKeys = array_keys($this->getOutputKeys());
        } else {
            $namespaceKeys = $outputKeys;
        }
        $namespace = $this->getNamespace();
        $applyNamespace = function ($value) use($namespace) {
            return $namespace . "." . $value;
        };
        $namespaces = array_map($applyNamespace, $namespaceKeys);
        return (new \WHMCS\Log\Register())->sinceByNamespace($since, $namespaces, $this->id);
    }

    public function addSuccess($data)
    {
        if (!array_key_exists("success", $this->details)) {
            $this->details["success"] = [];
        }
        $this->details["success"][] = $data;
        return $this;
    }

    public function getSuccesses()
    {
        if (!array_key_exists("success", $this->details)) {
            $this->details["success"] = [];
        }
        return $this->details["success"];
    }

    public function addFailure($data)
    {
        if (!array_key_exists("failure", $this->details)) {
            $this->details["failure"] = [];
        }
        $this->details["failure"][] = $data;
        return $this;
    }

    public function getFailures()
    {
        if (!array_key_exists("failure", $this->details)) {
            $this->details["failure"] = [];
        }
        return $this->details["failure"];
    }

    public function addCustom($type, $data)
    {
        if (!array_key_exists($type, $this->details)) {
            $this->details[$type] = [];
        }
        $this->details[$type][] = $data;
        return $this;
    }

    public function getCustom($type)
    {
        if (!array_key_exists($type, $this->details)) {
            $this->details[$type] = [];
        }
        return $this->details[$type];
    }

    public function getDetail()
    {
        return $this->details;
    }

    public function setDetails($details)
    {
        $this->details = $details;
        return $this;
    }
    public abstract function getOutputKeys();
}
