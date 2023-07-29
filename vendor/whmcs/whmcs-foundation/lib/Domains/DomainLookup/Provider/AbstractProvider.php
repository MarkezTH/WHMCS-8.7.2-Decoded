<?php

namespace WHMCS\Domains\DomainLookup\Provider;

abstract class AbstractProvider
{
    protected abstract function getGeneralAvailability($sld, $tlds);
    protected abstract function getDomainSuggestions(\WHMCS\Domains\Domain $domain, $tldsToInclude);
    public abstract function getSettings();

    public function checkAvailability(\WHMCS\Domains\Domain $domain, $tlds)
    {
        $resultsList = $this->getGeneralAvailability($domain->getIdnSecondLevel(), $tlds);
        if (!$resultsList instanceof \WHMCS\Domains\DomainLookup\ResultsList) {
            throw new \InvalidArgumentException("Return must be an instance of \\WHMCS\\Domains\\DomainLookup\\ResultsList");
        }
        $this->saveWhoisLog($this->resultsToWhoisLogArray($resultsList));
        return $resultsList;
    }

    protected function getSpotlightTlds()
    {
        return getSpotlightTlds();
    }

    public function getSuggestions(\WHMCS\Domains\Domain $domain)
    {
        $resultsList = $this->getDomainSuggestions($domain, $this->getTldsForSuggestions());
        if (!$resultsList instanceof \WHMCS\Domains\DomainLookup\ResultsList) {
            throw new \InvalidArgumentException("Return must be an instance of \\WHMCS\\Domains\\DomainLookup\\ResultsList");
        }
        $spotlightDomains = [];
        foreach ($this->getSpotlightTlds() as $tld) {
            $spotlightDomains[] = $domain->getSecondLevel() . $tld;
        }
        $shownElsewhere = array_merge([$domain->getDomain()], $spotlightDomains);
        $list = $resultsList->toArray();
        $this->saveWhoisLog($this->resultsToWhoisLogArray($resultsList));
        foreach ($list as $key => $result) {
            if (in_array($result["domainName"], $shownElsewhere)) {
                $resultsList->offsetUnset($key);
            } else {
                if (!$result["isValidDomain"]) {
                    $resultsList->offsetUnset($key);
                }
            }
        }
        $resultsList->uasort(function (\WHMCS\Domains\DomainLookup\SearchResult $firstResult, \WHMCS\Domains\DomainLookup\SearchResult $secondResult) {
            $scoreA = round($firstResult->getScore(), 3);
            $scoreB = round($secondResult->getScore(), 3);
            if ($scoreA === $scoreB) {
                return 0;
            }
            return $scoreB < $scoreA ? -1 : 1;
        });
        return $resultsList;
    }

    public function getTldsForSuggestions()
    {
        $moduleName = "WhmcsWhois";
        if (isset($this->registrarModule)) {
            $moduleName = $this->registrarModule->getLoadedModule();
        }
        $setting = \WHMCS\Domains\DomainLookup\Settings::ofRegistrar($moduleName)->whereSetting("suggestTlds")->first();
        if (!$setting) {
            return [];
        }
        $settingTlds = explode(",", $setting->value);
        $qualifiedTlds = getTLDList("register");
        $suggestedTlds = array_intersect($settingTlds, $qualifiedTlds);
        return array_values(array_filter(array_map(function ($tld) {
            return ltrim($tld, ".");
        }, $suggestedTlds)));
    }

    public function checkSubDomain(\WHMCS\Domains\Domain $subDomain)
    {
        if (!\WHMCS\Domains\Domain::isValidDomainName($subDomain->getSecondLevel(), ".com", false, "")) {
            throw new \WHMCS\Exception\InvalidDomain("ordererrordomaininvalid");
        }
        $bannedSubDomainPrefixes = explode(",", \WHMCS\Config\Setting::getValue("BannedSubdomainPrefixes"));
        if (in_array($subDomain->getSecondLevel(), $bannedSubDomainPrefixes)) {
            throw new \WHMCS\Exception\InvalidDomain("ordererrorsbudomainbanned");
        }
        if (\WHMCS\Config\Setting::getValue("AllowDomainsTwice")) {
            $subChecks = \WHMCS\Database\Capsule::table("tblhosting")->where("domain", "=", $subDomain->getSecondLevel() . $subDomain->getDotTopLevel())->whereNotIn("domainstatus", ["Terminated", "Cancelled", "Fraud"])->count();
            if ($subChecks) {
                throw new \WHMCS\Exception\InvalidDomain("ordererrorsubdomaintaken");
            }
        }
        $validate = new \WHMCS\Validate();
        run_validate_hook($validate, "CartSubdomainValidation", ["subdomain" => $subDomain->getSecondLevel(), "domain" => $subDomain->getDotTopLevel()]);
        if ($validate->hasErrors()) {
            $errors = "";
            foreach ($validate->getErrors() as $error) {
                $errors .= $error . "<br />";
            }
            throw new \WHMCS\Exception\InvalidDomain($errors);
        }
    }

    public function checkOwnDomain(\WHMCS\Domains\Domain $ownDomain)
    {
        try {
            \WHMCS\Domains\Domain::isValidDomainName($ownDomain->getUnicodeSecondLevel(), $ownDomain->getDotTopLevel(), true, "unique_service_domain");
        } catch (\WHMCS\Exception\Domains\UniqueDomainRequired $e) {
            throw new \WHMCS\Exception\InvalidDomain("ordererrordomainalreadyexists");
        } catch (\WHMCS\Exception $e) {
            throw new \WHMCS\Exception\InvalidDomain("ordererrordomaininvalid");
        }
        if (!\WHMCS\Domains\Domain::isSupportedTld($ownDomain->getDotTopLevel())) {
            throw new \WHMCS\Exception\InvalidDomain("ordererrordomaininvalid");
        }
        if (\WHMCS\Config\Setting::getValue("AllowDomainsTwice")) {
            $subChecks = \WHMCS\Database\Capsule::table("tblhosting")->where("domain", "=", $ownDomain->getSecondLevel() . $ownDomain->getDotTopLevel())->whereNotIn("domainstatus", ["Terminated", "Cancelled", "Fraud"])->count();
            if ($subChecks) {
                throw new \WHMCS\Exception\InvalidDomain("ordererrordomainalreadyexists");
            }
        }
        $validate = new \WHMCS\Validate();
        run_validate_hook($validate, "ShoppingCartValidateDomain", ["domainoption" => "owndomain", "sld" => $ownDomain->getSecondLevel(), "tld" => $ownDomain->getDotTopLevel()]);
        if ($validate->hasErrors()) {
            $errors = "";
            foreach ($validate->getErrors() as $error) {
                $errors .= $error . "\n";
            }
            throw new \WHMCS\Exception\InvalidDomain($errors);
        }
    }

    public function getProviderName()
    {
        return str_replace("WHMCS\\Domains\\DomainLookup\\Provider\\", "", get_class($this));
    }

    protected function resultsToWhoisLogArray($list)
    {
        $nowTime = \WHMCS\Carbon::now()->toDateString();
        $results = [];
        foreach ($list as $value) {
            if ($value instanceof \WHMCS\Domains\DomainLookup\SearchResult) {
                $results[] = ["date" => $nowTime, "domain" => $value->toPunycode(), "ip" => \WHMCS\Utility\Environment\CurrentRequest::getIP()];
            }
        }
        return $results;
    }

    protected function saveWhoisLog($list)
    {
        \WHMCS\Database\Capsule::table("tblwhoislog")->insert($list);
    }
}
