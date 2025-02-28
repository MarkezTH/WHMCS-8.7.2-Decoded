<?php

namespace WHMCS\Utility\Bootstrap;

class Installer extends AbstractBootstrap
{
    protected static function checkBareMinimumRequirements()
    {
        if (!\WHMCS\Environment\Php::functionEnabled("fopen")) {
            throw new \WHMCS\Exception\Fatal("The fopen() PHP function is disabled. This application cannot continue.");
        }
    }

    public static function boot(\WHMCS\Config\RuntimeStorage $preBootInstances = NULL)
    {
        $container = parent::boot();
        static::defineClassAliases(static::getAliases());
        $container->bind("terminus", function () {
            return \WHMCS\Terminus::getInstance();
        });
        $instances = static::getInstances();
        if ($preBootInstances) {
            $errMgmt = $preBootInstances->errorManagement;
            if ($errMgmt instanceof \WHMCS\Utility\ErrorManagement) {
                $instances["ErrorManagement"] = $errMgmt;
            }
        }
        $container->bind("config", function () {
            $config = new \WHMCS\Config\Application();
            $config->loadConfigFile(ROOTDIR . DIRECTORY_SEPARATOR . \WHMCS\Config\Application::WHMCS_DEFAULT_CONFIG_FILE);
            return $config;
        });
        static::bindInstances($container, $instances);
        static::bindSingletons($container, static::getSingletons());
        static::registerServices($container, static::getServices());
        return $container;
    }

    public static function getAliases()
    {
        return ["\\WHMCS\\Application\\Support\\Facades\\Auth" => "Auth", "\\WHMCS\\Application\\Support\\Facades\\Log" => "Log", "WHMCS\\Application\\Support\\Facades\\Hook" => "Hook", "WHMCS\\Application\\Support\\Facades\\HookMgr" => "HookMgr"];
    }

    public static function getInstances()
    {
        return ["ErrorManagement" => new \WHMCS\Utility\ErrorManagement(\WHMCS\Utility\ErrorManagement::factoryRunner())];
    }

    public static function getSingletons()
    {
        return ["auth" => function () {
            return new \WHMCS\Authentication\AuthManager();
        }, "db" => function () {
            return new \WHMCS\Database(\DI::make("config"));
        }, "mysqlCompat" => function () {
            return new \WHMCS\Database\MysqlCompat(\DI::make("db")->getPdo());
        }, "runtimeStorage" => function () {
            return new \WHMCS\Config\RuntimeStorage();
        }];
    }

    public static function getServices()
    {
        return ["\\WHMCS\\Installer\\LogServiceProvider", "WHMCS\\Hook\\HookServiceProvider"];
    }
}
