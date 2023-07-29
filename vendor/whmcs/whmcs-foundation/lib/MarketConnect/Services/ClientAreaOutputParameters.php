<?php

namespace WHMCS\MarketConnect\Services;

class ClientAreaOutputParameters
{
    protected $params = NULL;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function isActiveOrder()
    {
        $orderNumber = marketconnect_GetOrderNumber($this->params);
        return $orderNumber && $this->params["status"] == "Active";
    }

    public function getServiceId()
    {
        return (int) $this->params["serviceid"];
    }

    public function getAddonId()
    {
        return array_key_exists("addonId", $this->params) ? (int) $this->params["addonId"] : 0;
    }

    public function isProduct()
    {
        return $this->getAddonId() == 0;
    }

    public function isAddon()
    {
        return 0 < $this->getAddonId();
    }

    public function getUpgradeServiceId()
    {
        return $this->isAddon() ? $this->getAddonId() : $this->getServiceId();
    }

    public function getModel($AbstractModel)
    {
        return $this->params["model"];
    }
}
