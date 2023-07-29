<?php

namespace WHMCS\Domains\DomainLookup\Provider;

class Registrar extends WhmcsWhois
{
    protected $registrarModule = NULL;

    protected function getGeneralAvailability($sld, $tlds)
    {
        try {
            $domain = new \WHMCS\Domains\Domain($sld);
            $domainSearchResults = $this->getRegistrar()->call("CheckAvailability", ["sld" => $sld, "tlds" => $tlds, "searchTerm" => $domain->getSecondLevel(), "tldsToInclude" => $tlds, "isIdnDomain" => $domain->isIdn(), "punyCodeSearchTerm" => $domain->isIdn() ? $domain->getIdnSecondLevel() : "", "premiumEnabled" => (bool) (int) \WHMCS\Config\Setting::getValue("PremiumDomains")]);
            foreach ($domainSearchResults as $key => $domainSearchResult) {
                if ($domainSearchResult->getStatus() == $domainSearchResult::STATUS_TLD_NOT_SUPPORTED) {
                    $unsupportedTld = $domainSearchResult->getDotTopLevel();
                    $tldNotSupportedByEnom = parent::getGeneralAvailability($sld, [$unsupportedTld]);
                    $domainSearchResult->setStatus($tldNotSupportedByEnom->offsetGet(0)->getStatus());
                }
            }
            return $domainSearchResults;
        } catch (\Exception $e) {
            return parent::getGeneralAvailability($sld, $tlds);
        }
    }

    protected function getDomainSuggestions(\WHMCS\Domains\Domain $domain, $tldsToInclude)
    {
        if (empty($tldsToInclude)) {
            return new \WHMCS\Domains\DomainLookup\ResultsList();
        }
        try {
            $settings = \WHMCS\Domains\DomainLookup\Settings::ofRegistrar($this->registrarModule->getLoadedModule())->pluck("value", "setting")->toArray();
            return $this->getRegistrar()->call("GetDomainSuggestions", ["searchTerm" => $domain->getSecondLevel(), "tldsToInclude" => $tldsToInclude, "isIdnDomain" => $domain->isIdn(), "punyCodeSearchTerm" => $domain->isIdn() ? $domain->getIdnSecondLevel() : "", "suggestionSettings" => $settings, "premiumEnabled" => (bool) (int) \WHMCS\Config\Setting::getValue("PremiumDomains")]);
        } catch (\Exception $e) {
            return parent::getDomainSuggestions($domain, $tldsToInclude);
        }
    }

    public function loadRegistrar($moduleName)
    {
        $this->registrarModule = new \WHMCS\Module\Registrar();
        return $this->registrarModule->load($moduleName);
    }

    public function getRegistrar()
    {
        return $this->registrarModule;
    }

    public function getSettings()
    {
        $result = $this->registrarModule->call("DomainSuggestionOptions");
        if (!is_array($result)) {
            $result = [];
        }
        $result = array_merge(parent::getSettings(), $result);
        return $result;
    }
}
