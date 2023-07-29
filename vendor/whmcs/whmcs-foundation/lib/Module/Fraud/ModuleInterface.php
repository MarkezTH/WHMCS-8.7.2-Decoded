<?php

namespace WHMCS\Module\Fraud;

interface ModuleInterface
{
    public function validateRules($params, ResponseInterface $response);

    public function formatResponse(ResponseInterface $response);
}
