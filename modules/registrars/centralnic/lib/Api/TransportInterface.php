<?php

namespace WHMCS\Module\Registrar\CentralNic\Api;

interface TransportInterface
{
    public function doCall($command, AbstractApi $api);
}
