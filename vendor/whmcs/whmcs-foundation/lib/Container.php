<?php

namespace WHMCS;

class Container extends \Illuminate\Container\Container implements \Interop\Container\ContainerInterface
{
    protected $serviceProviders = [];

    public function environment()
    {
        return "";
    }

    public function isDownForMaintenance()
    {
        return Config\Setting::getValue("MaintenanceMode") || Installer\Update\Updater::isAutoUpdateInProgress();
    }

    public function call($callback, $parameters = [], $defaultMethod = NULL)
    {
        if (is_object($callback) && !$callback instanceof \Closure && is_callable($callback)) {
            return $callback(...$parameters);
        }
        return parent::call($callback, $parameters, $defaultMethod);
    }

    public function register($serviceProvider)
    {
        if (is_string($serviceProvider)) {
            $className = $serviceProvider;
        } else {
            $className = get_class($serviceProvider);
        }
        if (array_key_exists($className, $this->serviceProviders)) {
            return $this->serviceProviders[$className];
        }
        if (is_string($serviceProvider)) {
            $serviceProvider = new $className($this);
            if (!method_exists($serviceProvider, "register")) {
                throw new \RuntimeException("Service Provider " . $className . " must implement the 'register' method");
            }
            $serviceProvider->register();
        }
        $this->serviceProviders[$className] = $serviceProvider;
        return $serviceProvider;
    }

    public function get($id)
    {
        if (!$this->has($id)) {
            if (!class_exists($id)) {
                throw new Exception\Container\IdentifierNotDefined("Invalid Identifier " . $id);
            }
            try {
                $instance = new $id();
            } catch (\Exception $e) {
                throw new Exception\Container\NotBuildable("Could not construct " . $id);
            }
        } else {
            try {
                $instance = $this->make($id);
            } catch (\Exception $e) {
                throw new Exception\Container\NotBuildable("Could not construct container reference " . $id);
            }
        }
        return $instance;
    }

    protected function normalize($service)
    {
        return is_string($service) ? ltrim($service, "\\") : $service;
    }

    public function has($id)
    {
        $normalized = $this->normalize($id);
        $bindings = $this->getBindings();
        return isset($bindings[$normalized]) || $this->isAlias($id);
    }
}
