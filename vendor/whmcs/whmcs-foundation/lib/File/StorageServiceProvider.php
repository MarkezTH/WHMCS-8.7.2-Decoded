<?php

namespace WHMCS\File;

class StorageServiceProvider extends \WHMCS\Application\Support\ServiceProvider\AbstractServiceProvider
{
    public function register()
    {
        $this->app->singleton("storage", function () {
            return new Storage();
        });
    }
}
