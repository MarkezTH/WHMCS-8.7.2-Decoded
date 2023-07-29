<?php

namespace WHMCS;

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\HookMgr;
use WHMCS\Application\Support\Facades\Lang;

class Domains
{
    private $id = 0;
    private $data = [];
    private $domainModel = NULL;
    private $moduleresults = [];
    private $domainInformation = NULL;
    private $registrarModule = NULL;

    public function splitDomain($domain)
    {
        $sld = $domain;
        $tld = "";
        if (strpos($domain, ".") !== false) {
            $domain = $this->stripOutSubdomains($domain);
            $domainparts = explode(".", $domain, 2);
            $sld = $domainparts[0];
            $tld = isset($domainparts[1]) ? "." . $domainparts[1] : "";
        }
        return ["sld" => $sld, "tld" => $tld];
    }

    public function splitAndCleanDomainInput($domain)
    {
        if (!$domain) {
            throw new \InvalidArgumentException("Invalid Domain");
        }
        if (substr($domain, -1, 1) == "/") {
            $domain = substr($domain, 0, -1);
        }
        if (substr($domain, 0, 8) == "https://") {
            $domain = substr($domain, 8);
        }
        if (substr($domain, 0, 7) == "http://") {
            $domain = substr($domain, 7);
        }
        $domain = Domains\Idna::toPunycode(Domains\Idna::fromPunycode(trim($domain)));
        extract($this->splitDomain($domain));
        $sld = $this->clean($sld);
        $tld = $this->clean($tld);
        return ["sld" => $sld, "tld" => $tld];
    }

    protected function stripOutSubdomains($domain)
    {
        $domain = preg_replace("/^www\\./", "", $domain);
        return $domain;
    }

    public function clean($val)
    {
        return $val;
    }

    public function checkDomainisValid($parts)
    {
        $sld = $parts["sld"];
        $tld = $parts["tld"];
        if ($sld[0] == "-" || $sld[strlen($sld) - 1] == "-") {
            return 0;
        }
        $isIdn = $isIdnTld = $skipAllowIDNDomains = false;
        if (Config\Setting::getValue("AllowIDNDomains")) {
            try {
                $isIdn = true;
                $convertedDomain = Domains\Idna::fromPunycode($sld . $tld);
                if ($convertedDomain == $sld . $tld) {
                    $skipAllowIDNDomains = true;
                    $isIdn = false;
                }
            } catch (\Exception $e) {
                return 0;
            }
        }
        if ($isIdn === false) {
            if (preg_replace("/[^.%\$^'#~@&*(),_£?!+=:{}[]()|\\/ \\\\ ]/", "", $sld)) {
                return 0;
            }
            if ((!Config\Setting::getValue("AllowIDNDomains") || $skipAllowIDNDomains === true) && preg_replace("/[^a-z0-9-.]/i", "", $sld . $tld) != $sld . $tld) {
                return 0;
            }
            if (preg_replace("/[^a-z0-9-.]/", "", $tld) != $tld) {
                return 0;
            }
            $validMask = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-";
            if (strspn($sld, $validMask) != strlen($sld)) {
                return 0;
            }
        }
        run_hook("DomainValidation", ["sld" => $sld, "tld" => $tld]);
        if ($sld === false && $sld !== 0 || !$tld) {
            return 0;
        }
        $coreTLDs = [".com", ".net", ".org", ".info", "biz", ".mobi", ".name", ".asia", ".tel", ".in", ".mn", ".bz", ".cc", ".tv", ".us", ".me", ".co.uk", ".me.uk", ".org.uk", ".net.uk", ".ch", ".li", ".de", ".jp"];
        $DomainMinLengthRestrictions = $DomainMaxLengthRestrictions = [];
        require ROOTDIR . "/configuration.php";
        foreach ($coreTLDs as $cTLD) {
            if (!array_key_exists($cTLD, $DomainMinLengthRestrictions)) {
                $DomainMinLengthRestrictions[$cTLD] = 3;
            }
            if (!array_key_exists($cTLD, $DomainMaxLengthRestrictions)) {
                $DomainMaxLengthRestrictions[$cTLD] = 63;
            }
        }
        if (array_key_exists($tld, $DomainMinLengthRestrictions) && strlen($sld) < $DomainMinLengthRestrictions[$tld]) {
            return 0;
        }
        if (array_key_exists($tld, $DomainMaxLengthRestrictions) && $DomainMaxLengthRestrictions[$tld] < strlen($sld)) {
            return 0;
        }
        return 1;
    }

