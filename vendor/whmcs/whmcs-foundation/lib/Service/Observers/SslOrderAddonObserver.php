<?php

namespace WHMCS\Service\Observers;

class SslOrderAddonObserver
{
    public function deleting(\WHMCS\Service\Addon $addon)
    {
        $addon->ssl()->delete();
    }
}
