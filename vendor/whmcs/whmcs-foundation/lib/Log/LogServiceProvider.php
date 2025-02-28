<?php

namespace WHMCS\Log;

class LogServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider
{
    public function register()
    {
        $logService = $this;
        $this->app->singleton("log", function () use($logService) {
            return $logService->factoryDefaultChannelLogger();
        });
        $this->app->singleton("ActivityLog", function () {
            return new Activity();
        });
        $this->app->singleton("ApiLog", function () {
            return new \Monolog\Logger("WHMCS API", [new \WHMCS\Api\Log\Handler()]);
        });
        $this->app->singleton("ApiNgLog", function () {
            return new \Monolog\Logger("WHMCS API NG", [new \WHMCS\Api\NG\Log\ApiNgLogHandler()]);
        });
        $this->importLogHandlers();
    }

    public function factoryDefaultChannelLogger()
    {
        return new \Monolog\Logger("WHMCS Application");
    }

    protected function importLogHandlers($baseDirectory = NULL)
    {
        if (is_null($baseDirectory)) {
            $baseDirectory = ROOTDIR;
        }
        $distributedFile = $baseDirectory . DIRECTORY_SEPARATOR . "dist.loghandler.php";
        $userFile = $baseDirectory . DIRECTORY_SEPARATOR . "loghandler.php";
        if (file_exists($userFile)) {
            include_once $userFile;
        } else {
            if (file_exists($distributedFile)) {
                include_once $distributedFile;
            }
        }
        return $this;
    }
}
