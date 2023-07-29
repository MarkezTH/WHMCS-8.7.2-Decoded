<?php

namespace WHMCS\Domains;

class Domain
{
    protected $secondLevel = NULL;
    protected $topLevel = NULL;
    protected $secondLevelPunycode = NULL;
    protected $topLevelPunycode = NULL;
    protected $generalAvailability = true;
    protected $premiumDomain = false;
    protected static $whois = NULL;
    protected $throwExceptions = NULL;

    public function __construct($domain, $tld = NULL, $exceptions = true)
    {
        $this->throwExceptions = $exceptions;
        if ($tld) {
            $tld = ltrim($tld, ".");
            $this->setDomainBySecondAndTopLevels($domain, $tld);
        } else {
            $this->setDomain($domain);
        }
    }

    public static function createFromSldAndTld($sld, $tld)
    {
        return new static($sld, $tld);
    }

    protected function setDomain($domain)
    {
        $domain = \WHMCS\Config\Setting::getValue("AllowIDNDomains") ? mb_strtolower($domain) : strtolower($domain);
        $parts = explode(".", $domain, 2);
        if (isset($parts[1])) {
            $parts[0] = str_replace(" ", "", $parts[0]);
        }
        return $this->setDomainBySecondAndTopLevels($parts[0], isset($parts[1]) ? $parts[1] : "");
    }

    protected function setDomainBySecondAndTopLevels($self, $sld, $tld)
    {
        try {
            $idnDomain = Idna::toPunycode($sld . "." . $tld);
            $idnDomainParts = explode(".", $idnDomain, 2);
        } catch (\Throwable $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            $idnDomainParts = [$sld, $tld];
        }
        return $this->setSecondLevel($sld)->setTopLevel($tld)->setPunycodeSecondLevel($idnDomainParts[0])->setPunycodeTopLevel($idnDomainParts[1]);
    }

    public function setSecondLevel($secondLevel)
    {
        if (strpos($secondLevel, ".") === 0) {
            $secondLevel = substr($secondLevel, 1);
        }
        $this->secondLevel = $secondLevel;
        return $this;
    }

    public function getUnicodeSecondLevel()
    {
        return $this->secondLevel;
    }

    public function getSecondLevel()
    {
        return $this->getUnicodeSecondLevel();
    }

    public function setTopLevel($topLevel)
    {
        $topLevel = ltrim($topLevel, ".");
        $this->topLevel = $topLevel;
        return $this;
    }

    public function getTopLevel()
    {
        return $this->topLevel;
    }

    public function getDotTopLevel()
    {
        return "." . $this->topLevel;
    }

    public function getDotPunycodeTopLevel()
    {
        return "." . $this->getPunycodeTopLevel();
    }

    public function getSLD()
    {
        return $this->secondLevel;
    }

    public function getTLD()
    {
        return $this->topLevel;
    }

    public function getDomain($idn = true)
    {
        if ($idn && $this->isIdn()) {
            $sld = $this->getPunycodeSecondLevel();
            $tld = $this->getPunycodeTopLevel();
        } else {
            $sld = strtolower($this->getUnicodeSecondLevel());
            $tld = strtolower($this->getTopLevel());
        }
        if ($sld && $tld) {
            return $sld . "." . $tld;
        }
        return "";
    }

    public function toUnicode()
    {
        return $this->getDomain(false);
    }

    public function toPunycode()
    {
        return $this->getDomain();
    }

    public function getRawDomain()
    {
        return $this->toUnicode();
    }

    public function getLastTLDSegment()
    {
        $tld = $this->getTopLevel();
        $tldparts = explode(".", $tld);
        return $tldparts[count($tldparts) - 1];
    }

    public function getPunycodeSecondLevel()
    {
        return $this->secondLevelPunycode;
    }

    public function getPunycodeTopLevel()
    {
        return $this->topLevelPunycode;
    }

    public function getIdnSecondLevel()
    {
        return $this->getPunycodeSecondLevel();
    }

