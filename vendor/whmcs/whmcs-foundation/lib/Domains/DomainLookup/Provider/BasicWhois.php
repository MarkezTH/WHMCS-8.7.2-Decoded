<?php

namespace WHMCS\Domains\DomainLookup\Provider;

class BasicWhois extends AbstractProvider
{
    protected $bulkCheckLimit = 10;
    protected static $apiClient = NULL;

    protected function getGeneralAvailability($sld, $tlds)
    {
        $domainSearchResults = new \WHMCS\Domains\DomainLookup\ResultsList();
        $count = 1;
        foreach ($tlds as $tld) {
            $domainSearchResult = new \WHMCS\Domains\DomainLookup\SearchResult($sld, $tld);
            $tld = $domainSearchResult->getDotPunycodeTopLevel();
            if ($count <= $this->bulkCheckLimit) {
                $api = $this->factoryApiClient();
                $apiResult = $api->lookup(["sld" => $sld, "tld" => $tld]);
                if (isset($apiResult["result"]) && $apiResult["result"] == "available") {
                    $domainSearchResult->setStatus($domainSearchResult::STATUS_NOT_REGISTERED);
                } else {
                    if (isset($apiResult["result"]) && $apiResult["result"] == "unavailable") {
                        if (!empty($apiResult["whois"]) && strpos($apiResult["whois"], "Right of registration:") !== false) {
                            $domainSearchResult->setStatus($domainSearchResult::STATUS_RESERVED);
                        } else {
                            $domainSearchResult->setStatus($domainSearchResult::STATUS_REGISTERED);
                        }
                    } else {
                        if (isset($apiResult["result"]) && $apiResult["result"] == "error") {
                            logActivity(sprintf("WHOIS Lookup Error for '%s': %s", $domainSearchResult->getDomain(), $apiResult["errordetail"] ? $apiResult["errordetail"] : "error detail unknown"));
                            $domainSearchResult->setStatus($domainSearchResult::STATUS_UNKNOWN);
                        } else {
                            logActivity(sprintf("WHOIS Lookup Error for '%s': %s", $domainSearchResult->getDomain(), "extension not listed in /resources/domains/dist.whois.json or /resources/domains/whois.json"));
                            $domainSearchResult->setStatus($domainSearchResult::STATUS_UNKNOWN);
                        }
                    }
                }
                $count++;
            } else {
                $domainSearchResult->setStatus($domainSearchResult::STATUS_NOT_REGISTERED);
            }
            $domainSearchResults->append($domainSearchResult);
        }
        return $domainSearchResults;
    }

    protected function preprocessDomainSuggestionTlds($tldsToInclude)
    {
        $spotlightTlds = $this->getSpotlightTlds();
        $tldsToInclude = array_filter($tldsToInclude, function ($tld) use($spotlightTlds) {
            if (in_array("." . $tld, $spotlightTlds)) {
                return false;
            }
            return true;
        });
        return $tldsToInclude;
    }

    protected function getDomainSuggestions(\WHMCS\Domains\Domain $domain, $tldsToInclude)
    {
        $tldsToInclude = $this->preprocessDomainSuggestionTlds($tldsToInclude);
        $results = $this->checkAvailability($domain, $tldsToInclude);
        foreach ($results as $key => $result) {
            $result = $result->toArray();
            if ($result["isRegistered"] || !$result["isValidDomain"]) {
                unset($results[$key]);
            }
        }
        return $results;
    }

    public function factoryApiClient()
    {
        if (!static::$apiClient) {
            $whois = new \WHMCS\WHOIS();
            static::$apiClient = $whois;
        }
        return static::$apiClient;
    }

    public function getSettings()
    {
        return [];
    }
}