    public function getDomainsDatabyID($domainid)
    {
        $where = ["id" => (int) $domainid];
        if (defined("CLIENTAREA")) {
            if (!Auth::client()) {
                return false;
            }
            $where["userid"] = Auth::client()->id;
        }
        return $this->getDomainsData($where);
    }

    private function getDomainsData($where)
    {
        try {
            $domain = Domain\Domain::findOrFail($where["id"]);
            if (array_key_exists("userid", $where) && $domain->clientId != $where["userid"]) {
                throw new Exception\Module\NotServicable("Invalid Access Attempt");
            }
            $this->id = $domain->id;
            $this->data = $domain->toArray();
            $this->domainModel = $domain;
            return $this->data;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isActive()
    {
        if (is_array($this->data) && $this->data["status"] == Domain\Status::ACTIVE) {
            return true;
        }
        return false;
    }

    public function isPending()
    {
        if (is_array($this->data) && $this->data["status"] == Domain\Status::PENDING) {
            return true;
        }
        return false;
    }

    public function getData($var)
    {
        return isset($this->data[$var]) ? $this->data[$var] : "";
    }

    public function getModule()
    {
        $whmcs = \App::self();
        return $whmcs->sanitize("0-9a-z_-", $this->domainModel->registrarModuleName);
    }

    public function hasFunction($function)
    {
        $mod = new Module\Registrar();
        if (!$mod) {
            $mod->load($this->getModule());
        }
        return $mod->functionExists($function);
    }

    public function moduleCall($function, $additionalVars = [])
    {
        $module = $this->getModule();
        if (!$module) {
            $this->moduleresults = ["error" => "Domain not assigned to a registrar module"];
            return false;
        }
        if (is_null($this->registrarModule)) {
            try {
                $this->registrarModule = $this->domainModel->getRegistrarInterface();
            } catch (Exception\Module\ModuleNotFound $e) {
                $this->moduleresults = ["error" => "Registrar module not found"];
                return false;
            } catch (\Exception $e) {
                $this->moduleresults = ["error" => "An unknown error occurred"];
                return false;
            }
        }
        $mod = $this->registrarModule;
        $results = $mod->call($function, $additionalVars);
        $params = $mod->getParams();
        $vars = ["params" => $params, "results" => $results, "functionExists" => $results !== Module\Registrar::FUNCTIONDOESNTEXIST, "functionSuccessful" => is_array($results) && empty($results["error"]) || is_object($results)];
        $successOrFail = "";
        if (!$vars["functionSuccessful"] && $vars["functionExists"]) {
            $successOrFail = "Failed";
        }
        $hookResults = run_hook("AfterRegistrar" . $function . $successOrFail, $vars);
        try {
            if (HookMgr::processResults($module, $function, $hookResults)) {
                return true;
            }
        } catch (Exception $e) {
            return ["error" => $e->getMessage()];
        }
        if ($results === Module\Registrar::FUNCTIONDOESNTEXIST) {
            $this->moduleresults = ["error" => "Function not found"];
            return false;
        }
        $this->moduleresults = $results;
        return is_array($results) && array_key_exists("error", $results) && $results["error"] ? false : true;
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

    public function getDefaultNameservers()
    {
        global $whmcs;
        $vars = [];
        $serverid = get_query_val("tblhosting", "server", ["domain" => $this->getData("domain")]);
        if ($serverid) {
            $result = select_query("tblservers", "nameserver1,nameserver2,nameserver3,nameserver4,nameserver5", ["id" => $serverid]);
            $data = mysql_fetch_array($result);
            for ($i = 1; $i <= 5; $i++) {
                $vars["ns" . $i] = trim($data["nameserver" . $i]);
            }
        } else {
            for ($i = 1; $i <= 5; $i++) {
                $vars["ns" . $i] = trim($whmcs->get_config("DefaultNameserver" . $i));
            }
        }
        return $vars;
    }

    public function getSLD()
    {
        $domain = $this->getData("domain");
        $domainparts = explode(".", $this->getData("domain"), 2);
        return $domainparts[0];
    }

    public function getTLD()
    {
        $domain = $this->getData("domain");
        $domainparts = explode(".", $this->getData("domain"), 2);
        return $domainparts[1];
    }

    public function buildWHOISSaveArray($data)
    {
        $arr = ["First Name" => "firstname", "Last Name" => "lastname", "Full Name" => "fullname", "Contact Name" => "fullname", "Email" => "email", "Email Address" => "email", "Job Title" => "", "Company Name" => "companyname", "Organisation Name" => "companyname", "Address" => "address1", "Address 1" => "address1", "Street" => "address1", "Street 1" => "address1", "Address 2" => "address2", "Street 2" => "address2", "City" => "city", "State" => "state", "County" => "state", "Region" => "state", "Postcode" => "postcode", "ZIP Code" => "postcode", "ZIP" => "postcode", "Country" => "country", "Phone" => "phonenumberformatted", "Phone Number" => "phonenumberformatted", "Phone Country Code" => "phonecc"];
        $retarr = [];
        foreach ($arr as $k => $v) {
            $retarr[$k] = $data[$v];
        }
        return $retarr;
    }

    public function getManagementOptions()
    {
        $domainName = new Domains\Domain($this->getData("domain"));
        $managementOptions = ["nameservers" => false, "contacts" => false, "privatens" => false, "locking" => false, "dnsmanagement" => false, "emailforwarding" => false, "idprotection" => false, "eppcode" => false, "release" => false, "addons" => false];
        if ($this->isActive()) {
            $managementOptions["nameservers"] = $this->hasFunction("GetNameservers");
            $managementOptions["contacts"] = $this->hasFunction("GetContactDetails");
        } else {
            if ($this->isPending()) {
                $managementOptions["nameservers"] = true;
                $managementOptions["contacts"] = true;
            }
        }
        $managementOptions["privatens"] = $this->hasFunction("RegisterNameserver");
        $managementOptions["locking"] = $domainName->getLastTLDSegment() != "uk" && $this->hasFunction("GetRegistrarLock");
        $managementOptions["release"] = $domainName->getLastTLDSegment() == "uk" && $this->hasFunction("ReleaseDomain");
        $tldPricing = Database\Capsule::table("tbldomainpricing")->where("extension", "=", "." . $domainName->getTopLevel())->get()->all();
        $tldPricing = $tldPricing[0];
        $managementOptions["eppcode"] = $tldPricing->eppcode && $this->hasFunction("GetEPPCode");
        $managementOptions["dnsmanagement"] = $this->getData("dnsmanagement") && $this->hasFunction("GetDNS");
        $managementOptions["emailforwarding"] = $this->getData("emailforwarding") && $this->hasFunction("GetEmailForwarding");
        $managementOptions["idprotection"] = $this->getData("idprotection") ? true : false;
        $managementOptions["addons"] = $tldPricing->dnsmanagement || $tldPricing->emailforwarding || $tldPricing->idprotection;
        return $managementOptions;
    }

    public static function getRenewableDomains($userID = 0, $specificDomains = NULL)
    {
        if ($userID == 0) {
            $userID = Auth::client()->id;
        }
        $renewals = [];
        $renewalsByStatus = ["domainrenewalsbeforerenewlimit" => [], "domainrenewalspastgraceperiod" => [], "domainrenewalsingraceperiod" => [], "domainsExpiringSoon" => [], "domainsActive" => []];
        $hasExpiredDomains = $hasDomainsTooEarlyToRenew = $hasDomainsInGracePeriod = false;
        if ($userID) {
            if (!function_exists("getTLDPriceList")) {
                require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "domainfunctions.php";
            }
            $clientCurrency = getCurrency($userID);
            $domainRenewalPriceOptions = [];
            $domainRenewalMinimums = \App::getApplicationConfig()->DomainRenewalMinimums;
            $domainRenewalMinimums = array_merge([".co.uk" => "180", ".org.uk" => "180", ".me.uk" => "180", ".com.au" => "90", ".net.au" => "90", ".org.au" => "90"], is_array($domainRenewalMinimums) ? $domainRenewalMinimums : []);
            $domains = Domain\Domain::ofClient($userID)->orderBy("status", "desc")->orderBy("expirydate", "asc");
            if (is_array($specificDomains)) {
                $domains = $domains->whereIn("id", $specificDomains);
            } else {
                $domains = $domains->whereIn("status", [Domain\Status::ACTIVE, Domain\Status::GRACE, Domain\Status::REDEMPTION, Domain\Status::EXPIRED]);
            }
            $domains = $domains->get();
            foreach ($domains as $singleDomain) {
                $id = $singleDomain->id;
                $domain = $singleDomain->domain;
                $expiryDate = $singleDomain->expiryDate;
                $normalisedExpiryDate = $singleDomain->getRawAttribute("expirydate");
                $status = $singleDomain->status;
                $renewalGracePeriod = $singleDomain->gracePeriod;
                $gracePeriodFee = $singleDomain->gracePeriodFee;
                $redemptionGracePeriod = $singleDomain->redemptionGracePeriod;
                $redemptionGracePeriodFee = $singleDomain->redemptionGracePeriodFee;
                $isPremium = $singleDomain->isPremium;
                if (0 < $gracePeriodFee) {
                    $gracePeriodFee = convertCurrency($gracePeriodFee, null, $clientCurrency["id"], 1);
                }
                if (0 < $redemptionGracePeriodFee) {
                    $redemptionGracePeriodFee = convertCurrency($redemptionGracePeriodFee, null, $clientCurrency["id"], 1);
                }
                if (!$renewalGracePeriod || $renewalGracePeriod < 0 || $gracePeriodFee < 0) {
                    $renewalGracePeriod = 0;
                    $gracePeriodFee = 0;
                }
                if (!$redemptionGracePeriod || $redemptionGracePeriod < 0 || $redemptionGracePeriodFee < 0) {
                    $redemptionGracePeriod = 0;
                    $redemptionGracePeriodFee = 0;
                }
                if ($normalisedExpiryDate == "0000-00-00") {
                    $expiryDate = $singleDomain->nextDueDate;
                }
                $today = Carbon::today();
                $expiry = $expiryDate->copy();
                $todayExpiryDifference = $today->diff($expiry);
                $daysUntilExpiry = ($todayExpiryDifference->invert == 1 ? -1 : 1) * $todayExpiryDifference->days;
                $tld = "." . $singleDomain->tld;
                $beforeRenewLimit = $inGracePeriod = false;
                $pastGracePeriod = $inRedemptionGracePeriod = $pastRedemptionGracePeriod = false;
                $earlyRenewalRestriction = 0;
                if (array_key_exists($tld, $domainRenewalMinimums)) {
                    $earlyRenewalRestriction = $domainRenewalMinimums[$tld];
                    if ($earlyRenewalRestriction < $daysUntilExpiry) {
                        $beforeRenewLimit = true;
                        if (!$hasDomainsTooEarlyToRenew) {
                            $hasDomainsTooEarlyToRenew = true;
                        }
                    }
                }
                if (!$beforeRenewLimit && $daysUntilExpiry < 0) {
                    if ($renewalGracePeriod && 0 - $renewalGracePeriod <= $daysUntilExpiry && $gracePeriodFee) {
                        $inGracePeriod = true;
                    } else {
                        if (0 - ($renewalGracePeriod + $redemptionGracePeriod) <= $daysUntilExpiry && $redemptionGracePeriodFee) {
                            $pastGracePeriod = true;
                            $inRedemptionGracePeriod = true;
                        } else {
                            if (!$gracePeriodFee && !$redemptionGracePeriodFee || $daysUntilExpiry < 0 - ($renewalGracePeriod + $redemptionGracePeriod)) {
                                $pastGracePeriod = true;
                                $pastRedemptionGracePeriod = true;
                                if (!$hasExpiredDomains) {
                                    $hasExpiredDomains = true;
                                }
                            }
                        }
                    }
                }
                if (!array_key_exists($tld, $domainRenewalPriceOptions)) {
                    $tempPriceList = getTLDPriceList($tld, true, true);
                    $renewalOptions = [];
                    foreach ($tempPriceList as $regPeriod => $options) {
                        if ($options["renew"]) {
                            if ($regPeriod != 10) {
                                $renewalOptions[] = ["period" => $regPeriod, "price" => $options["renew"], "rawRenewalPrice" => $options["renew"], "gracePeriodFee" => NULL, "redemptionGracePeriodFee" => NULL];
                            }
                        }
                    }
                    $domainRenewalPriceOptions[$tld] = $renewalOptions;
                } else {
                    $renewalOptions = $domainRenewalPriceOptions[$tld];
                }
                if ($isPremium) {
                    $renewalCostPrice = Domain\Extra::whereDomainId($singleDomain->id)->whereName("registrarRenewalCostPrice")->first();
                    if ($renewalCostPrice) {
                        $renewalOptions = [];
                        $markupPremiumPrice = $renewalCostPrice->value;
                        $markupPremiumPrice *= 1 + Domains\Pricing\Premium::markupForCost($markupPremiumPrice) / 100;
                        $premiumRenewalPricing = ["period" => 1, "price" => new View\Formatter\Price($markupPremiumPrice, $clientCurrency), "rawRenewalPrice" => new View\Formatter\Price($markupPremiumPrice, $clientCurrency), "gracePeriodFee" => NULL, "redemptionGracePeriodFee" => NULL];
                        $renewalOptions[] = $premiumRenewalPricing;
                    }
                }
                $daysLeftInPeriod = 0;
                if (count($renewalOptions) && ($inGracePeriod || $inRedemptionGracePeriod)) {
                    $renewalOptions = reset($renewalOptions);
                    $renewalPeriod = $renewalOptions["period"];
                    $renewalPrice = is_null($renewalOptions["price"]) ? 0 : $renewalOptions["price"]->toNumeric();
                    $renewalOptions = [];
                    $daysLeftInPeriod = $daysUntilExpiry;
                    if ($inGracePeriod) {
                        $graceOptions = ["period" => $renewalPeriod, "rawRenewalPrice" => new View\Formatter\Price($renewalPrice, $clientCurrency), "gracePeriodFee" => new View\Formatter\Price($gracePeriodFee, $clientCurrency), "price" => new View\Formatter\Price($renewalPrice + $gracePeriodFee, $clientCurrency), "redemptionGracePeriodFee" => NULL];
                        $renewalOptions[] = $graceOptions;
                        $daysLeftInPeriod += $renewalGracePeriod;
                    }
                    if ($inRedemptionGracePeriod) {
                        $redemptionOptions = ["period" => $renewalPeriod, "rawRenewalPrice" => new View\Formatter\Price($renewalPrice, $clientCurrency), "gracePeriodFee" => new View\Formatter\Price($gracePeriodFee, $clientCurrency), "redemptionGracePeriodFee" => new View\Formatter\Price($redemptionGracePeriodFee, $clientCurrency), "price" => new View\Formatter\Price($renewalPrice + $gracePeriodFee + $redemptionGracePeriodFee, $clientCurrency)];
                        $renewalOptions[] = $redemptionOptions;
                        $daysLeftInPeriod += $renewalGracePeriod + $redemptionGracePeriod;
                    }
                    if (!$hasDomainsInGracePeriod) {
                        $hasDomainsInGracePeriod = true;
                    }
                }
                $eligibleForRenewal = true;
                if ($specificDomains && !in_array($status, [Domain\Status::ACTIVE, Domain\Status::GRACE, Domain\Status::REDEMPTION, Domain\Status::EXPIRED])) {
                    $eligibleForRenewal = false;
                    $beforeRenewLimit = true;
                }
                $rawStatus = ClientArea::getRawStatus($status);
                $tenYears = $today->copy()->addYears(10);
                $domainRenewalOptions = [];
                foreach ($renewalOptions as $key => $renewalOption) {
                    $newExpiry = $expiryDate->copy();
                    $newExpiry->addYears($renewalOption["period"]);
                    if ($newExpiry <= $tenYears) {
                        $domainRenewalOptions[] = $renewalOption;
                    }
                }
                if (!count($domainRenewalOptions)) {
                    $eligibleForRenewal = false;
                }
                if ($eligibleForRenewal && $singleDomain->isFree()) {
                    $eligibleForRenewal = false;
                    $domainRenewalOptions = [];
                }
                if (count($renewalOptions) || is_array($specificDomains) && in_array($id, $specificDomains)) {
                    $renewal = ["id" => $id, "domain" => $domain, "tld" => $tld, "status" => Lang::trans("clientarea" . $rawStatus), "expiryDate" => $expiryDate, "normalisedExpiryDate" => $normalisedExpiryDate, "daysUntilExpiry" => $daysUntilExpiry, "beforeRenewLimit" => $beforeRenewLimit, "beforeRenewLimitDays" => $earlyRenewalRestriction, "inGracePeriod" => $inGracePeriod, "pastGracePeriod" => $pastGracePeriod, "gracePeriodDays" => $renewalGracePeriod, "inRedemptionGracePeriod" => $inRedemptionGracePeriod, "pastRedemptionGracePeriod" => $pastRedemptionGracePeriod, "redemptionGracePeriodDays" => $redemptionGracePeriod, "daysLeftInPeriod" => $daysLeftInPeriod, "renewalOptions" => $domainRenewalOptions, "statusClass" => View\Helper::generateCssFriendlyClassName($status), "expiringSoon" => $daysUntilExpiry <= 45 && $status != Domain\Status::EXPIRED, "eligibleForRenewal" => $eligibleForRenewal, "freeDomainRenewal" => $singleDomain->isFree(), "isPremium" => $isPremium];
                    if (defined("SHOPPING_CART")) {
                        $renewal = array_merge($renewal, ["expirydate" => fromMySQLDate($renewal["expiryDate"]), "daysuntilexpiry" => $renewal["daysUntilExpiry"], "beforerenewlimit" => $renewal["beforeRenewLimit"], "beforerenewlimitdays" => $renewal["beforeRenewLimitDays"], "ingraceperiod" => $renewal["inGracePeriod"], "pastgraceperiod" => $renewal["pastGracePeriod"], "graceperioddays" => $renewal["gracePeriodDays"], "renewaloptions" => $renewal["renewalOptions"]]);
                    }
                    $renewals[] = $renewal;
                    $statusToUse = "domainsActive";
                    if ($beforeRenewLimit) {
                        $statusToUse = "domainrenewalsbeforerenewlimit";
                    }
                    if ($inGracePeriod) {
                        $statusToUse = "domainsExpiringSoon";
                    }
                    if ($inRedemptionGracePeriod) {
                        $statusToUse = "domainrenewalsingraceperiod";
                    }
                    if ($pastRedemptionGracePeriod) {
                        $statusToUse = "domainrenewalspastgraceperiod";
                    }
                    $renewalsByStatus[$statusToUse][] = $renewal;
                }
            }
            if ($renewals) {
                usort($renewals, function ($firstDomain, $secondDomain) {
                    return $secondDomain["daysUntilExpiry"] < $firstDomain["daysUntilExpiry"];
                });
            }
            if ($renewalsByStatus) {
                foreach ($renewalsByStatus as $status => $statusRenewals) {
                    usort($statusRenewals, function ($firstDomain, $secondDomain) {
                        return $secondDomain["daysUntilExpiry"] < $firstDomain["daysUntilExpiry"];
                    });
                }
            }
        }
        return ["renewals" => $renewals, "renewalsByStatus" => $renewalsByStatus, "hasExpiredDomains" => $hasExpiredDomains, "hasDomainsTooEarlyToRenew" => $hasDomainsTooEarlyToRenew, "hasDomainsInGracePeriod" => $hasDomainsInGracePeriod];
    }

    public function obtainEmailReminders()
    {
        $reminderData = [];
        $reminders = select_query("tbldomainreminders", "", ["domain_id" => $this->id], "id", "DESC");
        while ($data = mysql_fetch_assoc($reminders)) {
            $reminderData[] = $data;
        }
        return $reminderData;
    }

    public function getDomainInformation()
    {
        if (is_null($this->domainInformation)) {
            $domainInformation = NULL;
            if ($this->hasFunction("GetDomainInformation")) {
                $success = $this->moduleCall("GetDomainInformation");
                if (!$success) {
                    throw new Exception\Module\NotServicable($this->getLastError());
                }
                $domainInformation = $this->getModuleReturn();
                if (!$domainInformation instanceof Domain\Registrar\Domain) {
                    throw new Exception\Module\NotServicable("Invalid Response");
                }
            }
            if (!$domainInformation) {
                $domainInformation = new Domain\Registrar\Domain();
            }
            if (!$domainInformation->hasNameservers() && $this->hasFunction("GetNameservers")) {
                $success = $this->moduleCall("GetNameservers");
                if ($success) {
                    $domainInformation->setNameservers($this->getModuleReturn());
                } else {
                    throw new Exception\Module\NotServicable($this->getLastError());
                }
            }
            if (!$domainInformation->hasTransferLock() && $this->hasFunction("GetRegistrarLock")) {
                $success = $this->moduleCall("GetRegistrarLock");
                if ($success) {
                    $domainInformation->setTransferLock($this->getModuleReturn() === "locked");
                }
            }
            $this->domainInformation = $domainInformation;
        }
        return $this->domainInformation;
    }

    public function saveContactDetails(Client $client, $contactdetails, $wc, $sel = [])
    {
        $userContactDetails = $client->getDetails();
        $language = $userContactDetails["language"];
        $contactDetails = [];
        foreach ($wc as $wc_key => $wc_val) {
            if ($wc_val == "contact") {
                $selectedContact = $sel[$wc_key];
                $selectedContactType = substr($selectedContact, 0, 1);
                $selectedContactID = substr($selectedContact, 1);
                $tmpcontactdetails = [];
                if ($selectedContactType == "u") {
                    $tmpcontactdetails = $userContactDetails;
                } else {
                    if ($selectedContactType == "c") {
                        if (!array_key_exists($selectedContactID, $contactDetails)) {
                            $contactDetails[$selectedContactID] = $client->getDetails($selectedContactID);
                        }
                        $tmpcontactdetails = $contactDetails[$selectedContactID];
                    }
                }
                $contactdetails[$wc_key] = $this->buildWHOISSaveArray($tmpcontactdetails);
            } else {
                if (isset($contactdetails[$wc_key]) && is_array($contactdetails[$wc_key])) {
                    normaliseInternationalPhoneNumberFormat($contactdetails[$wc_key]);
                }
            }
        }
        unset($contactDetails);
        if (!$language) {
            $language = Config\Setting::getValue("Language");
        }
        $success = $this->moduleCall("SaveContactDetails", ["irtpOptOut" => \App::getFromRequest("irtpOptOut"), "irtpOptOutReason" => \App::getFromRequest("irtpOptOutReason"), "contactdetails" => $contactdetails, "language" => $language]);
        if ($success) {
            $return = ["status" => "success", "contactDetails" => $contactdetails];
            if ($this->getModuleReturn("pending")) {
                $return["status"] = "pending";
                $return["pendingData"] = $this->getModuleReturn("pendingData");
            }
            return $return;
        }
        throw new Exception\Module\NotServicable($this->getLastError());
    }

    public function fromPunycode($parts)
    {
        return Domains\Idna::fromPunycode($parts["sld"] . $parts["tld"]);
    }

    public function getContactFieldNameTranslations($contactDetails)
    {
        $contactTranslations = [];
        foreach ($contactDetails as $contactType) {
            foreach (array_keys($contactType) as $contactFieldName) {
                $fieldLangKey = "domaincontactdetails." . $contactFieldName;
                $langifiedValue = Lang::trans($fieldLangKey);
                if ($langifiedValue !== $fieldLangKey) {
                    $contactTranslations[$contactFieldName] = $langifiedValue;
                } else {
                    $contactTranslations[$contactFieldName] = $contactFieldName;
                }
            }
        }
        return $contactTranslations;
    }

    public function normalisePhoneNumberInContactDetails($contactDetails)
    {
        unset($contactDetails["domain"]);
        foreach ($contactDetails as &$contactData) {
            $contactData = Input\Sanitize::encode($contactData);
            if (is_array($contactData)) {
                normaliseInternationalPhoneNumberFormat($contactData, false);
            }
            unset($contactData["Phone Country Code"]);
        }
        unset($contactData);
        return $contactDetails;
    }
}
