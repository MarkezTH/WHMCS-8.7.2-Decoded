<?php

namespace WHMCS\View\Admin\HealthCheck;

class ApplicationDirectory
{
    public $friendlyName = NULL;
    public $currentPath = NULL;
    public $defaultPath = NULL;
    protected $filesystem = NULL;
    protected $when = NULL;
    protected $ignoreConcerns = [];

    public function __construct($currentPath)
    {
        $this->currentPath = $currentPath;
        $this->filesystem = new \WHMCS\File\Filesystem(new \League\Flysystem\Adapter\Local(dirname($currentPath)));
        $this->when = ["missing" => NULL, "not-writable" => NULL];
    }

    public function default($self, $path)
    {
        $this->defaultPath = $path;
        return $this;
    }

    public function name($self, $name)
    {
        $this->friendlyName = $name;
        return $this;
    }

    public function whenMissing($self, $callable)
    {
        $this->when["missing"] = $callable;
        return $this;
    }

    public function whenNotWritable($self, $callable)
    {
        $this->when["not-writable"] = $callable;
        return $this;
    }

    public function ignoreConcerns($self, $circumstances)
    {
        $this->ignoreConcerns = array_flip($circumstances);
        return $this;
    }

    public function isConcern($circumstance)
    {
        return !isset($this->ignoreConcerns[$circumstance]);
    }

    public function isDefault()
    {
        return $this->currentPath === $this->defaultPath;
    }

    public function exists()
    {
        return $this->filesystem->has(basename($this->currentPath));
    }

    public function writable()
    {
        return is_writable($this->currentPath);
    }

    public function invokeMissing()
    {
        return $this->invoke("missing");
    }

    public function invokeNotWritable()
    {
        return $this->invoke("not-writable");
    }

    public function invoke($circumstance)
    {
        if (is_callable($this->when[$circumstance])) {
            return call_user_func($this->when[$circumstance], $this);
        }
        return $this->defaultDisplay();
    }

    public function defaultDisplay()
    {
        return sprintf("%s (%s)", $this->friendlyName, $this->currentPath);
    }
}
