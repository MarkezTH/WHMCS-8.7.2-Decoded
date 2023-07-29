<?php

namespace WHMCS\MarketConnect\Services;

abstract class AbstractService implements ServiceInterface
{
    const WELCOME_EMAIL_TEMPLATE = "";
    public abstract function configure($model, $params);
    public abstract function getServiceIdent();

    public function provision($model, $params = NULL)
    {
        $this->configure($model, $params);
    }

    public function cancel($model, $params = NULL)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        $api = new \WHMCS\MarketConnect\Api();
        $response = $api->cancel($orderNumber);
        if (array_key_exists("error", $response)) {
            throw new \WHMCS\Exception($response["error"]);
        }
    }

    public function renew($model, $params = NULL)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        $term = marketconnect_DetermineTerm($params);
        $api = new \WHMCS\MarketConnect\Api();
        $response = $api->renew($orderNumber, $term);
        $model->serviceProperties->save(["Order Number" => $response["order_number"]]);
    }

    public function install(\WHMCS\ServiceInterface $model, $params = [])
    {
        return "";
    }

    public function upgrade($model, $params = [])
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $term = marketconnect_DetermineTerm($params);
            $api = new \WHMCS\MarketConnect\Api();
            $order = $api->upgrade($orderNumber, $params["configoption1"], $term, $params["qty"]);
            $orderNumber = $order["order_number"];
            $model->serviceProperties->save(["Order Number" => $orderNumber]);
            return "success";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function adminManagementButtons($params)
    {
        return [];
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInfo = NULL, $actionBtns = NULL)
    {
        $userId = $params["userid"];
        $serviceId = $params["serviceid"];
        $addonId = $params["addonId"];
        $orderInformationOutput = "";
        if ($orderInfo) {
            foreach ($orderInfo->getAdditionalInformation() as $label => $value) {
                $label = preg_split("/(?=[A-Z])/", $label);
                $label = array_map("ucfirst", $label);
                $displayValue = "-";
                if (is_array($value)) {
                    if (isset($value["htmlValue"])) {
                        $displayValue = $value["htmlValue"];
                    }
                } else {
                    if (is_scalar($value) && (string) $value !== "") {
                        $displayValue = htmlspecialchars($value);
                    }
                }
                $orderInformationOutput .= "<div class=\"row\"><div class=\"col-sm-4 field-label\">" . implode(" ", $label) . "</div><div class=\"col-sm-8\">" . $displayValue . "</div></div>";
            }
        }
        $actionBtnsOutput = "";
        if (is_array($actionBtns)) {
            foreach ($actionBtns as $button) {
                $class = "btn btn-default";
                $href = isset($button["href"]) ? $button["href"] : "?userid=" . $userId . "&id=" . $serviceId;
                if ($addonId) {
                    $href .= "&aid=" . $addonId;
                }
                if (isset($button["moduleCommand"])) {
                    $href .= "&modop=custom&ac=" . $button["moduleCommand"];
                }
                $modalOptions = "";
                if (isset($button["modal"]) && is_array($button["modal"])) {
                    $options = $button["modal"];
                    $modalTitle = isset($options["title"]) ? $options["title"] : "";
                    $modalClass = isset($options["class"]) ? $options["class"] : "";
                    $modalSize = isset($options["size"]) ? $options["size"] : "";
                    $submitLabel = isset($options["submitLabel"]) ? $options["submitLabel"] : "";
                    $submitId = isset($options["submitId"]) ? $options["submitId"] : "";
                    $class .= " open-modal";
                    $href .= generate_token("link");
                    $modalOptions = " data-modal-title=\"" . $modalTitle . "\" data-modal-size=\"" . $modalSize . "\" data-modal-class=\"" . $modalClass . "\"" . ($submitLabel ? " data-btn-submit-label=\"" . $submitLabel . "\" data-btn-submit-id=\"" . $submitId . "\"" : "");
                }
                $disabled = "";
                if (!in_array($orderInfo->status, $button["applicableStatuses"])) {
                    $disabled = " disabled=\"disabled\"";
                }
                if (!$disabled && !empty($button["disabled"])) {
                    $disabled = " disabled=\"disabled\"";
                }
                $actionBtnsOutput .= "<a href=\"" . $href . "\" class=\"" . $class . "\"" . $modalOptions . $disabled . ">\n                <i class=\"fas " . $button["icon"] . " fa-fw\"></i>\n                " . $button["label"] . "\n            </a>" . PHP_EOL;
            }
        }
        $disabled = "";
        if ($orderInfo->status && in_array($orderInfo->status, ["Cancelled", "Refunded", "Order not found"])) {
            $disabled = " disabled=\"disabled\"";
        }
        $actionBtnsOutput .= "<button type=\"button\" class=\"btn btn-default btn-cancel\" id=\"btnMcCancelOrder\"" . $disabled . ">\n                <i class=\"fas fa-times fa-fw\"></i>\n                Cancel\n            </button>" . PHP_EOL;
        $js = "";
        if ($orderInfo->orderNumber && $orderInfo->isCacheStale()) {
            $js = "<script>\n    \$(document).ready(function() {\n        \$('#btnMcServiceRefresh').click();\n    });\n</script>";
        }
        $lastUpdated = \AdminLang::trans("global.never");
        if ($orderInfo && $orderInfo->getLastUpdated()) {
            $lastUpdated = $orderInfo->getLastUpdated();
        }
        $serviceLearnMoreLink = "";
        if (isset($params["serviceLearnMoreLink"])) {
            $serviceLearnMoreLink = "<div class=\"small\" style=\"margin-top: 5px\">" . "<a href=\"" . $params["serviceLearnMoreLink"]["url"] . "\" target=\"_blank\">" . $params["serviceLearnMoreLink"]["text"] . "</a>" . "</div>";
        }
        return ["" => "<div class=\"mc-smwrapper\" id=\"mcServiceManagementWrapper\">\n    <div class=\"mc-sm-container\">\n        <h3>\n            Service Management\n            <a href=\"userid=" . $userId . "&id=" . $serviceId . "&aid=" . $addonId . "&modop=custom&ac=refreshStatus\" class=\"btn btn-default btn-sm pull-right btn-refresh\" id=\"btnMcServiceRefresh\">\n                <i class=\"fas fa-sync\"></i>\n            </a>\n            <span>\n                Last Updated: " . $lastUpdated . "\n            </span>\n        </h3>\n        \n        " . $serviceLearnMoreLink . "\n\n        <div class=\"detailed-order-status\">\n            <div class=\"row\">\n                <div class=\"col-sm-4 field-label\">Marketplace Order Number</div>\n                <div class=\"col-sm-8\">\n                    " . ($orderInfo->orderNumber ? $orderInfo->orderNumber : "-") . "\n                </div>\n            </div>\n            <div class=\"row\">\n                <div class=\"col-sm-4 field-label\">Associated Domain</div>\n                <div class=\"col-sm-8\">\n                    " . ($orderInfo->domain ? $orderInfo->domain : "-") . "\n                </div>\n            </div>\n            " . $orderInformationOutput . "\n            <div class=\"row\">\n                <div class=\"col-sm-4 field-label\">Order Status</div>\n                <div class=\"col-sm-8\">\n                    <span class=\"status " . str_replace(" ", "", strtolower($orderInfo->status)) . "\">" . ($orderInfo->status ? $orderInfo->status : ($orderInfo->orderNumber ? "Refreshing..." : "Not Yet Provisioned")) . "</span>\n                </div>\n            </div>\n        </div>\n\n        <div class=\"actions\">\n            " . $actionBtnsOutput . "\n        </div>\n\n        <div class=\"addt-info\">\n            <strong>Status Description</strong><br>\n            " . ($orderInfo->statusDescription ? $orderInfo->statusDescription : "You must accept/create this order to provision the service ready for use.") . "\n        </div>\n    </div>\n</div>" . PHP_EOL . $js];
    }

    public function adminReissueSsl(\WHMCS\ServiceInterface $model, $params)
    {
        throw new \WHMCS\Exception\Module\NotServicable("The request for the MarketConnect service was invalid.");
    }

    public function emailMergeData($params, $preCalculatedMergeData = [])
    {
        return [];
    }

    public function isEligibleForUpgrade()
    {
        return false;
    }

    public function isSslProduct()
    {
        return false;
    }

    public function clientAreaAllowedFunctions($params)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber || $params["status"] != "Active") {
            return [];
        }
        return ["manage_order"];
    }

    public function clientAreaOutput($params)
    {
        return "";
    }

    public function hookSidebarActions(\WHMCS\View\Menu\Item $item)
    {
        $model = $service = \Menu::context("service");
        $addonId = 0;
        $status = $service->domainStatus;
        if ($service->product->module !== "marketconnect") {
            $model = $addon = \Menu::context("addon");
            $addonId = $addon->id;
            $status = $addon->status;
        }
        $customActions = $this->clientAreaAllowedFunctions(["status" => $status, "model" => $model]);
        if (is_array($customActions) && in_array("manage_order", $customActions)) {
            $manageText = $this->cLang("manage");
            $bodyHtml = "<form>\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $service->id . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <span>\n        <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageText . "</span>\n    </span>\n    <div class=\"login-feedback\"></div>\n</form>";
            $item->getChild("Service Details Actions")->addChild("Manage", ["uri" => "#", "label" => $bodyHtml, "order" => 1, "attributes" => ["class" => "btn-service-sso"], "disabled" => $status !== \WHMCS\Service\Status::ACTIVE]);
        }
    }

    public function custom($params)
    {
        $action = $params["method"];
        if (method_exists($this, $action)) {
            return $this->{$action}($params);
        }
        return \WHMCS\Module\Server::FUNCTIONDOESNTEXIST;
    }

    public function customArgs($method, ...$params)
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$params);
        }
        return \WHMCS\Module\Server::FUNCTIONDOESNTEXIST;
    }

    public function getUsedQuantity($params)
    {
        return 1;
    }

    protected function provisionFtp($usernamePrefix, \WHMCS\Model\AbstractModel $model)
    {
        $parentModel = NULL;
        if ($model instanceof \WHMCS\Service\Addon) {
            $parentModel = $model->service;
        } else {
            if ($model instanceof \WHMCS\Service\Service) {
                $parentModel = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
            }
        }
        if (is_null($parentModel)) {
            return false;
        }
        $serviceProperties = $model->serviceProperties;
        $ftpHost = $serviceProperties->get("FTP Host");
        $ftpUsername = $serviceProperties->get("FTP Username");
        $ftpPassword = $serviceProperties->get("FTP Password");
        if ($ftpHost && $ftpUsername && $ftpPassword) {
            return true;
        }
        $ftpHost = $parentModel->domain;
        $ftpPath = "/";
        $module = $parentModel->product->module;
        if ($module) {
            $serverInterface = \WHMCS\Module\Server::factoryFromModel($parentModel);
            if ($serverInterface->functionExists("CreateFTPAccount")) {
                $ftpUsername = $usernamePrefix . $model->id;
                $ftpPassword = (new \WHMCS\Utility\Random())->string(4, 4, 2, 2);
                try {
                    $serverInterface->call("CreateFTPAccount", ["ftpUsername" => $ftpUsername, "ftpPassword" => $ftpPassword]);
                    $ftpUsername = $ftpUsername . "@" . $parentModel->domain;
                } catch (\Exception $e) {
                    logActivity($serverInterface->getDisplayName() . " Error: " . $e->getMessage(), $model->clientId);
                    $ftpUsername = $parentModel->username;
                    $ftpPassword = decrypt($parentModel->password);
                    if (stristr($e->getMessage(), "Connection Error") !== false) {
                        $ftpUsername = $ftpPassword = "";
                    }
                }
            } else {
                if ($module === "plesk") {
                    $ftpPath = "/httpdocs";
                }
                $ftpUsername = $parentModel->username;
                $ftpPassword = decrypt($parentModel->password);
            }
        }
        $model->serviceProperties->save(["FTP Host" => $ftpHost, "FTP Username" => $ftpUsername, "FTP Password" => ["type" => "password", "value" => $ftpPassword], "FTP Path" => $ftpPath]);
        if (!$ftpUsername || !$ftpPassword || !$ftpHost) {
            return false;
        }
        return true;
    }

    protected function setFtpDetailsRemotely($serviceProperties)
    {
        $api = new \WHMCS\MarketConnect\Api();
        return $api->extra("setftpcredentials", ["order_number" => $serviceProperties->get("Order Number"), "ftp_host" => $serviceProperties->get("FTP Host"), "ftp_username" => $serviceProperties->get("FTP Username"), "ftp_password" => $serviceProperties->get("FTP Password"), "ftp_path" => $serviceProperties->get("FTP Path")]);
    }

    protected function sendWelcomeEmail($model, $templateExtraData = [])
    {
        $emailTemplate = static::WELCOME_EMAIL_TEMPLATE;
        $emailRelatedId = $model->id;
        if ($model instanceof \WHMCS\Service\Addon) {
            if ($model->productAddon->welcomeEmailTemplateId) {
                $emailTemplate = $model->productAddon->welcomeEmailTemplate;
            }
            $emailRelatedId = $model->service->id;
        } else {
            if ($model instanceof \WHMCS\Service\Service && $model->product->welcomeEmailTemplateId) {
                $emailTemplate = $model->product->welcomeEmailTemplate;
            }
        }
        if (!$emailTemplate) {
            return NULL;
        }
        sendMessage($emailTemplate, $emailRelatedId, $templateExtraData);
    }

    public function updateFtpDetails($params)
    {
        $returnKey = "growl";
        $clientArea = false;
        if (defined("CLIENTAREA")) {
            $returnKey = "jsonResponse";
            $clientArea = true;
        }
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            if (!$orderNumber) {
                throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to manage it.");
            }
            $model = $params["model"];
            $serviceProperties = $model->serviceProperties;
            $ftpHost = $serviceProperties->get("FTP Host");
            $ftpUsername = $serviceProperties->get("FTP Username");
            $ftpPassword = $serviceProperties->get("FTP Password");
            $ftpPath = $serviceProperties->get("FTP Path");
            if ($clientArea) {
                $ftpHost = \App::getFromRequest("ftpHost");
                $ftpUsername = \App::getFromRequest("ftpUsername");
                $ftpPassword = \App::getFromRequest("ftpPassword");
                $ftpPath = \App::getFromRequest("ftpPath");
            }
            if (!$ftpHost) {
                throw new \WHMCS\Exception\Module\NotServicable("FTP Host is required");
            }
            if (!$ftpUsername) {
                throw new \WHMCS\Exception\Module\NotServicable("FTP Username is required");
            }
            if (!$ftpPassword) {
                throw new \WHMCS\Exception\Module\NotServicable("FTP Password is required");
            }
            if (!$ftpPath) {
                throw new \WHMCS\Exception\Module\NotServicable("FTP Path is required. For the base directory, use /");
            }
            if ($clientArea) {
                $serviceProperties->save(["FTP Host" => $ftpHost, "FTP Username" => $ftpUsername, "FTP Password" => ["type" => "password", "value" => $ftpPassword], "FTP Path" => $ftpPath]);
            }
            $response2 = $this->setFtpDetailsRemotely($serviceProperties);
            if (!empty($response2["error"])) {
                throw new \WHMCS\Exception\Module\NotServicable($response2["error"]);
            }
            return [$returnKey => ["dismiss" => true, "message" => "FTP credentials updated successfully!"]];
        } catch (\Exception $e) {
            if (defined("ADMINAREA")) {
                return [$returnKey => ["type" => "error", "message" => $e->getMessage()]];
            }
            return [$returnKey => ["type" => "error", "message" => $e->getMessage(), "body" => $this->getFtpDetailsForm($params, $e)->body]];
        }
    }

    public function getFtpDetailsForm($params, \Exception $exception = NULL)
    {
        throw new \WHMCS\Exception\Module\NotServicable("Service does not support FTP details management");
    }

    protected function cLang($key)
    {
        $ident = $this->getServiceIdent();
        $controller = \WHMCS\MarketConnect\MarketConnect::getControllerClassByService($ident);
        $langPrefix = (new $controller())->getLangPrefix();
        return \DI::make("lang")->trans("marketConnect." . $langPrefix . "." . $key);
    }

    protected function getFormUrlForAction($action, ClientAreaOutputParameters $params)
    {
        $addonId = $params->getAddonId();
        $path = "";
        if (0 < $addonId) {
            $path = routePath("module-custom-action-addon", $params->getServiceId(), $addonId, $action);
        } else {
            $path = routePath("module-custom-action", $params->getServiceId(), $action);
        }
        return $path;
    }

    public function isActive()
    {
        return \WHMCS\MarketConnect\MarketConnect::isActive(static::getServiceIdent());
    }
}
