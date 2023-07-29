<?php

namespace WHMCS\Module\Gateway\CCAvenueV2;

class ShutdownHandler extends \Whoops\Handler\CallbackHandler
{
    public function handle()
    {
        if (is_callable($this->callable)) {
            return parent::handle();
        }
    }

    public function unregister()
    {
        $this->callable = NULL;
    }
}
