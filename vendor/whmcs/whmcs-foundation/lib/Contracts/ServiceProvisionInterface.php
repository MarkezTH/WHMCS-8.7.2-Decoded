<?php

namespace WHMCS\Contracts;

interface ServiceProvisionInterface
{
    public function provision($model, $params);

    public function configure($model, $params);

    public function cancel($model, $params);

    public function renew($model, $response);

    public function install(\WHMCS\ServiceInterface $model, $params);
}
