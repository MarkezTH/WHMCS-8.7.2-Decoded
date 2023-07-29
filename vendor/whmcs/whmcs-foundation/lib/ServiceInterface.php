<?php

namespace WHMCS;

interface ServiceInterface
{
    public function getServiceClient($Client);

    public function getServiceDomain();

    public function getServiceProperties($Properties);

    public function getServiceActual($Service);

    public function getServiceSurrogate($Service);

    public function hasServiceSurrogate();
}
