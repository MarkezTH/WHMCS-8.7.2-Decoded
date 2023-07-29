<?php

namespace Transip\Api\Library\Repository;

class DomainWhitelabelRepository extends ApiRepository
{
    const RESOURCE_NAME = "whitelabel";

    public function order()
    {
        $this->httpClient->post($this->getResourceUrl());
    }
}
