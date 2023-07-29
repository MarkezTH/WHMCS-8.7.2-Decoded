<?php

namespace WHMCS;

class Service
{
    private $id = "";
    private $userid = "";
    private $data = [];
    private $moduleparams = [];
    private $moduleresults = [];
    private $addons_names = NULL;
    private $addons_to_pids = NULL;
    private $addons_downloads = [];
    private $associated_download_ids = [];

    public function __construct($serviceId = NULL, $userId = NULL)
    {
        if (!function_exists("checkContactPermission")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        if ($serviceId) {
            $this->setServiceID($serviceId, $userId);
        }
    }

    public function setServiceID($serviceid, $userid = "")
    {
        $this->id = $serviceid;
        $this->userid = $userid;
        $this->data = [];
        $this->moduleparams = [];
        $this->moduleresults = [];
        return $this->getServicesData();
    }

    public function getServicesData()
    {
        $service = Service\Service::with(["product", "product.productGroup"])->where("id", $this->id);
        if ($this->userid) {
            $service->where("userid", $this->userid);
        }
        try {
            $service = $service->firstOrFail();
            $data = array_merge($service->toArray(), ["pid" => $service->packageId, "group_id" => $service->product->productGroup->id, "group_name" => $service->product->productGroup->getRawAttribute("name"), "productid" => $service->product->id, "name" => $service->product->getRawAttribute("name"), "type" => $service->product->type, "tax" => $service->product->applyTax, "configoptionsupgrade" => $service->product->allowConfigOptionUpgradeDowngrade, "billingcycleupgrade" => $service->product->billingcycleupgrade, "servertype" => $service->product->module]);
            $data["status"] = $service->domainStatus;
            $data["password"] = decrypt($service->password);
            $data["groupname"] = $service->product->productGroup->name;
            $data["productname"] = $service->product->name;
            $this->associated_download_ids = $service->product->getDownloadIds();
            $data["upgradepackages"] = $service->product->getUpgradeProductIds();
            $data["serviceModel"] = $service;
            $this->data = $data;
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isNotValid()
    {
        return !count($this->data) ? true : false;
    }

    public function getData($var)
    {
        return isset($this->data[$var]) ? $this->data[$var] : "";
    }

    public function getID()
    {
        return (int) $this->getData("id");
    }

    public function getServerInfo()
    {
        if (!$this->getData("server")) {
            return [];
        }
        $result = select_query("tblservers", "", ["id" => $this->getData("server")]);
        $serverarray = mysql_fetch_assoc($result);
        return $serverarray;
    }

    public function getSuspensionReason()
    {
        global $whmcs;
        if ($this->getData("status") != "Suspended") {
            return "";
        }
        $suspendreason = $this->getData("suspendreason");
        if (!$suspendreason) {
            $suspendreason = $whmcs->get_lang("suspendreasonoverdue");
        }
        return $suspendreason;
    }

    public function getBillingCycleDisplay()
    {
        global $whmcs;
        $lang = strtolower($this->getData("billingcycle"));
        $lang = str_replace(" ", "", $lang);
        $lang = str_replace("-", "", $lang);
        return $whmcs->get_lang("orderpaymentterm" . $lang);
    }

    public function getStatusDisplay()
    {
        global $whmcs;
        $lang = strtolower($this->getData("status"));
        $lang = str_replace(" ", "", $lang);
        $lang = str_replace("-", "", $lang);
        return $whmcs->get_lang("clientarea" . $lang);
    }

    public function getPaymentMethod()
    {
        $paymentmethod = $this->getData("paymentmethod");
        return Module\GatewaySetting::getFriendlyNameFor($paymentmethod) ?: $paymentmethod;
    }

    public function getAllowProductUpgrades()
    {
        if ($this->getData("status") == "Active" && $this->getData("upgradepackages")) {
            $upgradepackages = count($this->getData("upgradepackages"));
            return $upgradepackages ? true : false;
        }
        return false;
    }

    public function getAllowConfigOptionsUpgrade()
    {
        if ($this->getData("status") == "Active" && $this->getData("configoptionsupgrade")) {
            return true;
        }
        return false;
    }

    public function getAllowChangePassword()
    {
        if ($this->getData("status") == "Active" && checkContactPermission("manageproducts", true)) {
            return true;
        }
        return false;
    }

    public function getModule()
    {
        $whmcs = \App::self();
        return $whmcs->sanitize("0-9a-z_-", $this->getData("servertype"));
    }

    public function getPredefinedAddonsOnce()
    {
        if (is_array($this->addons_names)) {
            return $this->addons_names;
        }
        return $this->getPredefinedAddons();
    }

    public function getPredefinedAddons()
    {
        $this->addons_names = $this->addons_to_pids = [];
        $result = select_query("tbladdons", "", "");
        while ($data = mysql_fetch_array($result)) {
            $addon_id = $data["id"];
            $addon_packages = $data["packages"];
            $addon_packages = explode(",", $addon_packages);
            $this->addons_names[$addon_id] = $data["name"];
            $this->addons_to_pids[$addon_id] = $addon_packages;
            $this->addons_downloads[$addon_id] = explode(",", $data["downloads"]);
        }
        return $this->addons_names;
    }

    public function getPredefinedAddonName($addonid)
    {
        $addons_data = $this->getPredefinedAddonsOnce();
        return array_key_exists($addonid, $addons_data) ? $addons_data[$addonid] : "";
    }

    private function addAssociatedDownloadID($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $id) {
                if (is_numeric($id)) {
                    $this->associated_download_ids[] = $id;
                }
            }
        } else {
            if (is_numeric($mixed)) {
                $this->associated_download_ids[] = $mixed;
            } else {
                return false;
            }
        }
        return true;
    }

    public function hasProductGotAddons()
    {
        if (is_null($this->addons_to_pids)) {
            $this->getPredefinedAddons();
        }
        $addons = [];
        foreach ($this->addons_to_pids as $addonid => $pids) {
            if (in_array($this->getData("pid"), $pids)) {
                $addons[] = $addonid;
            }
        }
        return $addons;
    }

    public function getAddons()
    {
        global $whmcs;
        $trans = function ($clientKey, $adminKey, $clientArgs = [], $adminArgs = []) use($whmcs) {
            if ($whmcs->isAdminAreaRequest()) {
                return \AdminLang::trans($adminKey, $adminArgs);
            }
            return \Lang::trans($clientKey, $clientArgs);
        };
        $addonCollection = Service\Addon::with("productAddon")->where("hostingid", "=", $this->getID())->orderBy("id", "DESC")->get();
        $addons = [];
        foreach ($addonCollection as $addon) {
            $addonName = $addon->name;
            $addonPaymentMethod = $addon->paymentGateway;
            $rawStatus = strtolower($addon->status);
            $addonRegistrationDate = fromMySQLDate($addon->registrationDate, 0, 1);
            $addonNextDueDate = fromMySQLDate($addon->nextDueDate, 0, 1);
            $addonPricing = "";
            if (!$addonPaymentMethod) {
                $addonPaymentMethod = ensurePaymentMethodIsSet($addon->clientId, $addon->id, "tblhostingaddons");
            }
            if ($addon->addonId) {
                if (!$addonName) {
                    $addonName = $addon->productAddon->name;
                }
                if (0 < count($addon->productAddon->downloads)) {
                    $this->addAssociatedDownloadID($addon->productAddon->downloads);
                }
                if ($addon->productAddon->allowMultipleQuantities === Cart\CartCalculator::QUANTITY_SCALING && 1 < $addon->qty) {
                    $addonName = $addon->qty . " x " . $addonName;
                }
            }
            if (substr($addon->billingCycle, 0, 4) == "Free") {
                $addonPricing = $trans("orderfree", "billingcycles.free");
                $addonNextDueDate = "-";
            } else {
                if ($addon->billingCycle == "One Time") {
                    $addonNextDueDate = "-";
                }
                if (0 < $addon->setupFee) {
                    $addonPricing .= sprintf("%s %s", formatCurrency($addon->setupFee), $trans("ordersetupfee", "fields.setupfee"));
                }
                if (0 < $addon->recurringFee) {
                    $cycleTranslation = str_replace(["-", " "], "", strtolower($addon->billingCycle));
                    if (0 < $addon->setupFee) {
                        $addonPricing .= " + ";
                    }
                    $addonPricing .= sprintf("%s %s", formatCurrency($addon->recurringFee), $trans("orderpaymentterm" . $cycleTranslation, "billingcycles." . $cycleTranslation));
                    unset($cycleTranslation);
                }
                if (!$addonPricing) {
                    $addonPricing = $trans("orderfree", "billingcycles.free");
                }
            }
            $xColour = "clientareatable" . $rawStatus;
            $addonStatus = $trans("clientarea" . $rawStatus, "status." . $rawStatus);
            if (!in_array($rawStatus, ["Active", "Suspended", "Pending"])) {
                $xColour = "clientareatableterminated";
            }
            $managementActions = "";
            if (defined("CLIENTAREA") && $addon->productAddon->module) {
                $server = new Module\Server();
                if ($server->loadByAddonId($addon->id) && $server->functionExists("ClientArea")) {
                    $managementActions = $server->call("ClientArea");
                    if (is_array($managementActions)) {
                        $managementActions = "";
                    }
                }
            }
            $addons[] = ["id" => $addon->id, "regdate" => $addonRegistrationDate, "name" => $addonName, "pricing" => $addonPricing, "paymentmethod" => $addonPaymentMethod, "nextduedate" => $addonNextDueDate, "status" => $addonStatus, "rawstatus" => $rawStatus, "class" => $xColour, "managementActions" => $managementActions];
        }
        return $addons;
    }

    public function getAssociatedDownloads()
    {
        $download_ids = db_build_in_array(db_escape_numarray($this->associated_download_ids));
        if (!$download_ids) {
            return [];
        }
        $downloadsarray = [];
        $result = select_query("tbldownloads", "", "id IN (" . $download_ids . ")", "id", "DESC");
        while ($data = mysql_fetch_array($result)) {
            $dlid = $data["id"];
            $category = $data["category"];
            $type = $data["type"];
            $title = $data["title"];
            $description = $data["description"];
            $downloads = $data["downloads"];
            $location = $data["location"];
            $fileext = explode(".", $location);
            $fileext = end($fileext);
            $type = "zip";
            if ($fileext == "doc") {
                $type = "doc";
            }
            if ($fileext == "gif" || $fileext == "jpg" || $fileext == "jpeg" || $fileext == "png") {
                $type = "picture";
            }
            if ($fileext == "txt") {
                $type = "txt";
            }
            $type = "<img src=\"images/" . $type . ".png\" align=\"absmiddle\" alt=\"\" />";
            $downloadsarray[] = ["id" => $dlid, "catid" => $category, "type" => $type, "title" => $title, "description" => $description, "downloads" => $downloads, "link" => "dl.php?type=d&id=" . $dlid . "&serviceid=" . $this->getID()];
        }
        return $downloadsarray;
    }

    public function getCustomFields()
    {
        return getCustomFields("product", $this->getData("pid"), $this->getData("id"), "", "", "", true);
    }

    public function getConfigurableOptions()
    {
        return getCartConfigOptions($this->getData("pid"), "", $this->getData("billingcycle"), $this->getData("id"));
    }

    public function getAllowCancellation()
    {
        if (($this->getData("status") == "Active" || $this->getData("status") == "Suspended") && checkContactPermission("orders", true)) {
            $billingCycle = $this->getData("billingcycle");
            if (!in_array(strtolower($billingCycle), ["free", "free account", "one time", "onetime"])) {
                $whmcs = \App::self();
                return $whmcs->get_config("ShowCancellationButton") ? true : false;
            }
        }
        return false;
    }

    public function hasCancellationRequest()
    {
        if ($this->getData("status") != "Cancelled") {
            $cancellation = Database\Capsule::table("tblcancelrequests")->select("type")->where("relid", "=", $this->getData("id"))->count();
            return 0 < $cancellation;
        }
        return false;
    }

    public function getDiskUsageStats()
    {
        global $whmcs;
        $diskusage = $this->getData("diskusage");
        $disklimit = $this->getData("disklimit");
        $bwusage = $this->getData("bwusage");
        $bwlimit = $this->getData("bwlimit");
        $lastupdate = $this->getData("lastupdate");
        $serviceModel = $this->getData("serviceModel");
        $metrics = $serviceModel->metrics(true);
        foreach ($metrics as $serviceMetric) {
            if (!$serviceMetric->usage() instanceof UsageBilling\Metrics\NoUsage) {
                $units = $serviceMetric->units();
                if ($serviceMetric->systemName() == "diskusage") {
                    $diskusage = $units->roundForType($serviceMetric->usage()->value() * 1024);
                    $lastupdate = $serviceMetric->usage()->collectedAt()->toDateTimeString();
                } else {
                    if ($serviceMetric->systemName() == "bandwidthusage") {
                        $bwusage = $units->roundForType($serviceMetric->usage()->value() * 1024);
                        $lastupdate = $serviceMetric->usage()->collectedAt()->toDateTimeString();
                    }
                }
            }
        }
        if ($disklimit == "0") {
            $disklimit = $whmcs->get_lang("clientareaunlimited");
            $diskpercent = "0%";
        } else {
            $diskpercent = round($diskusage / $disklimit * 100, 0) . "%";
        }
        if ($bwlimit == "0") {
            $bwlimit = $whmcs->get_lang("clientareaunlimited");
            $bwpercent = "0%";
        } else {
            $bwpercent = round($bwusage / $bwlimit * 100, 0) . "%";
        }
        $lastupdate = $lastupdate == "0000-00-00 00:00:00" ? "" : fromMySQLDate($lastupdate, 1, 1);
        return ["diskusage" => $diskusage, "disklimit" => $disklimit, "diskpercent" => $diskpercent, "bwusage" => $bwusage, "bwlimit" => $bwlimit, "bwpercent" => $bwpercent, "lastupdate" => $lastupdate];
    }

    public function hasFunction($function)
    {
        $moduleInterface = new Module\Server();
        $moduleName = $this->getModule();
        if (!$moduleName) {
            $this->moduleresults = ["error" => "Service not assigned to a module"];
            return false;
        }
        $loaded = $moduleInterface->load($moduleName);
        if (!$loaded) {
            $this->moduleresults = ["error" => "Product module not found"];
            return false;
        }
        return $moduleInterface->functionExists($function);
    }

    public function moduleCall($function, $vars = [])
    {
        $moduleInterface = new Module\Server();
        $moduleName = $this->getModule();
        if (!$moduleName) {
            $this->moduleresults = ["error" => "Service not assigned to a module"];
            return false;
        }
        $loaded = $moduleInterface->load($moduleName);
        if (!$loaded) {
            $this->moduleresults = ["error" => "Product module not found"];
            return false;
        }
        $moduleInterface->setServiceId($this->getID());
        $builtParams = array_merge($moduleInterface->getParams(), $vars);
        switch ($function) {
            case "CreateAccount":
                $hookFunction = "Create";
                break;
            case "SuspendAccount":
                $hookFunction = "Suspend";
                break;
            case "TerminateAccount":
                $hookFunction = "Terminate";
                break;
            case "UnsuspendAccount":
                $hookFunction = "Unsuspend";
                break;
            default:
                $hookFunction = $function;
                $hookResults = run_hook("PreModule" . $hookFunction, ["params" => $builtParams]);
                try {
                    if (\HookMgr::processResults($moduleName, $function, $hookResults)) {
                        return true;
                    }
                } catch (Exception $e) {
                    $this->moduleresults = ["error" => $e->getMessage()];
                    return false;
                }
                if (!isset($hookResults["functionExists"])) {
                    $hookResults["functionExists"] = false;
                }
                $results = $moduleInterface->call($function, $builtParams);
                $hookVars = ["params" => $builtParams, "results" => $results, "functionExists" => $results !== Module\Server::FUNCTIONDOESNTEXIST, "functionSuccessful" => is_array($results) && empty($results["error"]) || is_object($results)];
                $successOrFail = "";
                if (!$hookVars["functionSuccessful"] && $hookResults["functionExists"]) {
                    $successOrFail = "Failed";
                } else {
                    if ($hookFunction === "Create") {
                        (new Product\EventAction\EventActionProcessorHandler())->handleModuleEvent(Product\EventAction\EventActionProcessorHandler::ENTITY_TYPE_SERVICE, $this->getData("serviceModel"), "aftercreate");
                    }
                }
                $hookResults = run_hook("AfterModule" . $hookFunction . $successOrFail, $hookVars);
                try {
                    if (\HookMgr::processResults($moduleName, $function, $hookResults)) {
                        return true;
                    }
                } catch (Exception $e) {
                    return ["error" => $e->getMessage()];
                }
                if (!$hookVars["functionExists"] || $results === false) {
                    $this->moduleresults = ["error" => "Function not found"];
                    return false;
                }
                if (is_array($results)) {
                    $results = ["data" => $results];
                } else {
                    $results = $results == "success" || !$results ? [] : ["error" => $results, "data" => $results];
                }
                $this->moduleresults = $results;
                return isset($results["error"]) && $results["error"] ? false : true;
        }
    }

    public function getModuleReturn($var = "")
    {
        if (!$var) {
            return $this->moduleresults;
        }
        return isset($this->moduleresults[$var]) ? $this->moduleresults[$var] : "";
    }

    public function getLastError()
    {
        return $this->getModuleReturn("error");
    }
}
