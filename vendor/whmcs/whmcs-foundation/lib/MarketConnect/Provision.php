<?php

namespace WHMCS\MarketConnect;

class Provision
{
    protected $model = NULL;
    const AUTO_INSTALL_PANELS = ["cpanel", "directadmin", "plesk"];

    public static function factoryFromModel($model)
    {
        $provision = new self();
        $provision->setModel($model);
        return $provision;
    }

    public function setModel($model)
    {
        $this->model = $model;
    }

    protected function getServiceIdentifier()
    {
        if ($this->model instanceof \WHMCS\Service\Service) {
            return $this->model->product->moduleConfigOption1;
        }
        if ($this->model instanceof \WHMCS\Service\Addon) {
            $moduleConfiguration = $this->model->productAddon->moduleConfiguration;
            foreach ($moduleConfiguration as $moduleConfigureValue) {
                if ($moduleConfigureValue->settingName == "configoption1") {
                    return $moduleConfigureValue->value;
                }
            }
        } else {
            if ($this->model instanceof \WHMCS\Product\Product) {
                return $this->model->moduleConfigOption1;
            }
            if ($this->model instanceof \WHMCS\Product\Addon) {
                $moduleConfiguration = $this->model->moduleConfiguration;
                foreach ($moduleConfiguration as $moduleConfigureValue) {
                    if ($moduleConfigureValue->settingName == "configoption1") {
                        return $moduleConfigureValue->value;
                    }
                }
            }
        }
        return "";
    }

    protected function getServiceIdentifierPrefix()
    {
        $serviceId = $this->getServiceIdentifier();
        $serviceParts = explode("_", $serviceId, 2);
        return $serviceParts[0];
    }

    protected function getServiceController()
    {
        $serviceIdentifierPrefix = $this->getServiceIdentifierPrefix();
        if (!in_array($serviceIdentifierPrefix, MarketConnect::getServices())) {
            throw new \WHMCS\Exception("Unrecognised service \"" . $serviceIdentifierPrefix . "\"." . " Please ensure you are running the latest version of WHMCS.");
        }
        return MarketConnect::factoryServiceHelper($serviceIdentifierPrefix);
    }

    public function provision($params)
    {
        return $this->getServiceController()->provision($this->model, $params);
    }

    public function configure($params)
    {
        return $this->getServiceController()->configure($this->model, $params);
    }

    public function cancel($params)
    {
        return $this->getServiceController()->cancel($this->model, $params);
    }

    public function install($params)
    {
        return $this->getServiceController()->install($this->model, $params);
    }

    public function renew($params)
    {
        return $this->getServiceController()->renew($this->model, $params);
    }

    public function upgrade($params)
    {
        return $this->getServiceController()->upgrade($this->model, $params);
    }

    public function adminManagementButtons($params)
    {
        return $this->getServiceController()->adminManagementButtons($params);
    }

    public function adminServicesTabOutput($params)
    {
        return $this->getServiceController()->adminServicesTabOutput($params);
    }

    public function clientAreaAllowedFunctions($params)
    {
        return $this->getServiceController()->clientAreaAllowedFunctions($params);
    }

    public function clientAreaOutput($params)
    {
        return $this->getServiceController()->clientAreaOutput($params);
    }

    public function isEligibleForUpgrade()
    {
        return $this->getServiceController()->isEligibleForUpgrade();
    }

    public function getServiceType()
    {
        return $this->getServiceIdentifierPrefix();
    }

    public function generateCsr()
    {
        if (in_array($this->getServiceType(), Services\Symantec::SSL_TYPES) && $this->model instanceof \WHMCS\Service\Addon) {
            return $this->getServiceController()->generateCsr($this->model, \WHMCS\Module\Server::factoryFromModel($this->model->service));
        }
        return [];
    }

    public static function findRelatedHostingService(\WHMCS\Service\Service $model)
    {
        $domainCheck = [];
        $domainCheck[] = $model->domain;
        if (substr($model->domain, 0, 4) == "www.") {
            $domainCheck[] = substr($model->domain, 4);
        } else {
            $domainCheck[] = "www." . $model->domain;
        }
        return \WHMCS\Service\Service::whereHas("product", function ($query) {
            $query->whereIn("servertype", self::AUTO_INSTALL_PANELS);
        })->with("product")->whereIn("domain", $domainCheck)->where("id", "!=", $model->id)->where("userid", "=", $model->clientId)->where("domainstatus", "=", "Active")->first();
    }

    public function updateFtpDetails($params)
    {
        if (!$this->isFtpSupported()) {
            return [];
        }
        return $this->getServiceController()->updateFtpDetails($params);
    }

    public function getFtpDetailsForm($params)
    {
        if (!$this->isFtpSupported()) {
            return NULL;
        }
        return $this->getServiceController()->getFtpDetailsForm($params);
    }

    protected function isFtpSupported()
    {
        return in_array($this->getServiceType(), [MarketConnect::SERVICE_SITEBUILDER, MarketConnect::SERVICE_SITELOCK, MarketConnect::SERVICE_WEEBLY]);
    }

    public function emailMergeData($params)
    {
        return $this->getServiceController()->emailMergeData($params);
    }

    public function isSslProduct()
    {
        return $this->getServiceController()->isSslProduct();
    }

    public function hookSidebarActions(\WHMCS\View\Menu\Item $item)
    {
        return $this->getServiceController()->hookSidebarActions($item);
    }

    public function custom($params)
    {
        return $this->getServiceController()->custom($params);
    }

    public function customArgs(...$params)
    {
        return $this->getServiceController()->customArgs(...$params);
    }

    public function getUsedQuantity($params)
    {
        return $this->getServiceController()->getUsedQuantity($params);
    }

    public function clientAreaManage($params)
    {
        return $this->getServiceController()->clientAreaManage($params);
    }

    public function adminReissueSsl($params)
    {
        return $this->getServiceController()->adminReissueSsl($this->model, $params);
    }
}
