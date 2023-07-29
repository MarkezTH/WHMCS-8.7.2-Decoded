<?php

namespace WHMCS\Hook;

class HookServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider
{
    public function register()
    {
        $app = $this->app;
        $app->singleton("HookManager", function () {
            return new Manager();
        });
        $this->app->singleton("HookPublicRegistry", function () use($app) {
            $mgr = $app->make("HookManager");
            return new PublicRegistry($mgr);
        });
    }
}
