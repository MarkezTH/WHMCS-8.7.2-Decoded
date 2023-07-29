<?php

namespace WHMCS\MarketConnect\Services;

class OX extends AbstractService
{
    const WELCOME_EMAIL_TEMPLATE = "Open-Xchange Welcome Email";

    public function getServiceIdent()
    {
        return "ox";
    }

    public function configure($model, $params = NULL)
    {
        $serviceProperties = $model->serviceProperties;
        $orderNumber = marketconnect_GetOrderNumber($params);
        $relatedHostingService = NULL;
        if ($model instanceof \WHMCS\Service\Service) {
            $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
        }
        $domainName = $model instanceof \WHMCS\Service\Addon ? $model->service->domain : $model->domain;
        $configure = ["order_number" => $orderNumber, "domain" => $domainName, "reseller_company_name" => \WHMCS\Config\Setting::getValue("CompanyName"), "reseller_whmcs_url" => \App::getSystemURL(), "reseller_support_email" => \WHMCS\Config\Setting::getValue("Email"), "customer_name" => $model->client->fullName, "customer_email" => $model->client->email];
        $api = new \WHMCS\MarketConnect\Api();
        $response = $api->configure($configure);
        if (array_key_exists("error", $response)) {
            throw new \WHMCS\Exception($response["error"]);
        }
        $mxRecords = $response["data"]["mxRecords"];
        $dataToAdd = [];
        foreach ($mxRecords as $mxRecord) {
            $dataToAdd[$mxRecord["value"]] = $mxRecord["priority"];
        }
        $spfRecord = $response["data"]["spfRecords"][0]["value"];
        $migrationTool = $response["data"]["migration_tool"];
        $configurationRequired = true;
        if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
            $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
            switch ($parentModel->product->module) {
                case "cpanel":
                case "directadmin":
                case "plesk":
                    try {
                        $currentMxRecords = $parentModel->getMxRecords()["mxRecords"];
                        $parentModel->addMxRecords(["mxDomain" => $domainName, "mxRecords" => $dataToAdd, "alwaysAccept" => "local", "internal" => "no"])->removeMxRecords($currentMxRecords, $serviceProperties);
                        $currentSPFRecord = $parentModel->getSPFRecord()["spfRecord"];
                        $serviceProperties->save(["Original SPF Record" => ["type" => "text", "value" => $currentSPFRecord]]);
                        $parentModel->setSPFRecord($spfRecord);
                        $configurationRequired = false;
                    } catch (\Exception $e) {
                        logActivity(ucfirst($parentModel->product->module) . ": Error attempting to provision MX/SPF Record Changes: " . $e->getMessage(), $model->clientId);
                    }
                    $emailRelatedId = $model instanceof \WHMCS\Service\Addon ? $parentModel->id : $model->id;
                    break;
            }
        }
        $this->sendWelcomeEmail($model, $this->emailMergeData($params, ["required_mx_records" => $dataToAdd, "required_spf_record" => $spfRecord, "configuration_required" => $configurationRequired, "migration_tool" => $migrationTool]));
    }

    public function cancel($model, $params = NULL)
    {
        try {
            $serviceProperties = $model->serviceProperties;
            $orderNumber = $serviceProperties->get("Order Number");
            if (!$orderNumber) {
                throw new \WHMCS\Exception\Module\NotServicable("You must provision this service before attempting to manage it");
            }
            $relatedHostingService = NULL;
            if ($model instanceof \WHMCS\Service\Service) {
                $relatedHostingService = \WHMCS\MarketConnect\Provision::findRelatedHostingService($model);
            }
            $domainName = $model instanceof \WHMCS\Service\Addon ? $model->service->domain : $model->domain;
            $api = new \WHMCS\MarketConnect\Api();
            $response = $api->cancel($orderNumber);
            if ($response["success"]) {
                if ($model instanceof \WHMCS\Service\Addon || $relatedHostingService instanceof \WHMCS\Service\Service) {
                    $existingMxRecords = $serviceProperties->get("Original MX Records");
                    $existingSPFRecords = $serviceProperties->get("Original SPF Record");
                    $parentModel = $model instanceof \WHMCS\Service\Addon ? $model->service : $relatedHostingService;
                    switch ($parentModel->product->module) {
                        case "cpanel":
                        case "directadmin":
                        case "plesk":
                            if ($existingMxRecords) {
                                $existingMxRecords = explode("\r\n", $existingMxRecords);
                                $dataToAdd = [];
                                foreach ($existingMxRecords as $existingMxRecord) {
                                    $existingMxRecord = explode(":", $existingMxRecord);
                                    if (isset($existingMxRecord[1])) {
                                        $dataToAdd[$existingMxRecord[1]] = $existingMxRecord[0];
                                    }
                                }
                                try {
                                    $currentMxRecords = $parentModel->getMxRecords()["mxRecords"];
                                    $parentModel->addMxRecords(["mxDomain" => $domainName, "mxRecords" => $dataToAdd, "alwaysAccept" => "auto"])->removeMxRecords($currentMxRecords, $serviceProperties);
                                } catch (\Exception $e) {
                                    throw new \Exception(ucfirst($parentModel->product->module) . ": Error attempting to revert MX Record Changes: " . $e->getMessage());
                                }
                                $serviceProperties->save(["Original MX Records" => ["type" => "textarea", "value" => ""]]);
                            }
                            if ($existingSPFRecords) {
                                try {
                                    $parentModel->setSPFRecord($existingSPFRecords);
                                } catch (\Exception $e) {
                                    throw new \Exception(ucfirst($parentModel->product->module) . ": Error attempting to revert SPF Record Changes: " . $e->getMessage());
                                }
                                $serviceProperties->save(["Original SPF Record" => ["type" => "text", "value" => ""]]);
                            }
                            break;
                    }
                }
                $serviceId = $params["serviceid"];
                $addonId = $params["addonId"];
                \WHMCS\TransientData::getInstance()->delete("MarketConnectS" . $serviceId . "A" . $addonId);
                \WHMCS\TransientData::getInstance()->delete("MarketConnectCacheS" . $serviceId . "A" . $addonId);
                return NULL;
            }
            throw new \WHMCS\Exception("Cancellation Failed");
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function upgrade($model, $params = [])
    {
        $result = parent::upgrade($model, $params);
        if ($result === "success") {
            $serviceId = $params["serviceid"];
            $addonId = $params["addonId"];
            $transientName = "MarketConnectCacheS" . $serviceId . "A" . $addonId;
            $configuration = \WHMCS\TransientData::getInstance()->retrieve($transientName);
            if ($configuration) {
                $configuration = json_decode(base64_decode($configuration), true);
                $configuration["account"]["accounts"]["count"] = $model->qty;
                \WHMCS\TransientData::getInstance()->store($transientName, base64_encode(json_encode($configuration)), 86400);
            }
        }
        return $result;
    }

    public function adminServicesTabOutput($params, \WHMCS\MarketConnect\OrderInformation $orderInformation = NULL, $actionButtons = NULL)
    {
        $orderInfo = \WHMCS\MarketConnect\OrderInformation::factory($params);
        $actionButtons = [["icon" => "fa-cog", "label" => "Attempt Configuration", "class" => "btn-default", "moduleCommand" => "resend_configuration_data", "applicableStatuses" => ["Awaiting Configuration"]]];
        return parent::adminServicesTabOutput($params, $orderInfo, $actionButtons);
    }

    public function isEligibleForUpgrade()
    {
        return $this->isActive();
    }

    public function emailMergeData($params = [], $preCalculatedMergeData)
    {
        $configurationRequired = true;
        if (array_key_exists("configuration_required", $preCalculatedMergeData)) {
            $configurationRequired = $preCalculatedMergeData["configuration_required"];
        }
        $orderNumber = marketconnect_GetOrderNumber($params);
        $remoteData = NULL;
        if (array_key_exists("required_mx_records", $preCalculatedMergeData)) {
            $mxRecords = $preCalculatedMergeData["required_mx_records"];
        } else {
            $mxRecords = [];
            $remoteData = $this->getConfigurationInfoCache($orderNumber, $params["serviceid"], $params["addonId"]);
            $remoteRecords = $remoteData["records"]["mx"];
            foreach ($remoteRecords as $record) {
                $mxRecords[$record["value"]] = $record["priority"];
            }
        }
        if (array_key_exists("required_spf_record", $preCalculatedMergeData)) {
            $spfRecord = $preCalculatedMergeData["required_spf_record"];
        } else {
            if (!$remoteData) {
                $remoteData = $this->getConfigurationInfoCache($orderNumber, $params["serviceid"], $params["addonId"]);
            }
            $spfRecord = $remoteData["records"]["spf"][0]["value"];
        }
        $migrationToolUrl = NULL;
        if (array_key_exists("migration_tool", $preCalculatedMergeData)) {
            $migrationToolUrl = $preCalculatedMergeData["migration_tool"]["url"];
        } else {
            if (!$remoteData) {
                $remoteData = $this->getConfigurationInfoCache($orderNumber, $params["serviceid"], $params["addonId"]);
            }
            if (isset($remoteData["endpoints"]["migration_tool"]["free"]) && $remoteData["endpoints"]["migration_tool"]["free"]) {
                $migrationToolUrl = $remoteData["endpoints"]["migration_tool"]["url"];
            }
        }
        if (isset($remoteData["endpoints"]["webmail_portal"])) {
            $webmailUrl = $remoteData["endpoints"]["webmail_portal"];
        } else {
            $remoteData = $this->getConfigurationInfoCache($orderNumber, $params["serviceid"], $params["addonId"]);
            $webmailUrl = $remoteData["endpoints"]["webmail_portal"] ?? NULL;
        }
        return ["required_mx_records" => $mxRecords, "required_spf_record" => $spfRecord, "configuration_required" => $configurationRequired, "migration_tool_url" => $migrationToolUrl, "webmail_link" => $webmailUrl];
    }

    public function hookSidebarActions(\WHMCS\View\Menu\Item $item)
    {
        $service = \Menu::context("service");
        $addon = NULL;
        $addonId = 0;
        $status = $service->domainStatus;
        if ($service->product->module !== "marketconnect") {
            $addon = \Menu::context("addon");
            $addonId = $addon->id;
            $status = $addon->status;
        }
        $childName = "Email Actions - S" . $service->id . ($addonId ? "A" . $addonId : "");
        $child = $item->addChild($childName, ["label" => \Lang::trans("ox.emailActions"), "order" => 30, "icon" => "far fa-envelope"]);
        $manageText = $this->cLang("manage");
        $formAction = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . DIRECTORY_SEPARATOR . "clientarea.php";
        $bodyHtml = "<form data-href=\"" . $formAction . "\">\n    <input type=\"hidden\" name=\"action\" value=\"productdetails\" />\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"manage_order\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $service->id . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    <span class=\"loading hidden w-hidden\">\n            <i class=\"fas fa-spinner fa-spin\"></i>\n        </span>\n        <span class=\"text\">" . $manageText . "</span>\n    <div class=\"login-feedback\"></div>\n</form>";
        $child->addChild("Manage", ["uri" => "#", "label" => $bodyHtml, "order" => 1, "attributes" => ["class" => "btn-service-sso"], "disabled" => $status !== \WHMCS\Service\Status::ACTIVE]);
        if ($addon instanceof \WHMCS\Service\Addon) {
            $routeUri = routePath("module-custom-action-addon", $service->id, $addon->id, "manage");
        } else {
            $routeUri = routePath("module-custom-action", $service->id, "manage");
        }
        $requestUri = $_SERVER["REQUEST_URI"];
        $child->addChild("Manage OX", ["uri" => $routeUri, "attributes" => ["class" => stristr($requestUri, $routeUri) ? "active" : ""], "label" => \Lang::trans("store.ox.manage"), "order" => 2]);
        if ($this->isEligibleForUpgrade()) {
            $isProduct = (int) $addonId === 0;
            $upgradeLabel = \Lang::trans("upgrade");
            $upgradeRoute = routePath("upgrade");
            $upgradeServiceId = 0 < $addonId ? $addonId : $service->id;
            $upgrade = "<form method=\"post\" action=\"" . $upgradeRoute . "\" style=\"display:inline;\">\n    <input type=\"hidden\" name=\"isproduct\" value=\"" . $isProduct . "\">\n    <input type=\"hidden\" name=\"serviceid\" value=\"" . $upgradeServiceId . "\">\n    <span class=\"text\">" . $upgradeLabel . "</span>\n</form>";
            $child->addChild("Upgrade OX", ["uri" => $upgradeRoute, "attributes" => ["class" => stristr($requestUri, $upgradeRoute) ? "active" : "btn-sidebar-form-submit"], "label" => $upgrade, "order" => 3]);
        }
    }

    public function clientAreaAllowedFunctions($params)
    {
        $orderNumber = marketconnect_GetOrderNumber($params);
        if (!$orderNumber || $params["status"] != "Active") {
            return [];
        }
        return ["createUser", "getConfigurationInfo", "deleteUser", "listUsers", "manageUser", "manage", "manage_order", "refreshStatus", "setPassword", "modifyAliases"];
    }

    public function manage($params)
    {
        $persistentVariables = ["upgradeUrl" => routePath("upgrade"), "isService" => $params["addonId"] ? 0 : 1];
        try {
            if ($params["addonId"]) {
                $variables = ["domain" => $params["model"]->domain, "addAccountUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "createUser"), "configurationUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "getConfigurationInfo"), "deleteAccountUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "deleteUser"), "listAccountsUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "listUsers"), "manageAccountUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "manageUser"), "setPasswordUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "setPassword"), "modifyAliasesUrl" => routePath("module-custom-post-action-addon", $params["serviceid"], $params["addonId"], "modifyAliases")];
            } else {
                $variables = ["domain" => $params["model"]->domain, "addAccountUrl" => routePath("module-custom-post-action", $params["serviceid"], "createUser"), "configurationUrl" => routePath("module-custom-post-action", $params["serviceid"], "getConfigurationInfo"), "deleteAccountUrl" => routePath("module-custom-post-action", $params["serviceid"], "deleteUser"), "listAccountsUrl" => routePath("module-custom-post-action", $params["serviceid"], "listUsers"), "manageAccountUrl" => routePath("module-custom-post-action", $params["serviceid"], "manageUser"), "setPasswordUrl" => routePath("module-custom-post-action", $params["serviceid"], "setPassword"), "modifyAliasesUrl" => routePath("module-custom-post-action", $params["serviceid"], "modifyAliases")];
            }
        } catch (\Exception $e) {
            $variables = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        $variables = array_merge($persistentVariables, $variables);
        return ["template" => "store/ox/manage", "displayTitle" => "store.ox.manage", "variables" => $variables];
    }

    public function setPassword($params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $api = new \WHMCS\MarketConnect\Api();
            $api->extra("modify_user", ["order_number" => $orderNumber, "user_id" => $params["account"], "password" => $params["password"]]);
            $response = ["success" => true, "successMessage" => \Lang::trans("ox.passwordChanged")];
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function listUsers($JsonResponse, $params)
    {
        try {
            $users = $this->getListUsersCache($params);
            foreach ($users["users"] as &$user) {
                $user["quota"] += 0;
                $user["quota"] = round($user["quota"] / 1024, 2);
                $user["aliases"] = $this->buildAliasArray((array) $user["aliases"]);
            }
            $response = ["limits" => $users["meta"], "accounts" => $users["users"]];
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function getConfigurationInfo($JsonResponse, $params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $configurationInfo = $this->getConfigurationInfoCache($orderNumber, $params["serviceid"], $params["addonId"], (bool) (int) $params["force"]);
            $mxData = [];
            foreach ($configurationInfo["records"]["mx"] as $record) {
                $mxData[] = ["hostname" => $record["value"], "priority" => $record["priority"]];
            }
            $usageInstructions = [];
            foreach ($configurationInfo["usage_instructions"] as $languageKey => $fallback) {
                $translation = \Lang::trans($languageKey);
                if (!$translation || $translation === $languageKey) {
                    $translation = $fallback;
                }
                $usageInstructions[] = $translation;
            }
            $response = ["settings" => ["incoming" => ["hostname" => $configurationInfo["endpoints"]["incoming"]["imap"]["hostname"], "port" => \Lang::trans("ox.settings.port", [":port" => $configurationInfo["endpoints"]["incoming"]["imap"]["port"]])], "pop" => ["hostname" => $configurationInfo["endpoints"]["incoming"]["pop3"]["hostname"], "port" => \Lang::trans("ox.settings.port", [":port" => $configurationInfo["endpoints"]["incoming"]["pop3"]["port"]])], "outgoing" => ["hostname" => $configurationInfo["endpoints"]["outgoing"]["hostname"], "port" => \Lang::trans("ox.settings.port", [":port" => $configurationInfo["endpoints"]["outgoing"]["port"]])], "sharing" => ["hostname" => $configurationInfo["endpoints"]["sharing"]["hostname"]], "calendar" => ["hostname" => $configurationInfo["endpoints"]["dav"]["hostname"]], "mx" => $mxData, "spf" => $configurationInfo["records"]["spf"][0]["value"]], "usage" => $usageInstructions];
            if (isset($configurationInfo["endpoints"]["migration_tool"]["free"]) && $configurationInfo["endpoints"]["migration_tool"]["free"]) {
                $response["migration_tool"] = $configurationInfo["endpoints"]["migration_tool"];
            }
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function deleteUser($params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            (new \WHMCS\MarketConnect\Api())->extra("delete_user", ["order_number" => $orderNumber, "user_id" => $params["account"]]);
            $data = $this->getListUsersCache($params);
            if ($data && is_array($data)) {
                foreach ($data["users"] as $key => $user) {
                    if ($user["id"] == $params["account"]) {
                        unset($data["users"][$key]);
                        $this->updateListUsersCache($params, $data);
                    }
                }
            }
            $response = ["success" => true, "successMessage" => \Lang::trans("ox.accountDeleted")];
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function createUser($params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $api = new \WHMCS\MarketConnect\Api();
            $result = $api->extra("create_user", ["order_number" => $orderNumber, "display_name" => $params["display"], "first_name" => $params["first"], "last_name" => $params["last"], "username" => $params["email"], "password" => $params["password"]]);
            $data = $this->getListUsersCache($params);
            if ($data && is_array($data)) {
                $data["users"][] = ["id" => $result["user_id"], "name" => $params["email"] . "@" . $params["domain"], "username" => $params["email"], "display_name" => $params["display"], "first_name" => $params["first"], "last_name" => $params["last"], "quota" => $result["quota"]];
                $this->updateListUsersCache($params, $data);
            }
            $quota = round(($result["quota"] + 0) / 1024, 2);
            $response = ["success" => true, "id" => $result["user_id"], "username" => $params["email"], "display_name" => $params["display"], "first_name" => $params["first"], "last_name" => $params["last"], "quota" => $quota, "successMessage" => \Lang::trans("ox.accountCreated")];
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function manageUser($params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            (new \WHMCS\MarketConnect\Api())->extra("modify_user", ["order_number" => $orderNumber, "user_id" => $params["account"], "display_name" => $params["display"], "first_name" => $params["first"], "last_name" => $params["last"]]);
            $data = $this->getListUsersCache($params);
            if ($data && is_array($data)) {
                foreach ($data["users"] as $key => $user) {
                    if ($user["id"] == $params["account"]) {
                        $user["display_name"] = $params["display"];
                        $user["first_name"] = $params["first"];
                        $user["last_name"] = $params["last"];
                        $data["users"][$key] = $user;
                        $this->updateListUsersCache($params, $data);
                    }
                }
            }
            $response = ["success" => true, "display_name" => $params["display"], "first_name" => $params["first"], "last_name" => $params["last"], "successMessage" => \Lang::trans("ox.accountModified")];
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    protected function getConfigurationInfoCache($orderNumber, int $serviceId = 0, int $addonId = false, $force)
    {
        $transientName = "MarketConnectCacheS" . $serviceId . "A" . $addonId;
        $configurationInfo = \WHMCS\TransientData::getInstance()->retrieve($transientName);
        if ($configurationInfo) {
            $configurationInfo = json_decode(base64_decode($configurationInfo), true);
        }
        if (!$configurationInfo || !is_array($configurationInfo) || $force) {
            $api = new \WHMCS\MarketConnect\Api();
            $apiParams = ["order_number" => $orderNumber];
            $configurationInfo = $api->extra("get_configuration_info", $apiParams);
            \WHMCS\TransientData::getInstance()->store($transientName, base64_encode(json_encode($configurationInfo)), 86400);
        }
        return $configurationInfo;
    }

    protected function getListUsersCache($params)
    {
        $transientName = "MarketConnectS" . $params["serviceid"] . "A" . $params["addonId"];
        $users = \WHMCS\TransientData::getInstance()->retrieve($transientName);
        if ($users) {
            $users = json_decode(base64_decode($users), true);
        }
        if (!$users || !is_array($users) || !empty($params["force"])) {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $api = new \WHMCS\MarketConnect\Api();
            $apiParams = ["order_number" => $orderNumber];
            $users = $api->extra("list_users", $apiParams);
            \WHMCS\TransientData::getInstance()->store($transientName, base64_encode(json_encode($users)), 86400);
        }
        return $users;
    }

    protected function updateListUsersCache($params, $cache)
    {
        \WHMCS\TransientData::getInstance()->store("MarketConnectS" . $params["serviceid"] . "A" . $params["addonId"], base64_encode(json_encode($cache)));
    }

    public function getUsedQuantity($params)
    {
        $users = $this->getListUsersCache($params);
        return count($users["users"]);
    }

    protected function buildAliasArray($aliases)
    {
        $aliasArray = [];
        foreach ($aliases as $alias) {
            list($aliasArray[]) = explode("@", $alias);
        }
        return $aliasArray;
    }

    public function modifyAliases($JsonResponse, $params)
    {
        try {
            $orderNumber = marketconnect_GetOrderNumber($params);
            $action = $params["aliasAction"];
            $aliases = json_decode($params["aliases"]);
            if (!in_array($action, ["create", "delete"])) {
                throw new \WHMCS\Exception("Invalid action.");
            }
            if ($action === "create" && count(array_unique(array_map("strtolower", $aliases))) != count($aliases)) {
                return new \WHMCS\Http\Message\JsonResponse(["failed" => true, "failedMessage" => "Alias already exists."]);
            }
            (new \WHMCS\MarketConnect\Api())->extra("modify_aliases", ["order_number" => $orderNumber, "user_id" => $params["account"], "aliases" => $aliases]);
            $data = $this->getListUsersCache($params);
            if ($data && is_array($data)) {
                foreach ($data["users"] as $key => $user) {
                    if ($user["id"] == $params["account"]) {
                        $data["users"][$key]["aliases"] = $aliases;
                    }
                }
                $this->updateListUsersCache($params, $data);
            }
            $response = ["success" => true, "successMessage" => \Lang::trans("ox.alias." . $action . "Success")];
        } catch (\Exception $e) {
            $response = ["failed" => true, "failedMessage" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
