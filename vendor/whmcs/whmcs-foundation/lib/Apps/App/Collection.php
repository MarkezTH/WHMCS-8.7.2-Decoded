<?php

namespace WHMCS\Apps\App;

class Collection
{
    protected $apps = NULL;

    public function __construct()
    {
        $this->initialiseApps();
    }

    protected function initialiseApps()
    {
        $this->apps = [];
        foreach ((new \WHMCS\Module\Module())->getAllClasses() as $moduleInterface) {
            foreach ($moduleInterface->getApps() as $app) {
                $this->apps[$app->getKey()] = $app;
            }
        }
        $additionalApps = [];
        foreach ((new \WHMCS\Apps\Feed())->additionalApps() as $key => $app) {
            if (!array_key_exists($key, $this->apps)) {
                $additionalApps[$key] = Model::factoryFromRemoteFeed($app);
            }
        }
        $this->apps = array_merge($this->apps, $additionalApps);
        uasort($this->apps, function ($a, $b) {
            return strcmp($a->getDisplayName(), $b->getDisplayName());
        });
        return $this;
    }

    public function all()
    {
        return $this->apps;
    }

    public function exists($appKey)
    {
        return isset($this->apps[$appKey]);
    }

    public function get($appKey)
    {
        return $this->apps[$appKey];
    }

    public function active()
    {
        $appHelper = new Utility\AppHelper();
        $appsToReturn = [];
        foreach ($this->apps as $key => $app) {
            if ($app->isActive() && !$appHelper->isExcludedFromActiveList($key)) {
                $appsToReturn[$key] = $app;
            }
        }
        return $appsToReturn;
    }
}