    public function isGeneralAvailability()
    {
        return (bool) $this->generalAvailability;
    }

    public function isPremiumDomain()
    {
        return (bool) $this->premiumDomain;
    }

    public function isIdn()
    {
        return $this->getUnicodeSecondLevel() != $this->getPunycodeSecondLevel();
    }

    public function setGeneralAvailability($generalAvailability)
    {
        $this->generalAvailability = (bool) $generalAvailability;
        return $this;
    }

    public function setPunycodeSecondLevel($idn)
    {
        $this->secondLevelPunycode = $idn;
        return $this;
    }

    public function setPunycodeTopLevel($self, $idn)
    {
        $this->topLevelPunycode = $idn;
        return $this;
    }

    public function setIdnSecondLevel($idn)
    {
        return $this->setPunycodeSecondLevel($idn);
    }

    public function setPremiumDomain($premiumDomain)
    {
        $this->premiumDomain = (bool) $premiumDomain;
        return $this;
    }

    public static function isValidDomain($domain)
    {
        return 0 < strlen($domain) && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    public static function isValidDomainName($sld, $tld, $exceptions = false, $domainValidationType = "unique_domain")
    {
        if (trim($sld, "-") != $sld) {
            if ($exceptions) {
                throw new \WHMCS\Exception\Domains\InvalidPrefix();
            }
            return false;
        }
        $isIdn = false;
        $allowIdnDomains = \WHMCS\Config\Setting::getValue("AllowIDNDomains");
        if ($allowIdnDomains) {
            try {
                $domain = Idna::toPunycode($sld);
                if ($domain !== $sld) {
                    $isIdn = true;
                }
            } catch (\Exception $e) {
                if ($exceptions) {
                    throw $e;
                }
                return false;
            }
        }
        if (!$isIdn && !static::containsValidNonIdnCharacters($sld, $tld)) {
            if ($exceptions) {
                throw new \WHMCS\Exception\Domains\IDNNotEnabled();
            }
            return false;
        }
        run_hook("DomainValidation", ["sld" => $sld, "tld" => $tld]);
        if ($sld === false && $sld !== 0 || !$tld) {
            if ($exceptions) {
                throw new \WHMCS\Exception\Domains\InvalidDomainLength();
            }
            return false;
        }
        list($DomainMinLengthRestrictions, $DomainMaxLengthRestrictions) = static::getTldDomainLengthRestrictions();
        $dottedTld = $tld;
        if ($tld[0] != ".") {
            $dottedTld = "." . $tld;
        }
        if (array_key_exists($dottedTld, $DomainMinLengthRestrictions) && strlen($sld) < $DomainMinLengthRestrictions[$dottedTld] || array_key_exists($dottedTld, $DomainMaxLengthRestrictions) && $DomainMaxLengthRestrictions[$dottedTld] < strlen($sld)) {
            if ($exceptions) {
                throw new \WHMCS\Exception\Domains\InvalidDomainLength();
            }
            return false;
        }
        if ($domainValidationType) {
            $validate = new \WHMCS\Validate();
            $validate->validate($domainValidationType, $domainValidationType, "ordererrordomainalreadyexists", "", Domain::createFromSldAndTld($sld, $tld));
            if ($validate->hasErrors()) {
                if ($exceptions) {
                    throw new \WHMCS\Exception\Domains\UniqueDomainRequired();
                }
                return false;
            }
        }
        return true;
    }

    protected static function containsValidNonIdnCharacters($sld, $tld)
    {
        $validmaskSld = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-";
        $validmaskTld = "abcdefghijklmnopqrstuvwxyz0123456789-.";
        if (strspn($sld, $validmaskSld) != strlen($sld) || strspn($tld, $validmaskTld) != strlen($tld)) {
            return false;
        }
        return true;
    }

    public static function isSupportedTld($tld)
    {
        $tld = ltrim($tld, ".");
        $dotTld = "." . $tld;
        if ((new \WHMCS\Domain\TopLevel\Categories())->hasTld($dotTld)) {
            return true;
        }
        $existsInDb = \WHMCS\Database\Capsule::table("tbldomainpricing")->where("extension", $dotTld)->first();
        if ($existsInDb) {
            return true;
        }
        if (!static::$whois) {
            static::$whois = new \WHMCS\WHOIS();
        }
        if (static::$whois->canLookup($dotTld)) {
            return true;
        }
        return false;
    }

    protected static function getTldDomainLengthRestrictions()
    {
        $appConfig = \Config::self();
        $DomainMinLengthRestrictions = $appConfig["DomainMinLengthRestrictions"];
        $DomainMaxLengthRestrictions = $appConfig["DomainMaxLengthRestrictions"];
        if (!is_array($DomainMaxLengthRestrictions)) {
            $DomainMaxLengthRestrictions = [];
        }
        if (!is_array($DomainMinLengthRestrictions)) {
            $DomainMinLengthRestrictions = [];
        }
        foreach (static::getCoreTldList() as $ctld) {
            if (!array_key_exists($ctld, $DomainMinLengthRestrictions)) {
                $DomainMinLengthRestrictions[$ctld] = 3;
            }
            if (!array_key_exists($ctld, $DomainMaxLengthRestrictions)) {
                $DomainMaxLengthRestrictions[$ctld] = 63;
            }
        }
        return [static::normalizeDomainLengthRestrictionArray($DomainMinLengthRestrictions), static::normalizeDomainLengthRestrictionArray($DomainMaxLengthRestrictions)];
    }

    protected static function normalizeDomainLengthRestrictionArray($restrictionArray)
    {
        foreach ($restrictionArray as $tld => $restriction) {
            if ($tld[0] != ".") {
                unset($restrictionArray[$tld]);
                $restrictionArray["." . $tld] = $restriction;
            }
        }
        return $restrictionArray;
    }

    protected static function getCoreTldList()
    {
        return [".com", ".net", ".org", ".info", "biz", ".mobi", ".name", ".asia", ".tel", ".in", ".mn", ".bz", ".cc", ".tv", ".us", ".me", ".co.uk", ".me.uk", ".org.uk", ".net.uk", ".ch", ".li", ".de", ".jp"];
    }

    public function alreadyBilledAsAHostingProduct()
    {
        return (bool) \WHMCS\Service\Service::where("domain", $this->getDomain())->whereNotIn("domainstatus", ["Terminated", "Cancelled", "Fraud"])->count();
    }

    public function alreadyBilledAsADomainItem()
    {
        return (bool) \WHMCS\Domain\Domain::where("domain", $this->getDomain())->whereNotIn("status", ["Expired", "Cancelled", "Fraud", "Transferred Away"])->count();
    }

    public function pricing()
    {
        return new DomainPricing($this);
    }

    public function group()
    {
        if (is_null($groups)) {
            $groups = \WHMCS\Database\Capsule::table("tbldomainpricing")->pluck(\WHMCS\Database\Capsule::raw("LOWER(`group`)"), "extension")->all();
        }
        return isset($groups[$this->getDotTopLevel()]) && $groups[$this->getDotTopLevel()] != "none" ? $groups[$this->getDotTopLevel()] : "";
    }

    public function getDomainMinimumLength()
    {
        $lengthRestrictions = self::getTldDomainLengthRestrictions();
        if (array_key_exists($this->getDotTopLevel(), $lengthRestrictions[0])) {
            return $lengthRestrictions[0][$this->getDotTopLevel()];
        }
        return 0;
    }

    public function getDomainMaximumLength()
    {
        $lengthRestrictions = self::getTldDomainLengthRestrictions();
        if (array_key_exists($this->getDotTopLevel(), $lengthRestrictions[1])) {
            return $lengthRestrictions[1][$this->getDotTopLevel()];
        }
        return 0;
    }

    protected function getPremiumPricing($registrar = NULL, $type = ["register", "renew"])
    {
        $sessionData = \WHMCS\Session::get("Premium");
        if (array_key_exists($this->getDomain(), $sessionData)) {
            unset($sessionData[$this->getDomain()]);
        }
        \WHMCS\Session::set("Premium", $sessionData);
        if (!(bool) (int) \WHMCS\Config\Setting::getValue("PremiumDomains")) {
            throw new \WHMCS\Exception("PremiumDomains not Enabled");
        }
        if (!$this->isPremiumDomain()) {
            throw new \WHMCS\Exception("Not Premium");
        }
        if (!$registrar) {
            $registrar = DomainLookup\Provider::getDomainLookupRegistrar();
        }
        $registrarModule = new \WHMCS\Module\Registrar();
        if (!$registrarModule->load($registrar)) {
            throw new \WHMCS\Exception("No Registrar Configured");
        }
        $pricing = $registrarModule->call("GetPremiumPrice", ["domain" => $this, "sld" => $this->getSecondLevel(), "tld" => $this->getDotTopLevel(), "type" => $type]);
        $pricingCurrency = $pricing["CurrencyCode"];
        unset($pricing["CurrencyCode"]);
        foreach ($pricing as $registerType => &$price) {
            $price = convertCurrency($price, \WHMCS\Database\Capsule::table("tblcurrencies")->where("code", "=", $pricingCurrency)->value("id"), \Currency::factoryForClientArea()->id);
        }
        $registerTransferKey = "register";
        if (array_key_exists("transfer", $pricing)) {
            $registerTransferKey = "transfer";
        }
        $hookReturns = run_hook("PremiumPriceOverride", ["domainName" => $this->getRawDomain(), "tld" => $this->getTopLevel(), "sld" => $this->getSecondLevel(), $registerTransferKey => $pricing[$registerTransferKey], "renew" => $pricing["renew"]]);
        $skipMarkup = false;
        foreach ($hookReturns as $hookReturn) {
            if (array_key_exists("noSale", $hookReturn) && $hookReturn["noSale"] === true) {
                throw new \WHMCS\Exception\Domains\Pricing\NoSale();
            }
            if (array_key_exists("contactUs", $hookReturn) && $hookReturn["contactUs"] === true) {
                throw new \WHMCS\Exception\Domains\Pricing\ContactUs();
            }
            if (array_key_exists("register", $hookReturn) && array_key_exists("register", $pricing)) {
                $premiumPricing["register"] = $hookReturn["register"];
            }
            if (array_key_exists("transfer", $hookReturn) && array_key_exists("transfer", $pricing)) {
                $premiumPricing["transfer"] = $hookReturn["transfer"];
            }
            if (array_key_exists("renew", $hookReturn) && array_key_exists("renew", $pricing)) {
                $premiumPricing["renew"] = $hookReturn["renew"];
            }
            if (array_key_exists("skipMarkup", $hookReturn) && $hookReturn["skipMarkup"] === true) {
                $skipMarkup = true;
            }
        }
        foreach ($pricing as $type => &$price) {
            if (!$skipMarkup) {
                $price *= 1 + Pricing\Premium::markupForCost($price) / 100;
            }
        }
        return $pricing;
    }

    public function getPremiumRegistrationPrice($registrar = NULL)
    {
        return $this->getPremiumPricing($registrar, ["register"]);
    }

    public function getPremiumRenewalPrice($registrar = NULL)
    {
        return $this->getPremiumPricing($registrar, ["renew"]);
    }

    protected static function eligibleCountriesForEuTld()
    {
        return ["AT", "BE", "BG", "CZ", "CY", "DE", "DK", "ES", "EE", "FI", "FR", "GR", "GB", "HU", "IE", "IT", "LT", "LU", "LV", "MT", "NL", "PL", "PT", "RO", "SE", "SK", "SI", "AX", "GF", "GI", "GP", "MQ", "RE"];
    }

    public static function isValidForEuRegistration($countryCode)
    {
        $eu = self::eligibleCountriesForEuTld();
        return in_array($countryCode, $eu);
    }

    public function getExtensionModel()
    {
        return Extension::where("extension", $this->getDotTopLevel())->first();
    }
}
