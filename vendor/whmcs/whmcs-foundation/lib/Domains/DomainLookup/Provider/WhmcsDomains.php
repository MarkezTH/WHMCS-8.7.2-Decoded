<?php

namespace WHMCS\Domains\DomainLookup\Provider;

class WhmcsDomains extends WhmcsWhois
{
    private $whmcsApiClient = NULL;
    private $settings = [];

    public function __construct(WhmcsDomains\ApiClient $apiClient = NULL)
    {
        $config = \DI::make("config");
        if (!$config["disable_whmcs_domain_lookup"]) {
            if (!$apiClient) {
                $url = $config["domain_lookup_url"];
                $key = $config["domain_lookup_key"];
                $apiClient = new WhmcsDomains\ApiClient($url, $key);
            }
            $this->whmcsApiClient = $apiClient;
            $class = "Lang";
            if (defined("ADMINAREA")) {
                $class = "AdminLang";
            }
            $this->settings = \WHMCS\Domains\DomainLookup\Settings::ofRegistrar("WhmcsWhois")->pluck("value", "setting")->toArray();
            $this->whmcsApiClient->setLanguage($class::getName())->setEnableSensitiveContentFilter((bool) empty($this->settings["suggestAdultDomains"]));
        }
    }

    protected function processUnknownResults($sld, $verisignResults, $tlds = [])
    {
        $knownResults = [];
        $unknownResults = [];
        $tlds = array_flip($tlds);
        foreach ($verisignResults as $result) {
            if (in_array($result->getStatus(), [\WHMCS\Domains\DomainLookup\SearchResult::STATUS_UNKNOWN, \WHMCS\Domains\DomainLookup\SearchResult::STATUS_TLD_NOT_SUPPORTED])) {
                $unknownResults[] = $result;
            } else {
                $knownResults[] = $result;
            }
            if (array_key_exists($result->getDotTopLevel(), $tlds)) {
                unset($tlds[$result->getDotTopLevel()]);
            }
        }
        $tlds = array_flip($tlds);
        if (!empty($unknownResults)) {
            $unknownTlds = array_map(function (\WHMCS\Domains\DomainLookup\SearchResult $result) {
                return $result->getTopLevel();
            }, $unknownResults);
            $unknownTlds = array_merge($unknownTlds, $tlds);
            $unknownResults = parent::getGeneralAvailability($sld, $unknownTlds)->getArrayCopy();
        }
        return new \WHMCS\Domains\DomainLookup\ResultsList(array_merge($knownResults, $unknownResults));
    }

    protected function getGeneralAvailabilityViaApi($sld, $tlds)
    {
        return $this->whmcsApiClient->bulkCheck($sld, $tlds);
    }

    protected function getGeneralAvailability($sld, $tlds)
    {
        if ($this->whmcsApiClient) {
            $tlds = array_filter($tlds);
            if (empty($tlds)) {
                return new \WHMCS\Domains\DomainLookup\ResultsList();
            }
            try {
                $verisignResults = $this->getGeneralAvailabilityViaApi($sld, $tlds);
                return $this->processUnknownResults($sld, $verisignResults, $tlds);
            } catch (\Exception $e) {
                return parent::getGeneralAvailability($sld, $tlds);
            }
        } else {
            return parent::getGeneralAvailability($sld, $tlds);
        }
    }

    protected function getDomainSuggestionsViaApi(\WHMCS\Domains\Domain $domain, $tldsToInclude)
    {
        return $this->whmcsApiClient->suggest($domain->getSecondLevel(), $tldsToInclude, 100);
    }

    protected function getDomainSuggestions(\WHMCS\Domains\Domain $domain, $tldsToInclude)
    {
        if ($this->whmcsApiClient) {
            $results = [];
            $tldsToInclude = array_filter($tldsToInclude);
            if (!empty($tldsToInclude)) {
                try {
                    $tldsToInclude = $this->preprocessDomainSuggestionTlds($tldsToInclude);
                    $results = $this->getDomainSuggestionsViaApi($domain, $tldsToInclude);
                } catch (\Exception $e) {
                    return parent::getDomainSuggestions($domain, $tldsToInclude);
                }
            }
            return new \WHMCS\Domains\DomainLookup\ResultsList($results);
        }
        return parent::getDomainSuggestions($domain, $tldsToInclude);
    }

    public function getSettings()
    {
        $settings = array_merge(parent::getSettings(), ["geotargetedResults" => ["FriendlyName" => \AdminLang::trans("general.geotargetedresults"), "Type" => "yesno", "Description" => "", "Default" => true]]);
        return $settings;
    }
}
